<?php

namespace App\Http\Controllers;

use App\Models\Assiduidade;
use App\Models\Avaliacao;
use App\Models\DocumentoEstagio;
use App\Models\Estagio;
use App\Models\HistoricoEstado;
use App\Models\Notificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupervisorController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Painel do supervisor
    |--------------------------------------------------------------------------
    |
    | Visão de leitura: o supervisor acompanha os estágios em curso que lhe
    | foram atribuídos pela coordenação. Não altera estados nem aprova
    | candidaturas — essas decisões continuam com a coordenação e a direcção.
    |
    */

    public function dashboard(Request $request)
    {
        $utilizador = $request->user();

        $estagios = DB::table('estagios as e')
            ->join(
                'candidaturas as c',
                'c.id_candidatura',
                '=',
                'e.id_candidatura'
            )
            ->leftJoin(
                'departamentos as d',
                'd.id_departamento',
                '=',
                'c.id_departamento_selecionado'
            )
            ->leftJoin(
                'utilizadores as u',
                'u.id_utilizador',
                '=',
                'c.id_utilizador'
            )
            /*
            | Só os estágios em que o supervisor tem um vínculo ativo (não
            | removido). Sem esta junção o painel mostrava todos os estágios.
            */
            ->join(
                'estagio_supervisores as es',
                function ($join) use ($utilizador) {
                    $join->on(
                        'es.id_estagio',
                        '=',
                        'e.id_estagio'
                    )
                        ->where(
                            'es.id_supervisor',
                            '=',
                            $utilizador->id_utilizador
                        )
                        ->whereNull('es.removido_em');
                }
            )
            ->whereIn('e.estado', ['ativo', 'suspenso'])
            ->select(
                'e.id_estagio',
                'e.data_inicio',
                'e.data_fim_prevista',
                'e.estado as estado_estagio',
                'c.nome as nome_candidatura',
                'c.email as email_candidatura',
                'd.nome as nome_departamento',
                DB::raw('COALESCE(u.nome, c.nome) as nome_estagiario')
            )
            ->orderBy('e.data_inicio', 'desc')
            ->get();

        return response()->json([
            'message' => 'Painel do supervisor carregado com sucesso.',
            'utilizador' => [
                'id_utilizador' => $utilizador->id_utilizador,
                'nome' => $utilizador->nome,
                'email' => $utilizador->email,
                'tipo_utilizador' => $utilizador->tipo_utilizador,
            ],
            'resumo' => [
                'estagios_ativos' => $estagios
                    ->where('estado_estagio', 'ativo')
                    ->count(),
            ],
            'estagios' => $estagios->values(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Listar os estágios atribuídos ao supervisor
    |--------------------------------------------------------------------------
    */

    public function estagios(Request $request)
    {
        $utilizador = $request->user();

        $estagios = $this->estagiosDoSupervisor($utilizador)
            ->with([
                'estagiario',
                'candidatura.departamento',
                'planos' => fn ($q) => $q->orderByDesc('id_plano_estagio'),
            ])
            ->orderByDesc('data_inicio')
            ->get();

        return response()->json([
            'estagios' => $estagios,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Consultar o plano de estágio
    |--------------------------------------------------------------------------
    */

    public function mostrarPlano(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        $plano = $estagio->planoVigente();

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'plano' => $plano,

            'historico' => $plano
                ? $plano->historico()
                    ->orderByDesc('id_plano_historico')
                    ->get()
                : [],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Submeter o plano de estágio
    |--------------------------------------------------------------------------
    |
    | Cria o plano, ou reaproveita o existente:
    |  - recusado  -> guarda cópia do anterior no histórico e volta a submeter;
    |  - submetido -> refina o conteúdo enquanto aguarda decisão;
    |  - aprovado  -> o plano antigo fica 'substituido' e nasce um novo.
    |
    */

    public function submeterPlano(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        if (! $estagio->estaAtivo()) {
            return response()->json([
                'message' => 'Só é possível submeter o plano de um estágio ativo.',
            ], 422);
        }

        $dados = $request->validate([
            'objetivos' => [
                'required',
                'string',
                'max:5000',
            ],

            'atividades_previstas' => [
                'required',
                'string',
                'max:5000',
            ],

            'cronograma' => [
                'required',
                'string',
                'max:5000',
            ],

            'data_inicio' => [
                'required',
                'date',
            ],

            'data_fim' => [
                'required',
                'date',
                'after_or_equal:data_inicio',
            ],
        ]);

        return DB::transaction(function () use (
            $request,
            $estagio,
            $dados
        ) {

            $utilizador = $request->user();

            $plano = $estagio->planoVigente();

            $criado = false;

            if ($plano && $plano->estaAprovado()) {
                $plano->update([
                    'estado' => 'substituido',
                ]);

                $plano = null;
            }

            $conteudo = [
                'objetivos' => $dados['objetivos'],

                'atividades_previstas' => $dados['atividades_previstas'],

                'cronograma' => $dados['cronograma'],

                'data_inicio' => $dados['data_inicio'],

                'data_fim' => $dados['data_fim'],
            ];

            if (! $plano) {
                $plano = $estagio->planos()->create(
                    $conteudo + [
                        'id_supervisor' => $utilizador->id_utilizador,
                        'estado' => 'submetido',
                    ]
                );

                $criado = true;
            } elseif ($plano->estado === 'recusado') {
                $plano->registarHistorico($utilizador->id_utilizador);

                $plano->update($conteudo + [
                    'estado' => 'submetido',
                    'motivo_recusa' => null,
                    'id_coordenador' => null,
                    'data_decisao' => null,
                ]);
            } else {
                $plano->update($conteudo);
            }

            Notificacao::paraUtilizador(
                $estagio->coordenadorResponsavel(),
                'Plano de estágio submetido',
                "O supervisor submeteu o plano do estágio #{$estagio->id_estagio} para a tua aprovação."
            );

            return response()->json([
                'message' => 'Plano de estágio submetido com sucesso.',

                'plano' => $plano
                    ->fresh()
                    ->load('historico'),
            ], $criado ? 201 : 200);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Documentos do estágio (contrato)
    |--------------------------------------------------------------------------
    */

    public function documentos(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'documentos' => $estagio->documentos()
                ->orderByDesc('versao')
                ->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Validar um documento do estágio
    |--------------------------------------------------------------------------
    */

    public function validarDocumento(
        Request $request,
        int $id,
        DocumentoEstagio $documento
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        $this->garantirDocumentoDoEstagio($documento, $estagio);

        if ($estagio->estaTerminado()) {
            return response()->json([
                'message' => 'Este estágio já terminou e não aceita alterações.',
            ], 422);
        }

        if ($documento->estaValidado()) {
            return response()->json([
                'message' => 'Este documento já está validado.',
            ], 422);
        }

        return DB::transaction(function () use (
            $request,
            $estagio,
            $documento
        ) {

            $documentoBloqueado = DocumentoEstagio::where(
                'id_documento_estagio',
                $documento->id_documento_estagio
            )
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = $documentoBloqueado->estado;

            $documentoBloqueado->update([
                'estado' => 'validado',
                'descricao' => null,
            ]);

            HistoricoEstado::create([
                'id_documento_estagio' => $documentoBloqueado
                    ->id_documento_estagio,

                'estado_anterior' => $estadoAnterior,

                'estado_novo' => 'validado',

                'motivo' => null,

                'id_utilizador' => $request->user()->id_utilizador,
            ]);

            Notificacao::paraUtilizador(
                $estagio->id_estagiario,
                'Contrato validado',
                "O contrato (versão {$documentoBloqueado->versao}) foi validado pelo supervisor."
            );

            return response()->json([
                'message' => 'Documento validado com sucesso.',

                'documento' => $documentoBloqueado->fresh(),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Rejeitar um documento do estágio
    |--------------------------------------------------------------------------
    |
    | O motivo fica no documento (descricao) e no histórico de estados.
    |
    */

    public function rejeitarDocumento(
        Request $request,
        int $id,
        DocumentoEstagio $documento
    ) {

        $dados = $request->validate([
            'descricao' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ]);

        $estagio = $this->estagioDoSupervisor($request, $id);

        $this->garantirDocumentoDoEstagio($documento, $estagio);

        if ($estagio->estaTerminado()) {
            return response()->json([
                'message' => 'Este estágio já terminou e não aceita alterações.',
            ], 422);
        }

        if ($documento->estaRejeitado()) {
            return response()->json([
                'message' => 'Este documento já foi rejeitado.',
            ], 422);
        }

        return DB::transaction(function () use (
            $request,
            $estagio,
            $documento,
            $dados
        ) {

            $documentoBloqueado = DocumentoEstagio::where(
                'id_documento_estagio',
                $documento->id_documento_estagio
            )
                ->lockForUpdate()
                ->firstOrFail();

            $estadoAnterior = $documentoBloqueado->estado;

            $documentoBloqueado->update([
                'estado' => 'rejeitado',
                'descricao' => $dados['descricao'],
            ]);

            HistoricoEstado::create([
                'id_documento_estagio' => $documentoBloqueado
                    ->id_documento_estagio,

                'estado_anterior' => $estadoAnterior,

                'estado_novo' => 'rejeitado',

                'motivo' => $dados['descricao'],

                'id_utilizador' => $request->user()->id_utilizador,
            ]);

            Notificacao::paraUtilizador(
                $estagio->id_estagiario,
                'Contrato rejeitado',
                "O contrato (versão {$documentoBloqueado->versao}) foi rejeitado. Motivo: {$dados['descricao']}."
            );

            return response()->json([
                'message' => 'Documento rejeitado.',

                'documento' => $documentoBloqueado->fresh(),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relatórios submetidos pelo estagiário
    |--------------------------------------------------------------------------
    |
    | Apenas os relatórios já submetidos; os rascunhos pertencem ao estagiário.
    |
    */

    public function relatorios(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'relatorios' => $estagio->relatorios()
                ->whereNotNull('submetido_em')
                ->orderByDesc('periodo_referencia')
                ->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Assiduidade do estágio
    |--------------------------------------------------------------------------
    |
    | O supervisor lança a assiduidade de cada dia. Existe no máximo um
    | registo por dia (id_estagio + data), pelo que lançar duas vezes o
    | mesmo dia atualiza o registo existente.
    |
    */

    public function assiduidades(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        $dados = $request->validate([
            'mes' => [
                'nullable',
                'date_format:Y-m',
            ],

            'estado' => [
                'nullable',
                'in:presente,atraso,falta,falta_justificada',
            ],

            'justificacao_estado' => [
                'nullable',
                'in:pendente,aprovada,recusada',
            ],
        ]);

        $query = $estagio->assiduidades()
            ->orderByDesc('data');

        if (! empty($dados['mes'])) {
            $query->whereYear('data', substr($dados['mes'], 0, 4))
                ->whereMonth('data', substr($dados['mes'], 5, 2));
        }

        if (! empty($dados['estado'])) {
            $query->where('estado', $dados['estado']);
        }

        if (! empty($dados['justificacao_estado'])) {
            $query->where(
                'justificacao_estado',
                $dados['justificacao_estado']
            );
        }

        $todas = $estagio->assiduidades()->get();

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'assiduidades' => $query->get(),

            'resumo' => [
                'presente' => $todas->where('estado', 'presente')->count(),

                'atraso' => $todas->where('estado', 'atraso')->count(),

                'falta' => $todas->where('estado', 'falta')->count(),

                'falta_justificada' => $todas
                    ->where('estado', 'falta_justificada')
                    ->count(),

                'justificacoes_pendentes' => $todas
                    ->where('justificacao_estado', 'pendente')
                    ->count(),

                'total' => $todas->count(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Registar ou atualizar a assiduidade de um dia
    |--------------------------------------------------------------------------
    */

    public function registarAssiduidade(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        if (! $estagio->aceitaRegistos()) {
            return response()->json([
                'message' => 'Só é possível registar assiduidade de um estágio ativo.',
            ], 422);
        }

        $dados = $request->validate([
            'data' => [
                'required',
                'date',
            ],

            'estado' => [
                'required',
                'in:presente,atraso,falta,falta_justificada',
            ],

            'hora_entrada' => [
                'nullable',
                'date_format:H:i',
            ],

            'hora_saida' => [
                'nullable',
                'date_format:H:i',
                'after_or_equal:hora_entrada',
            ],

            'justificacao' => [
                'required_if:estado,falta_justificada',
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        if (! $estagio->contemData($dados['data'])) {
            return response()->json([
                'message' => 'A data indicada está fora do período do estágio.',
            ], 422);
        }

        $falta = in_array(
            $dados['estado'],
            ['falta', 'falta_justificada'],
            true
        );

        $justificada = $dados['estado'] === 'falta_justificada';

        /*
         * Ao marcar falta_justificada o supervisor assume a justificação
         * como válida; em qualquer outro estado o processo de justificação
         * é reiniciado.
         */
        $atributos = [
            'id_supervisor' => $request->user()->id_utilizador,

            'estado' => $dados['estado'],

            'hora_entrada' => $falta
                ? null
                : ($dados['hora_entrada'] ?? null),

            'hora_saida' => $falta
                ? null
                : ($dados['hora_saida'] ?? null),

            'justificacao' => $justificada
                ? ($dados['justificacao'] ?? null)
                : null,

            'justificacao_estado' => $justificada
                ? 'aprovada'
                : null,

            'motivo_recusa' => null,

            'id_supervisor_justificacao' => $justificada
                ? $request->user()->id_utilizador
                : null,

            'justificacao_submetida_em' => $justificada
                ? now()
                : null,

            'justificacao_decidida_em' => $justificada
                ? now()
                : null,
        ];

        $criado = false;

        $assiduidade = DB::transaction(function () use (
            $estagio,
            $dados,
            $atributos,
            &$criado
        ) {

            $assiduidade = $estagio->assiduidades()
                ->whereDate('data', $dados['data'])
                ->lockForUpdate()
                ->first();

            if ($assiduidade) {
                $assiduidade->update($atributos);

                return $assiduidade;
            }

            $criado = true;

            return $estagio->assiduidades()->create(
                $atributos + ['data' => $dados['data']]
            );
        });

        return response()->json([
            'message' => $criado
                ? 'Assiduidade registada com sucesso.'
                : 'Assiduidade atualizada com sucesso.',

            'assiduidade' => $assiduidade->fresh(),
        ], $criado ? 201 : 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Remover um registo de assiduidade
    |--------------------------------------------------------------------------
    */

    public function removerAssiduidade(
        Request $request,
        int $id,
        Assiduidade $assiduidade
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        $this->garantirAssiduidadeDoEstagio($assiduidade, $estagio);

        if (! $estagio->aceitaRegistos()) {
            return response()->json([
                'message' => 'Só é possível remover assiduidade de um estágio ativo.',
            ], 422);
        }

        $assiduidade->delete();

        return response()->json([
            'message' => 'Registo de assiduidade removido com sucesso.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Atividades registadas pelo estagiário
    |--------------------------------------------------------------------------
    */

    public function atividades(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'atividades' => $estagio->atividades()
                ->orderByDesc('data')
                ->orderByDesc('id_atividade')
                ->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Aprovar a justificação de uma falta
    |--------------------------------------------------------------------------
    */

    public function aprovarJustificacao(
        Request $request,
        int $id,
        Assiduidade $assiduidade
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        $this->garantirAssiduidadeDoEstagio($assiduidade, $estagio);

        if (! $estagio->aceitaRegistos()) {
            return response()->json([
                'message' => 'Só é possível decidir justificações de um estágio ativo.',
            ], 422);
        }

        return DB::transaction(function () use ($request, $estagio, $assiduidade) {

            $bloqueada = Assiduidade::where(
                'id_assiduidade',
                $assiduidade->id_assiduidade
            )
                ->lockForUpdate()
                ->firstOrFail();

            if (! $bloqueada->temJustificacaoPendente()) {
                return response()->json([
                    'message' => 'Não há justificação pendente para aprovar.',
                ], 422);
            }

            $bloqueada->update([
                'estado' => 'falta_justificada',

                'justificacao_estado' => 'aprovada',

                'motivo_recusa' => null,

                'id_supervisor_justificacao' => $request
                    ->user()
                    ->id_utilizador,

                'justificacao_decidida_em' => now(),
            ]);

            Notificacao::paraUtilizador(
                $estagio->id_estagiario,
                'Justificação aprovada',
                'A tua justificação para a falta de '
                    .$bloqueada->data->format('d/m/Y')
                    .' foi aprovada.'
            );

            return response()->json([
                'message' => 'Justificação aprovada com sucesso.',

                'assiduidade' => $bloqueada->fresh(),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Recusar a justificação de uma falta
    |--------------------------------------------------------------------------
    */

    public function recusarJustificacao(
        Request $request,
        int $id,
        Assiduidade $assiduidade
    ) {

        $dados = $request->validate([
            'motivo' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ]);

        $estagio = $this->estagioDoSupervisor($request, $id);

        $this->garantirAssiduidadeDoEstagio($assiduidade, $estagio);

        if (! $estagio->aceitaRegistos()) {
            return response()->json([
                'message' => 'Só é possível decidir justificações de um estágio ativo.',
            ], 422);
        }

        return DB::transaction(function () use (
            $request,
            $estagio,
            $assiduidade,
            $dados
        ) {

            $bloqueada = Assiduidade::where(
                'id_assiduidade',
                $assiduidade->id_assiduidade
            )
                ->lockForUpdate()
                ->firstOrFail();

            if (! $bloqueada->temJustificacaoPendente()) {
                return response()->json([
                    'message' => 'Não há justificação pendente para recusar.',
                ], 422);
            }

            $bloqueada->update([
                'estado' => 'falta',

                'justificacao_estado' => 'recusada',

                'motivo_recusa' => $dados['motivo'],

                'id_supervisor_justificacao' => $request
                    ->user()
                    ->id_utilizador,

                'justificacao_decidida_em' => now(),
            ]);

            Notificacao::paraUtilizador(
                $estagio->id_estagiario,
                'Justificação recusada',
                'A tua justificação para a falta de '
                    .$bloqueada->data->format('d/m/Y')
                    ." foi recusada. Motivo: {$dados['motivo']}."
            );

            return response()->json([
                'message' => 'Justificação recusada.',

                'assiduidade' => $bloqueada->fresh(),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Avaliações do estágio
    |--------------------------------------------------------------------------
    */

    public function avaliacoes(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        $dados = $request->validate([
            'tipo' => [
                'nullable',
                'in:semanal,mensal,final',
            ],
        ]);

        $query = $estagio->avaliacoes()
            ->orderByDesc('periodo_referencia');

        if (! empty($dados['tipo'])) {
            $query->where('tipo', $dados['tipo']);
        }

        $todas = $estagio->avaliacoes()->get();

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'avaliacoes' => $query->get(),

            'resumo' => [
                'semanal' => $todas->where('tipo', 'semanal')->count(),

                'mensal' => $todas->where('tipo', 'mensal')->count(),

                'final' => $todas->where('tipo', 'final')->count(),

                'media_nota' => $todas->isEmpty()
                    ? null
                    : round((float) $todas->avg('nota'), 2),

                'total' => $todas->count(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Registar ou atualizar uma avaliação
    |--------------------------------------------------------------------------
    |
    | Existe no máximo uma avaliação por (tipo, período de referência),
    | pelo que lançar duas vezes o mesmo período atualiza o registo.
    |
    */

    public function registarAvaliacao(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        if (! $estagio->aceitaRegistosDeFecho()) {
            return response()->json([
                'message' => 'Não é possível avaliar um estágio cancelado.',
            ], 422);
        }

        $dados = $request->validate([
            'tipo' => [
                'required',
                'in:semanal,mensal,final',
            ],

            'periodo_referencia' => [
                'required',
                'date',
            ],

            'nota' => [
                'required',
                'numeric',
                'between:0,20',
            ],

            'nota_atividades' => [
                'nullable',
                'numeric',
                'between:0,20',
            ],

            'nota_assiduidade' => [
                'nullable',
                'numeric',
                'between:0,20',
            ],

            'nota_pontualidade' => [
                'nullable',
                'numeric',
                'between:0,20',
            ],

            'comentarios' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'pontos_fortes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'areas_melhoria' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        if ($dados['tipo'] === 'semanal' && (
            ! empty($dados['pontos_fortes'])
            || ! empty($dados['areas_melhoria'])
        )) {
            return response()->json([
                'message' => 'Os pontos fortes e as áreas de melhoria só se aplicam às avaliações mensal e final.',
            ], 422);
        }

        $atributos = [
            'id_supervisor' => $request->user()->id_utilizador,

            'nota' => $dados['nota'],

            'nota_atividades' => $dados['nota_atividades'] ?? null,

            'nota_assiduidade' => $dados['nota_assiduidade'] ?? null,

            'nota_pontualidade' => $dados['nota_pontualidade'] ?? null,

            'comentarios' => $dados['comentarios'] ?? null,

            'pontos_fortes' => $dados['tipo'] === 'semanal'
                ? null
                : ($dados['pontos_fortes'] ?? null),

            'areas_melhoria' => $dados['tipo'] === 'semanal'
                ? null
                : ($dados['areas_melhoria'] ?? null),
        ];

        $criado = false;

        $avaliacao = DB::transaction(function () use (
            $estagio,
            $dados,
            $atributos,
            &$criado
        ) {

            $avaliacao = $estagio->avaliacoes()
                ->where('tipo', $dados['tipo'])
                ->whereDate('periodo_referencia', $dados['periodo_referencia'])
                ->lockForUpdate()
                ->first();

            if ($avaliacao) {
                $avaliacao->update($atributos);

                return $avaliacao;
            }

            $criado = true;

            return $estagio->avaliacoes()->create(
                $atributos + [
                    'tipo' => $dados['tipo'],

                    'periodo_referencia' => $dados['periodo_referencia'],
                ]
            );
        });

        Notificacao::paraUtilizador(
            $estagio->id_estagiario,
            'Nova avaliação',
            'Foi registada uma avaliação '.$avaliacao->tipo
                .' referente a '
                .$avaliacao->periodo_referencia->format('d/m/Y')
                .' com a nota '.$avaliacao->nota.'.'
        );

        return response()->json([
            'message' => $criado
                ? 'Avaliação registada com sucesso.'
                : 'Avaliação atualizada com sucesso.',

            'avaliacao' => $avaliacao->fresh(),
        ], $criado ? 201 : 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Remover uma avaliação
    |--------------------------------------------------------------------------
    */

    public function removerAvaliacao(
        Request $request,
        int $id,
        Avaliacao $avaliacao
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        $this->garantirAvaliacaoDoEstagio($avaliacao, $estagio);

        if (! $estagio->aceitaRegistosDeFecho()) {
            return response()->json([
                'message' => 'Não é possível alterar avaliações de um estágio cancelado.',
            ], 422);
        }

        $avaliacao->delete();

        return response()->json([
            'message' => 'Avaliação removida com sucesso.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Feedback do estagiário sobre o estágio
    |--------------------------------------------------------------------------
    */

    public function feedback(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoSupervisor($request, $id);

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'feedback' => $estagio->feedback,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Garantir que a avaliação pertence ao estágio indicado
    |--------------------------------------------------------------------------
    */

    private function garantirAvaliacaoDoEstagio(
        Avaliacao $avaliacao,
        Estagio $estagio
    ): void {
        if ((int) $avaliacao->id_estagio !== (int) $estagio->id_estagio) {
            abort(response()->json([
                'message' => 'Avaliação não encontrada.',
            ], 404));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Garantir que o registo de assiduidade pertence ao estágio indicado
    |--------------------------------------------------------------------------
    */

    private function garantirAssiduidadeDoEstagio(
        Assiduidade $assiduidade,
        Estagio $estagio
    ): void {
        if ((int) $assiduidade->id_estagio !== (int) $estagio->id_estagio) {
            abort(response()->json([
                'message' => 'Registo de assiduidade não encontrado.',
            ], 404));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Garantir que o documento pertence ao estágio indicado
    |--------------------------------------------------------------------------
    */

    private function garantirDocumentoDoEstagio(
        DocumentoEstagio $documento,
        Estagio $estagio
    ): void {
        if ((int) $documento->id_estagio !== (int) $estagio->id_estagio) {
            abort(response()->json([
                'message' => 'Documento não encontrado.',
            ], 404));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Estágios atribuídos ao supervisor autenticado
    |--------------------------------------------------------------------------
    */

    private function estagiosDoSupervisor($utilizador)
    {
        return Estagio::query()
            ->whereHas('supervisores', function ($q) use ($utilizador) {
                $q->where(
                    'id_supervisor',
                    $utilizador->id_utilizador
                )
                    ->whereNull('removido_em');
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Obter um estágio atribuído ao supervisor autenticado
    |--------------------------------------------------------------------------
    */

    private function estagioDoSupervisor(
        Request $request,
        int $id
    ): Estagio {

        $estagio = $this->estagiosDoSupervisor($request->user())
            ->where('id_estagio', $id)
            ->first();

        if (! $estagio) {
            abort(response()->json([
                'message' => 'Estágio não encontrado ou não atribuído a este supervisor.',
            ], 404));
        }

        return $estagio;
    }
}
