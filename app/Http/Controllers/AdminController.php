<?php

namespace App\Http\Controllers;

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Estagio;
use App\Models\HistoricoEstado;
use App\Models\Relatorio;
use App\Models\User;
use App\Services\LimiteCandidaturasTrimestrais;
use App\Services\NotificacaoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct(
        private readonly NotificacaoService $notificacoes,
        private readonly LimiteCandidaturasTrimestrais $limiteCandidaturas
    ) {}

    /**
     * Dashboard administrativo.
     *
     * Devolve os indicadores do painel e os dados dos gráficos
     * (funil, candidaturas por semana e vagas por departamento).
     */
    public function dashboard()
    {
        /*
        |--------------------------------------------------------------------------
        | Candidaturas por estado
        |--------------------------------------------------------------------------
        |
        | Uma única agregação substitui várias contagens separadas.
        |
        */
        $porEstado = Candidatura::query()
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $emAnalise = (int) ($porEstado['em_analise'] ?? 0);
        $aguardaDiretor = (int) ($porEstado['aguarda_diretor'] ?? 0);
        $aprovadas = (int) ($porEstado['aprovada'] ?? 0);
        $recusadas = (int) ($porEstado['recusada'] ?? 0);
        $encerradas = (int) ($porEstado['encerrada'] ?? 0);

        return response()->json([
            'total_candidaturas' => (int) $porEstado->sum(),

            'em_analise' => $emAnalise,

            'aguarda_diretor' => $aguardaDiretor,

            // Candidaturas ainda sem decisão final.
            'aguarda_decisao' => $emAnalise + $aguardaDiretor,

            'aprovadas' => $aprovadas,

            'recusadas' => $recusadas,

            'encerradas' => $encerradas,

            'estagiarios_ativos' => Estagio::query()
                ->where('estado', 'ativo')
                ->count(),

            'relatorios_atraso' => $this->relatoriosEmAtraso()->count(),

            'candidaturas_por_semana' => $this->candidaturasPorSemanaDoTrimestre(),

            'funil_candidaturas' => [
                [
                    'chave' => 'em_analise',
                    'etapa' => 'Em análise',
                    'total' => $emAnalise,
                ],
                [
                    'chave' => 'aguarda_diretor',
                    'etapa' => 'Aguarda decisão do diretor',
                    'total' => $aguardaDiretor,
                ],
                [
                    'chave' => 'aprovada',
                    'etapa' => 'Aprovadas',
                    'total' => $aprovadas,
                ],
                [
                    'chave' => 'recusada',
                    'etapa' => 'Recusadas',
                    'total' => $recusadas,
                ],
                [
                    'chave' => 'encerrada',
                    'etapa' => 'Encerradas',
                    'total' => $encerradas,
                ],
            ],

            'vagas_por_departamento' => $this->vagasPorDepartamento(),
        ]);
    }

    /**
     * Relatórios cujo prazo já passou e que continuam por entregar.
     *
     * @return Builder<Relatorio>
     */
    private function relatoriosEmAtraso()
    {
        return Relatorio::query()
            ->whereNull('enviado_em')
            ->whereNull('entrega_confirmada_em')
            ->whereNotNull('prazo_envio')
            ->where('prazo_envio', '<', now()->toDateString());
    }

    /**
     * Candidaturas recebidas por semana do trimestre corrente.
     *
     * O agrupamento é feito em PHP para funcionar igual em MySQL e em
     * SQLite (usado nos testes).
     *
     * @return array{
     *     trimestre: array{numero: int, ano: int, inicio: string, fim: string},
     *     semanas: array<int, array{semana: int, ano: int, inicio: string, fim: string, total: int}>
     * }
     */
    private function candidaturasPorSemanaDoTrimestre(): array
    {
        $trimestre = $this->limiteCandidaturas->trimestreDe();

        $datas = Candidatura::query()
            ->whereBetween('created_at', [
                $trimestre['inicio'],
                $trimestre['fim'],
            ])
            ->pluck('created_at');

        $semanas = [];

        $cursor = $trimestre['inicio']->copy()->startOfWeek();

        while ($cursor <= $trimestre['fim']) {
            $chave = $cursor->isoWeekYear().'-'.$cursor->isoWeek();

            $inicio = $cursor->greaterThan($trimestre['inicio'])
                ? $cursor->copy()
                : $trimestre['inicio']->copy();

            $fim = $cursor->copy()->endOfWeek();

            if ($fim->greaterThan($trimestre['fim'])) {
                $fim = $trimestre['fim']->copy();
            }

            $semanas[$chave] = [
                'semana' => $cursor->isoWeek(),
                'ano' => $cursor->isoWeekYear(),
                'inicio' => $inicio->toDateString(),
                'fim' => $fim->toDateString(),
                'total' => 0,
            ];

            $cursor = $cursor->addWeek();
        }

        foreach ($datas as $data) {
            $data = Carbon::parse($data);

            $chave = $data->isoWeekYear().'-'.$data->isoWeek();

            if (isset($semanas[$chave])) {
                $semanas[$chave]['total']++;
            }
        }

        return [
            'trimestre' => [
                'numero' => $trimestre['numero'],
                'ano' => $trimestre['ano'],
                'inicio' => $trimestre['inicio']->toDateString(),
                'fim' => $trimestre['fim']->toDateString(),
            ],
            'semanas' => array_values($semanas),
        ];
    }

    /**
     * Vagas definidas por departamento e estágios ativos que as ocupam.
     *
     * @return array{
     *     total_vagas: int,
     *     total_ocupadas: int,
     *     total_disponiveis: int,
     *     departamentos: array<int, array{
     *         id_departamento: int,
     *         nome: string,
     *         vagas: int|null,
     *         ocupadas: int,
     *         disponiveis: int|null,
     *         taxa_ocupacao: int|null
     *     }>
     * }
     */
    private function vagasPorDepartamento(): array
    {
        $ocupadas = DB::table('estagios as e')
            ->join(
                'candidaturas as c',
                'c.id_candidatura',
                '=',
                'e.id_candidatura'
            )
            ->where('e.estado', 'ativo')
            ->groupBy('c.id_departamento_selecionado')
            ->select(
                'c.id_departamento_selecionado as id_departamento',
                DB::raw('COUNT(*) as total')
            )
            ->pluck('total', 'id_departamento');

        $departamentos = Departamento::query()
            ->orderBy('nome')
            ->get(['id_departamento', 'nome', 'vagas'])
            ->map(function (Departamento $departamento) use ($ocupadas) {
                $totalOcupadas = (int) (
                    $ocupadas[$departamento->id_departamento] ?? 0
                );

                $vagas = $departamento->vagas;

                return [
                    'id_departamento' => $departamento->id_departamento,
                    'nome' => $departamento->nome,
                    'vagas' => $vagas,
                    'ocupadas' => $totalOcupadas,
                    'disponiveis' => $vagas === null
                        ? null
                        : max($vagas - $totalOcupadas, 0),
                    'taxa_ocupacao' => $vagas === null || $vagas === 0
                        ? null
                        : (int) round(($totalOcupadas / $vagas) * 100),
                ];
            })
            ->values();

        $comVagas = $departamentos->whereNotNull('vagas');

        return [
            'total_vagas' => (int) $comVagas->sum('vagas'),
            'total_ocupadas' => (int) $comVagas->sum('ocupadas'),
            'total_disponiveis' => (int) $comVagas->sum('disponiveis'),
            'departamentos' => $departamentos->all(),
        ];
    }

    /**
     * Lista todas as candidaturas.
     *
     * Permite pesquisa, filtros e paginação.
     */
    public function candidaturas(Request $request)
    {
        $dados = $request->validate([
            'pesquisa' => ['nullable', 'string', 'max:150'],

            'estado' => [
                'nullable',
                'in:em_analise,aguarda_diretor,aprovada,recusada,encerrada',
            ],

            'id_departamento' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'por_pagina' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
        ]);

        $query = Candidatura::with([
            'departamento',
            'documentos',
        ])->orderByDesc('created_at');

        /*
        |--------------------------------------------------------------------------
        | Pesquisa
        |--------------------------------------------------------------------------
        */
        if (! empty($dados['pesquisa'])) {
            $pesquisa = $dados['pesquisa'];

            $query->where(function ($q) use ($pesquisa) {
                $q->where('nome', 'like', "%{$pesquisa}%")
                    ->orWhere('email', 'like', "%{$pesquisa}%")
                    ->orWhere('curso', 'like', "%{$pesquisa}%")
                    ->orWhere(
                        'universidade',
                        'like',
                        "%{$pesquisa}%"
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filtro por estado
        |--------------------------------------------------------------------------
        |
        | Sem filtro, a área de candidaturas mostra apenas as que ainda não
        | foram aceites. Uma candidatura aprovada passa para a área de
        | estagiários (aceite ou já com estágio).
        |
        */
        if (! empty($dados['estado'])) {
            $query->where(
                'estado',
                $dados['estado']
            );
        } else {
            $query->whereIn('estado', [
                'em_analise',
                'aguarda_diretor',
                'recusada',
                'encerrada',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Filtro por direção
        |--------------------------------------------------------------------------
        */
        if (! empty($dados['id_departamento'])) {
            $query->where(
                'id_departamento_selecionado',
                $dados['id_departamento']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Paginação
        |--------------------------------------------------------------------------
        */
        return response()->json(
            $query->paginate(
                $dados['por_pagina'] ?? 15
            )
        );
    }

    /**
     * Exibe os detalhes de uma candidatura.
     */
    public function show(int $id)
    {
        $candidatura = Candidatura::with([
            'departamento',
            'documentos',
        ])
            ->where('id_candidatura', $id)
            ->first();

        if (! $candidatura) {
            return response()->json([
                'message' => 'Candidatura não encontrada.',
            ], 404);
        }

        return response()->json($candidatura);
    }

    /**
     * Encaminha uma candidatura ao coordenador
     * da direção selecionada pelo candidato.
     *
     * O estado da candidatura permanece em_analise.
     */
    public function encaminhar(Request $request, int $id)
    {
        $dados = $request->validate([
            'id_departamento' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        return DB::transaction(function () use (

            $id,
            $dados
        ) {
            $candidatura = Candidatura::query()
                ->where('id_candidatura', $id)
                ->lockForUpdate()
                ->first();

            if (! $candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada.',
                ], 404);
            }

            if ($candidatura->estado !== 'em_analise') {
                return response()->json([
                    'message' => 'Só é possível encaminhar candidaturas em análise.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Verificar se já foi encaminhada
            |--------------------------------------------------------------------------
            */
            if (
                $candidatura->id_coordenador !== null ||
                $candidatura->encaminhada_em !== null
            ) {
                return response()->json([
                    'message' => 'Esta candidatura já foi encaminhada.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Localizar coordenador ativo da direção escolhida
            |--------------------------------------------------------------------------
            */
            $coordenador = User::query()
                ->where('tipo_utilizador', 'coordenador')
                ->where('ativo', true)
                ->whereHas('perfil', function ($q) use ($dados) {
                    $q->where(
                        'id_departamento',
                        $dados['id_departamento']
                    );
                })
                ->with('perfil')
                ->first();

            if (! $coordenador) {
                return response()->json([
                    'message' => 'Não existe coordenador activo para a direção seleccionada.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Localizar o diretor ativo da mesma direção
            |--------------------------------------------------------------------------
            */
            $diretor = User::query()
                ->where('tipo_utilizador', 'diretor')
                ->where('ativo', true)
                ->where(function ($q) use ($dados) {
                    $q->whereHas('perfil', function ($perfil) use ($dados) {
                        $perfil->where(
                            'id_departamento',
                            $dados['id_departamento']
                        );
                    })->orWhere(
                        'id_departamento',
                        $dados['id_departamento']
                    );
                })
                ->with('perfil')
                ->first();

            /*
            |--------------------------------------------------------------------------
            | Registar o encaminhamento
            |--------------------------------------------------------------------------
            */
            $candidatura->update([
                'id_coordenador' => $coordenador->id_utilizador,
                'id_diretor' => $diretor?->id_utilizador,
                'id_departamento_selecionado' => (int) $dados['id_departamento'],
                'encaminhada_em' => now(),
            ]);

            $this->notificacoes->paraUtilizadores(
                collect([$coordenador, $diretor])->filter(),
                'Candidatura encaminhada',
                "A candidatura de {$candidatura->nome} foi encaminhada para análise."
            );

            return response()->json([
                'message' => $diretor
                    ? 'Candidatura encaminhada ao coordenador e ao diretor com sucesso.'
                    : 'Candidatura encaminhada ao coordenador. Não existe diretor ativo nesta direção.',

                'candidatura' => $candidatura->fresh()->load([
                    'departamento',
                    'documentos',
                ]),

                'coordenador' => [
                    'id_utilizador' => $coordenador->id_utilizador,
                    'nome' => $coordenador->nome,
                    'email' => $coordenador->email,
                    'id_departamento' => (int) $dados['id_departamento'],
                ],

                'diretor' => $diretor
                    ? [
                        'id_utilizador' => $diretor->id_utilizador,
                        'nome' => $diretor->nome,
                        'email' => $diretor->email,
                        'id_departamento' => (int) $dados['id_departamento'],
                    ]
                    : null,
            ]);
        });
    }

    /**
     * A decisão final pertence ao diretor.
     *
     * Mantido para evitar erros nas rotas antigas,
     * mas não permite ao administrador aprovar.
     */
    public function aprovar(Request $request, int $id)
    {
        return response()->json([
            'message' => 'O administrador não pode aprovar candidaturas. A decisão final pertence ao diretor.',
        ], 403);
    }

    /**
     * Corrige a direção ou a duração prevista de uma candidatura
     * ainda por decidir.
     *
     * Quando a direção muda, a candidatura volta a `em_analise` e o
     * encaminhamento anterior é limpo, para ser reencaminhada à direção
     * correta.
     */
    public function atualizarCandidatura(Request $request, int $id)
    {
        $dados = $request->validate([
            'id_departamento' => [
                'nullable',
                'required_without:duracao_meses',
                'integer',
                'exists:departamentos,id_departamento',
            ],

            'duracao_meses' => [
                'nullable',
                'required_without:id_departamento',
                'integer',
                'min:1',
                'max:60',
            ],
        ]);

        return DB::transaction(function () use ($request, $id, $dados) {
            $candidatura = Candidatura::query()
                ->where('id_candidatura', $id)
                ->lockForUpdate()
                ->first();

            if (! $candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada.',
                ], 404);
            }

            if (! in_array($candidatura->estado, [
                'em_analise',
                'aguarda_diretor',
            ], true)) {
                return response()->json([
                    'message' => 'Só é possível alterar candidaturas em análise ou a aguardar decisão do diretor.',
                ], 422);
            }

            $alteracoes = [];

            $mudouDepartamento = isset($dados['id_departamento'])
                && (int) $dados['id_departamento']
                    !== (int) $candidatura->id_departamento_selecionado;

            if (isset($dados['id_departamento'])) {
                $alteracoes['id_departamento_selecionado'] = (int) $dados['id_departamento'];
            }

            if (isset($dados['duracao_meses'])) {
                $alteracoes['duracao_meses'] = (int) $dados['duracao_meses'];
            }

            if ($mudouDepartamento) {
                $alteracoes = [
                    ...$alteracoes,
                    'estado' => 'em_analise',
                    'id_coordenador' => null,
                    'id_diretor' => null,
                    'encaminhada_em' => null,
                    'parecer_coordenador' => null,
                    'parecer_coordenador_motivo' => null,
                    'parecer_coordenador_em' => null,
                    'decisao_diretor' => null,
                    'decisao_diretor_motivo' => null,
                    'decisao_diretor_em' => null,
                    'motivo_recusa' => null,
                ];
            }

            $estadoAnterior = $candidatura->estado;

            $candidatura->update($alteracoes);

            if ($mudouDepartamento && $estadoAnterior !== 'em_analise') {
                HistoricoEstado::create([
                    'id_candidatura' => $candidatura->id_candidatura,
                    'estado_anterior' => $estadoAnterior,
                    'estado_novo' => 'em_analise',
                    'motivo' => 'Direção alterada pelo administrador.',
                    'id_utilizador' => $request->user()->id_utilizador,
                ]);
            }

            return response()->json([
                'message' => 'Candidatura atualizada com sucesso.',

                'candidatura' => $candidatura
                    ->fresh()
                    ->load(['departamento', 'documentos']),
            ]);
        });
    }

    /**
     * Recusa uma candidatura que ainda não foi decidida.
     *
     * A justificação é obrigatória e fica registada no histórico.
     */
    public function recusar(Request $request, int $id)
    {
        $dados = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        return DB::transaction(function () use (
            $request,
            $id,
            $dados
        ) {
            $candidatura = Candidatura::query()
                ->where('id_candidatura', $id)
                ->lockForUpdate()
                ->first();

            if (! $candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada.',
                ], 404);
            }

            if (! in_array($candidatura->estado, [
                'em_analise',
                'aguarda_diretor',
            ], true)) {
                return response()->json([
                    'message' => 'Só é possível recusar candidaturas em análise ou a aguardar decisão do diretor.',
                ], 422);
            }

            $estadoAnterior = $candidatura->estado;

            $candidatura->update([
                'estado' => 'recusada',
                'motivo_recusa' => $dados['motivo'],
                'data_decisao' => now(),
            ]);

            HistoricoEstado::create([
                'id_candidatura' => $candidatura->id_candidatura,
                'estado_anterior' => $estadoAnterior,
                'estado_novo' => 'recusada',
                'motivo' => $dados['motivo'],
                'id_utilizador' => $request->user()->id_utilizador,
            ]);

            $this->notificacoes->paraCandidato(
                $candidatura,
                'Candidatura recusada',
                "A tua candidatura foi recusada pelo administrador. Motivo: {$dados['motivo']}."
            );

            return response()->json([
                'message' => 'Candidatura recusada.',

                'candidatura' => $candidatura
                    ->fresh()
                    ->load(['departamento', 'documentos']),
            ]);
        });
    }

    /**
     * Lista os estagiários e os estágios.
     */
    public function estagiarios(Request $request)
    {
        $query = DB::table('candidaturas as c')
            ->leftJoin(
                'estagios as e',
                'e.id_candidatura',
                '=',
                'c.id_candidatura'
            )
            ->leftJoin(
                'utilizadores as u',
                'u.id_utilizador',
                '=',
                'c.id_utilizador'
            )
            ->leftJoin(
                'departamentos as d',
                'd.id_departamento',
                '=',
                'c.id_departamento_selecionado'
            )
            ->where(function ($q) {
                $q->where('c.estado', 'aprovada')
                    ->orWhereNotNull('e.id_estagio');
            })
            ->select(
                'e.id_estagio',
                'c.id_candidatura',
                'e.data_inicio',
                'e.duracao_meses',
                'e.data_fim_prevista',
                'e.data_fim_real',
                'e.dia_limite_relatorio',
                'e.motivo_suspensao',
                DB::raw(
                    'COALESCE(e.estado, c.estado) as estado_estagio'
                ),
                'u.id_utilizador as id_estagiario',
                DB::raw('COALESCE(u.nome, c.nome) as nome'),
                DB::raw('COALESCE(u.email, c.email) as email'),
                DB::raw(
                    'COALESCE(u.telefone, c.contacto) as telefone'
                ),
                'c.curso',
                'c.universidade',
                'd.nome as departamento'
            );

        /*
        |--------------------------------------------------------------------------
        | Pesquisa
        |--------------------------------------------------------------------------
        */
        if ($request->filled('pesquisa')) {
            $pesquisa = $request->input('pesquisa');

            $query->where(function ($q) use ($pesquisa) {
                $q->where('c.nome', 'like', "%{$pesquisa}%")
                    ->orWhere('c.email', 'like', "%{$pesquisa}%")
                    ->orWhere('c.curso', 'like', "%{$pesquisa}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filtro por estado do estágio
        |--------------------------------------------------------------------------
        */
        if ($request->filled('estado')) {
            $estado = $request->input('estado');

            if ($estado === 'aprovada') {
                $query->where('c.estado', 'aprovada')
                    ->whereNull('e.id_estagio');
            } else {
                $query->where('e.estado', $estado);
            }
        }

        return response()->json(
            $query->orderByDesc('c.data_decisao')
                ->paginate(
                    min(
                        max($request->integer('por_pagina', 15), 1),
                        50
                    )
                )
        );
    }

    /**
     * Número de estagiários registados por direção/departamento.
     *
     * Serve o dashboard em "escada" do painel administrativo: o
     * departamento com mais estagiários aparece com a barra mais alta.
     *
     * Cada barra é decomposta por estado do estágio, para o
     * administrador ver logo quantos estão ativos, suspensos,
     * concluídos, cancelados ou ainda por iniciar.
     */
    public function estagiariosPorDepartamento()
    {
        /*
        |--------------------------------------------------------------------------
        | Estagiários registados no sistema
        |--------------------------------------------------------------------------
        |
        | A mesma regra usada na listagem de estagiários: candidaturas
        | aprovadas ou que já tenham um estágio criado.
        |
        */
        $linhas = DB::table('candidaturas as c')
            ->leftJoin('estagios as e', 'e.id_candidatura', '=', 'c.id_candidatura')
            ->where(function ($q) {
                $q->where('c.estado', 'aprovada')
                    ->orWhereNotNull('e.id_estagio');
            })
            ->select(
                'c.id_departamento_selecionado',
                DB::raw("COALESCE(e.estado, 'sem_estagio') as estado_estagio"),
                DB::raw('COUNT(DISTINCT c.id_candidatura) as total')
            )
            ->groupBy(
                'c.id_departamento_selecionado',
                'estado_estagio'
            )
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Estrutura auxiliar
        |--------------------------------------------------------------------------
        |
        | [chave_departamento][estado] => total de estagiários
        |
        */
        $contagem = [];

        $estados = [
            'sem_estagio',
            'ativo',
            'suspenso',
            'concluido',
            'cancelado',
        ];

        foreach ($linhas as $linha) {
            $chave = $linha->id_departamento_selecionado
                ?? 'sem_departamento';

            $estado = $linha->estado_estagio;

            if (! in_array($estado, $estados, true)) {
                $estados[] = $estado;
            }

            $contagem[$chave][$estado] = (int) $linha->total;
        }

        /*
        |--------------------------------------------------------------------------
        | Soma por estado (para a legenda da escada)
        |--------------------------------------------------------------------------
        */
        $totaisEstado = array_fill_keys($estados, 0);

        foreach ($contagem as $porEstado) {
            foreach ($porEstado as $estado => $total) {
                $totaisEstado[$estado] += $total;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Departamentos/direções registados pelo administrador
        |--------------------------------------------------------------------------
        |
        | Inclui os que ainda não têm estagiários, para continuarem a
        | fazer parte da escada (no fundo, com zero).
        |
        */
        $departamentos = Departamento::query()
            ->orderBy('nome')
            ->get(['id_departamento', 'nome'])
            ->map(function (Departamento $departamento) use (
                $contagem,
                $estados
            ) {
                $porEstado = $contagem[
                    $departamento->id_departamento
                ] ?? [];

                return $this->comPorEstadoDepartamento(
                    [
                        'id_departamento' => $departamento->id_departamento,
                        'nome' => $departamento->nome,
                    ],
                    $porEstado,
                    $estados
                );
            });

        /*
        |--------------------------------------------------------------------------
        | Estagiários sem departamento associado
        |--------------------------------------------------------------------------
        */
        if (! empty($contagem['sem_departamento'])) {
            $departamentos->push(
                $this->comPorEstadoDepartamento(
                    [
                        'id_departamento' => null,
                        'nome' => 'Sem departamento atribuído',
                    ],
                    $contagem['sem_departamento'],
                    $estados
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Escada: do maior para o menor
        |--------------------------------------------------------------------------
        */
        $departamentos = $departamentos
            ->sortBy([
                ['total_estagiarios', 'desc'],
                ['nome', 'asc'],
            ])
            ->values();

        return response()->json([
            'total_estagiarios' => $departamentos->sum('total_estagiarios'),
            'estados' => $estados,
            'totais_estado' => $totaisEstado,
            'departamentos' => $departamentos,
        ]);
    }

    /**
     * Junta a contagem por estado e o total a um departamento.
     */
    private function comPorEstadoDepartamento(
        array $departamento,
        array $porEstado,
        array $estados
    ): array {
        $completo = [];

        foreach ($estados as $estado) {
            $completo[$estado] = (int) ($porEstado[$estado] ?? 0);
        }

        return array_merge($departamento, [
            'total_estagiarios' => array_sum($completo),
            'por_estado' => $completo,
        ]);
    }

    public function atualizarEstadoEstagio(Request $request, int $idCandidatura)
    {
        $dados = $request->validate([
            'estado' => 'required|in:ativo,suspenso,concluido,cancelado',
            'data_inicio' => 'required|date',
            'duracao_meses' => 'nullable|integer|min:1',
            'motivo_suspensao' => 'nullable|string|required_if:estado,suspenso|max:5000',
            'dia_limite_relatorio' => 'nullable|integer|min:1|max:28',
        ]);

        return DB::transaction(function () use ($dados, $idCandidatura) {
            $candidatura = DB::table('candidaturas')
                ->where('id_candidatura', $idCandidatura)
                ->lockForUpdate()
                ->first();

            if (! $candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada.',
                ], 404);
            }

            $estagio = DB::table('estagios')
                ->where('id_candidatura', $idCandidatura)
                ->lockForUpdate()
                ->first();

            if (! $estagio && $candidatura->estado !== 'aprovada') {
                return response()->json([
                    'message' => 'Só é possível iniciar um estágio para uma candidatura aprovada.',
                ], 422);
            }

            // Resolver a conta do estagiário no servidor.
            $utilizador = DB::table('utilizadores')
                ->where('email', $candidatura->email)
                ->first();

            if (! $utilizador) {
                return response()->json([
                    'message' => 'Esta candidatura ainda não tem uma conta de estagiário. Cria a conta antes de iniciar o estágio.',
                ], 422);
            }

            $inicio = $dados['data_inicio']
                ?? ($estagio->data_inicio ?? null);

            $duracaoMeses = (int) (
                $dados['duracao_meses']
                ?? ($estagio->duracao_meses ?? 6)
            );

            if (! $inicio) {
                return response()->json([
                    'message' => 'Indica a data de início do estágio.',
                ], 422);
            }

            if ($duracaoMeses < 1) {
                return response()->json([
                    'message' => 'A duração deve ser de pelo menos um mês.',
                ], 422);
            }

            // O servidor calcula a data final; não confia na data
            // prevista enviada pelo navegador.
            $fimPrevisto = Carbon::parse($inicio)
                ->addMonthsNoOverflow($duracaoMeses)
                ->toDateString();

            $agora = now();

            $valores = [
                'id_candidatura' => $idCandidatura,
                'id_estagiario' => $utilizador->id_utilizador,
                'data_inicio' => $inicio,
                'duracao_meses' => $duracaoMeses,
                'data_fim_prevista' => $fimPrevisto,
                'dia_limite_relatorio' => (int) (
                    $dados['dia_limite_relatorio']
                    ?? ($estagio->dia_limite_relatorio ?? 5)
                ),
                'estado' => $dados['estado'],
                'motivo_suspensao' => $dados['estado'] === 'suspenso'
                    ? trim($dados['motivo_suspensao'] ?? '')
                    : null,
                'data_fim_real' => $dados['estado'] === 'concluido'
                    ? ($estagio->data_fim_real ?? $agora->toDateString())
                    : ($estagio->data_fim_real ?? null),
                'updated_at' => $agora,
            ];

            if ($estagio) {
                DB::table('estagios')
                    ->where('id_estagio', $estagio->id_estagio)
                    ->update($valores);

                $idEstagio = $estagio->id_estagio;
            } else {
                $valores['created_at'] = $agora;

                $idEstagio = DB::table('estagios')->insertGetId(
                    $valores,
                    'id_estagio'
                );
            }

            return response()->json([
                'message' => 'Estado do estágio atualizado com sucesso.',
                'data' => DB::table('estagios')
                    ->where('id_estagio', $idEstagio)
                    ->first(),
            ]);
        });
    }
}
