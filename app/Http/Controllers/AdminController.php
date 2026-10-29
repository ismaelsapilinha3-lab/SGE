<?php

namespace App\Http\Controllers;

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminController extends Controller
{
    /**
     * Dashboard administrativo.
     */
    public function dashboard()
    {
        return response()->json([
            'total_candidaturas' => Candidatura::count(),

            'em_analise' => Candidatura::where(
                'estado',
                'em_analise'
            )->count(),

            'aprovadas' => Candidatura::where(
                'estado',
                'aprovada'
            )->count(),

            'recusadas' => Candidatura::where(
                'estado',
                'recusada'
            )->count(),
        ]);
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
                'in:em_analise,aprovada,recusada',
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
        */
        if (! empty($dados['estado'])) {
            $query->where(
                'estado',
                $dados['estado']
            );
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
            'entrevista.administrador:id_utilizador,nome,email',
            'entrevista.supervisorRepresentante:id_utilizador,nome,email',
        ])
            ->where('id_candidatura', $id)
            ->first();

        if (! $candidatura) {
            return response()->json([
                'message' => 'Candidatura não encontrada.',
            ], 404);
        }

        Gate::authorize('view', $candidatura);

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

            Gate::authorize('encaminhar', $candidatura);

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
            | Registar o encaminhamento
            |--------------------------------------------------------------------------
            */
            $candidatura->update([
                'id_coordenador' => $coordenador->id_utilizador,
                'id_departamento_selecionado' => (int) $dados['id_departamento'],
                'encaminhada_em' => now(),
            ]);

            return response()->json([
                'message' => 'Candidatura encaminhada ao coordenador com sucesso.',

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
            ]);
        });
    }

    /**
     * A decisão final pertence ao coordenador.
     *
     * Mantido para evitar erros nas rotas antigas,
     * mas não permite ao administrador aprovar.
     */
    public function aprovar(Request $request, int $id)
    {
        return response()->json([
            'message' => 'O administrador não pode aprovar candidaturas. Encaminhe a candidatura ao coordenador responsável.',
        ], 403);
    }

    /**
     * A decisão final pertence ao coordenador.
     *
     * Mantido para evitar erros nas rotas antigas,
     * mas não permite ao administrador recusar.
     */
    public function recusar(Request $request, int $id)
    {
        return response()->json([
            'message' => 'O administrador não pode recusar candidaturas. Encaminhe a candidatura ao coordenador responsável.',
        ], 403);
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
