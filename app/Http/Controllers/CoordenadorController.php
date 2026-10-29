<?php

namespace App\Http\Controllers;

use App\Http\Resources\DocumentoEstagioResource;
use App\Http\Resources\PlanoEstagioResource;
use App\Models\Candidatura;
use App\Models\Estagio;
use App\Models\HistoricoEstado;
use App\Models\Perfil;
use App\Models\PlanoEstagio;
use App\Models\User;
use App\Services\NotificacaoService;
use App\Services\Sms\SmsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class CoordenadorController extends Controller
{
    public function __construct(
        private readonly NotificacaoService $notificacoes
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Obter a direção do coordenador autenticado
    |--------------------------------------------------------------------------
    */

    private function idDepartamentoDoCoordenador(
        Request $request
    ): int {

        $idDepartamento = $request->user()
            ->perfil
            ?->id_departamento;

        if (! $idDepartamento) {
            abort(response()->json([
                'message' => 'O coordenador não está associado a uma direção.',
            ], 403));
        }

        return (int) $idDepartamento;
    }

    /*
    |--------------------------------------------------------------------------
    | Consultar candidaturas atribuídas ao coordenador autenticado
    |--------------------------------------------------------------------------
    */

    private function candidaturasDoCoordenador(
        Request $request
    ) {

        $user = $request->user();

        $idDepartamento =
            $this->idDepartamentoDoCoordenador($request);

        $query = Candidatura::query();

        $this->aplicarVisibilidadeDoCoordenador(
            $query,
            $user,
            $idDepartamento,
            false
        );

        return $query;
    }

    /**
     * Limita as candidaturas às que o coordenador pode gerir.
     *
     * Inclui as candidaturas órfãs e as que ficaram associadas a um
     * coordenador inativo, removido ou de outra direção, para que a direção
     * possa retomar a sua gestão.
     */
    private function aplicarVisibilidadeDoCoordenador(
        Builder $query,
        User $user,
        int $idDepartamento,
        bool $incluirSemCoordenador
    ): void {
        $query
            ->where(
                'id_departamento_selecionado',
                $idDepartamento
            )
            ->where(function (Builder $subquery) use (
                $user,
                $idDepartamento,
                $incluirSemCoordenador
            ) {
                $subquery->where(
                    'id_coordenador',
                    $user->id_utilizador
                );

                if ($incluirSemCoordenador) {
                    $subquery->orWhereNull('id_coordenador');
                }

                $subquery->orWhere(function (Builder $invalida) use (
                    $idDepartamento
                ) {
                    $invalida
                        ->whereNotNull('id_coordenador')
                        ->whereDoesntHave(
                            'coordenador',
                            function (Builder $coordenador) use ($idDepartamento) {
                                $coordenador
                                    ->where('ativo', true)
                                    ->where('tipo_utilizador', 'coordenador')
                                    ->whereHas(
                                        'perfil',
                                        function (Builder $perfil) use ($idDepartamento) {
                                            $perfil->where(
                                                'id_departamento',
                                                $idDepartamento
                                            );
                                        }
                                    );
                            }
                        );
                });
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard do coordenador
    |--------------------------------------------------------------------------
    */

    public function dashboard(Request $request)
    {
        $query = $this->candidaturasDoCoordenador($request);

        return response()->json([
            'total_candidaturas' => (clone $query)->count(),

            'em_analise' => (clone $query)
                ->where('estado', 'em_analise')
                ->count(),

            'aprovadas' => (clone $query)
                ->where('estado', 'aprovada')
                ->count(),

            'recusadas' => (clone $query)
                ->where('estado', 'recusada')
                ->count(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Listar candidaturas
    |--------------------------------------------------------------------------
    */

    public function candidaturas(Request $request)
    {
        $dados = $request->validate([
            'pesquisa' => [
                'nullable',
                'string',
                'max:150',
            ],

            'estado' => [
                'nullable',
                'in:em_analise,aprovada,recusada,encerrada',
            ],

            'por_pagina' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
        ]);

        $query = $this->candidaturasDoCoordenador($request)
            ->with('departamento')
            ->orderByDesc('created_at');

        if (! empty($dados['pesquisa'])) {
            $pesquisa = $dados['pesquisa'];

            $query->where(function ($q) use ($pesquisa) {
                $q->where(
                    'nome',
                    'like',
                    "%{$pesquisa}%"
                )
                    ->orWhere(
                        'email',
                        'like',
                        "%{$pesquisa}%"
                    )
                    ->orWhere(
                        'curso',
                        'like',
                        "%{$pesquisa}%"
                    )
                    ->orWhere(
                        'universidade',
                        'like',
                        "%{$pesquisa}%"
                    );
            });
        }

        if (! empty($dados['estado'])) {
            $query->where('estado', $dados['estado']);
        }

        return response()->json(
            $query->paginate($dados['por_pagina'] ?? 15)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Consultar uma candidatura
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        int $id
    ) {

        $candidatura = $this
            ->candidaturasDoCoordenador($request)
            ->with([
                'departamento',
                'documentos',
                'entrevista.supervisorRepresentante:id_utilizador,nome,email',
            ])
            ->where('id_candidatura', $id)
            ->first();

        if (! $candidatura) {
            return response()->json([
                'message' => 'Candidatura não encontrada ou não atribuída a este coordenador.',
            ], 404);
        }

        Gate::authorize('view', $candidatura);

        return response()->json($candidatura);
    }

    /*
    |--------------------------------------------------------------------------
    | Aprovar candidatura
    |--------------------------------------------------------------------------
    */

    public function aprovar(
        Request $request,
        int $id
    ) {

        /*
         * A candidatura aprovada é guardada para que o SMS seja enviado
         * apenas depois de a transação ser confirmada.
         */
        $candidaturaAprovada = null;

        $resposta = DB::transaction(function () use (
            $request,
            $id,
            &$candidaturaAprovada
        ) {

            $candidatura = $this
                ->candidaturasDoCoordenador($request)
                ->where('id_candidatura', $id)
                ->lockForUpdate()
                ->first();

            if (! $candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada ou não atribuída a este coordenador.',
                ], 404);
            }

            if ($candidatura->estado !== 'em_analise') {
                return response()->json([
                    'message' => 'Esta candidatura já foi decidida.',
                ], 422);
            }

            if ($candidatura->entrevista()
                ->where('resultado', 'pendente')
                ->exists()) {
                return response()->json([
                    'message' => 'Existe uma entrevista pendente. Registe o resultado antes de decidir a candidatura.',
                ], 422);
            }

            Gate::authorize('decidir', $candidatura);

            $candidatura->garantirCoordenador($request->user());

            $estadoAnterior = $candidatura->estado;

            $candidatura->update([
                'estado' => 'aprovada',
                'motivo_recusa' => null,
                'data_decisao' => now(),
            ]);

            $candidatura->sincronizarPerfilDoEstagiario();

            HistoricoEstado::create([
                'id_candidatura' => $candidatura->id_candidatura,

                'estado_anterior' => $estadoAnterior,

                'estado_novo' => 'aprovada',

                'motivo' => null,

                'id_utilizador' => $request->user()->id_utilizador,
            ]);

            $this->notificacoes->paraCandidato(
                $candidatura,
                'Candidatura aprovada',
                'A tua candidatura foi aprovada.',
                '/estagiario'
            );

            $candidaturaAprovada = $candidatura
                ->fresh()
                ->load([
                    'departamento',
                    'documentos',
                    'utilizador',
                    'entrevista.supervisorRepresentante:id_utilizador,nome,email',
                ]);

            return response()->json([
                'message' => 'Candidatura aprovada com sucesso.',

                'candidatura' => $candidaturaAprovada,
            ]);
        });

        /*
         * O candidato é avisado por SMS da aceitação. O envio só acontece
         * depois de a transação ter sido confirmada, para que não seja
         * enviada uma mensagem de parabéns a uma candidatura que não
         * chegou a ser aceite.
         */
        $this->notificarAprovacaoPorSms($candidaturaAprovada);

        return $resposta;
    }

    /*
    |--------------------------------------------------------------------------
    | Aviso de aceitação por SMS
    |--------------------------------------------------------------------------
    |
    | Uma falha no envio do SMS não pode impedir a aprovação da
    | candidatura: o erro é registado no log e o pedido continua a ser
    | considerado bem-sucedido.
    |
    */

    private function notificarAprovacaoPorSms(?Candidatura $candidatura): void
    {
        if ($candidatura === null) {
            return;
        }

        try {
            app(SmsService::class)->notificarCandidaturaAprovada($candidatura);
        } catch (\Throwable $excecao) {
            Log::channel('sms')->error(
                'Falha ao enviar o SMS de aceitação da candidatura',
                [
                    'id_candidatura' => $candidatura->id_candidatura,
                    'erro' => $excecao->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Recusar candidatura
    |--------------------------------------------------------------------------
    */

    public function recusar(
        Request $request,
        int $id
    ) {

        $dados = $request->validate([
            'motivo' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ]);

        return DB::transaction(function () use (
            $request,
            $id,
            $dados
        ) {

            $candidatura = $this
                ->candidaturasDoCoordenador($request)
                ->where('id_candidatura', $id)
                ->lockForUpdate()
                ->first();

            if (! $candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada ou não atribuída a este coordenador.',
                ], 404);
            }

            if ($candidatura->estado !== 'em_analise') {
                return response()->json([
                    'message' => 'Esta candidatura já foi decidida.',
                ], 422);
            }

            if ($candidatura->entrevista()
                ->where('resultado', 'pendente')
                ->exists()) {
                return response()->json([
                    'message' => 'Existe uma entrevista pendente. Registe o resultado antes de decidir a candidatura.',
                ], 422);
            }

            Gate::authorize('decidir', $candidatura);

            $candidatura->garantirCoordenador($request->user());

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
                "A tua candidatura foi recusada. Motivo: {$dados['motivo']}.",
                '/estagiario'
            );

            return response()->json([
                'message' => 'Candidatura recusada com sucesso.',

                'candidatura' => $candidatura
                    ->fresh()
                    ->load([
                        'departamento',
                        'documentos',
                        'entrevista.supervisorRepresentante:id_utilizador,nome,email',
                    ]),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Listar os supervisores da direção do coordenador
    |--------------------------------------------------------------------------
    */

    public function listarSupervisores(Request $request)
    {

        $idDepartamento =
            $this->idDepartamentoDoCoordenador($request);

        $supervisores = User::query()
            ->with('perfil')
            ->where('tipo_utilizador', 'supervisor')
            ->where('ativo', true)
            ->whereHas('perfil', function ($q) use ($idDepartamento) {
                $q->where('id_departamento', $idDepartamento);
            })
            ->orderBy('nome')
            ->get()
            ->map(function (User $supervisor) {
                return [
                    'id_utilizador' => $supervisor->id_utilizador,

                    'nome' => $supervisor->nome,

                    'email' => $supervisor->email,

                    'telefone' => $supervisor->telefone,

                    'id_departamento' => $supervisor->perfil?->id_departamento,
                ];
            })
            ->values();

        return response()->json([
            'supervisores' => $supervisores,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Criar supervisor
    |--------------------------------------------------------------------------
    */

    public function criarSupervisor(Request $request)
    {
        $idDepartamento =
            $this->idDepartamentoDoCoordenador($request);

        $dados = $request->validate([
            'nome' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:utilizadores,email',
            ],

            'password' => [
                'required',
                'string',
                Password::default(),
                'confirmed',
            ],

            'telefone' => [
                'nullable',
                'string',
                'max:30',
            ],
        ]);

        $supervisor = DB::transaction(function () use (
            $dados,
            $idDepartamento
        ) {

            $utilizador = User::create([
                'nome' => $dados['nome'],

                'email' => $dados['email'],

                'password' => Hash::make(
                    $dados['password']
                ),

                'telefone' => $dados['telefone'] ?? null,

                'tipo_utilizador' => 'supervisor',

                'ativo' => true,
            ]);

            Perfil::create([
                'id_utilizador' => $utilizador->id_utilizador,

                'id_departamento' => $idDepartamento,
            ]);

            return $utilizador;
        });

        return response()->json([
            'message' => 'Supervisor criado com sucesso.',

            'supervisor' => [
                'id_utilizador' => $supervisor->id_utilizador,

                'nome' => $supervisor->nome,

                'email' => $supervisor->email,

                'telefone' => $supervisor->telefone,

                'tipo_utilizador' => $supervisor->tipo_utilizador,

                'ativo' => $supervisor->ativo,

                'id_departamento' => $idDepartamento,
            ],
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Obter um estágio atribuído ao coordenador autenticado
    |--------------------------------------------------------------------------
    |
    | Um estágio pertence ao coordenador cuja candidatura de origem foi por
    | ele decidida dentro da sua direção. Fora desse âmbito responde 404,
    | para não revelar a existência do estágio a terceiros.
    |
    */

    private function estagioDoCoordenador(
        Request $request,
        int $id
    ): Estagio {

        $user = $request->user();

        $idDepartamento =
            $this->idDepartamentoDoCoordenador($request);

        $estagio = Estagio::query()
            ->where('id_estagio', $id)
            ->whereHas('candidatura', function (Builder $q) use (
                $user,
                $idDepartamento
            ) {
                $this->aplicarVisibilidadeDoCoordenador(
                    $q,
                    $user,
                    $idDepartamento,
                    true
                );
            })
            ->first();

        if (
            ! $estagio
            || ! Gate::forUser($user)->allows('view', $estagio)
        ) {
            abort(response()->json([
                'message' => 'Estágio não encontrado ou não atribuído a este coordenador.',
            ], 404));
        }

        return $estagio;
    }

    /*
    |--------------------------------------------------------------------------
    | Listar os estágios da direção do coordenador
    |--------------------------------------------------------------------------
    */

    public function estagios(Request $request)
    {
        $dados = $request->validate([
            'pesquisa' => [
                'nullable',
                'string',
                'max:150',
            ],

            'estado' => [
                'nullable',
                'in:ativo,suspenso,concluido,cancelado',
            ],

            'por_pagina' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
        ]);

        $user = $request->user();

        $idDepartamento =
            $this->idDepartamentoDoCoordenador($request);

        $query = Estagio::query()
            ->whereHas('candidatura', function (Builder $q) use (
                $user,
                $idDepartamento
            ) {
                $this->aplicarVisibilidadeDoCoordenador(
                    $q,
                    $user,
                    $idDepartamento,
                    true
                );
            })
            ->with([
                'estagiario',
                'candidatura.departamento',
                'supervisoresAtivos.supervisor',
            ])
            ->orderByDesc('created_at');

        if (! empty($dados['pesquisa'])) {
            $pesquisa = $dados['pesquisa'];

            $query->whereHas('estagiario', function ($q) use ($pesquisa) {
                $q->where('nome', 'like', "%{$pesquisa}%")
                    ->orWhere('email', 'like', "%{$pesquisa}%");
            });
        }

        if (! empty($dados['estado'])) {
            $query->where('estado', $dados['estado']);
        }

        $pagina = $query->paginate($dados['por_pagina'] ?? 15);

        /*
         * Assume as candidaturas da direção que ainda não tinham coordenador
         * definido (estágios iniciados antes do encaminhamento). A atribuição
         * passa a ficar registada para as decisões seguintes.
         */
        $pagina->getCollection()->each(function (Estagio $estagio) use ($user) {
            $estagio->candidatura?->garantirCoordenador($user);
        });

        return response()->json($pagina);
    }

    /*
    |--------------------------------------------------------------------------
    | Listar os supervisores de um estágio
    |--------------------------------------------------------------------------
    */

    public function supervisores(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoCoordenador($request, $id);

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'supervisores' => $estagio->supervisores()
                ->with('supervisor')
                ->orderByDesc('atribuido_em')
                ->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Atribuir um supervisor a um estágio
    |--------------------------------------------------------------------------
    */

    public function atribuirSupervisor(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoCoordenador($request, $id);

        if ($estagio->estaTerminado()) {
            return response()->json([
                'message' => 'Não é possível atribuir supervisores a um estágio terminado.',
            ], 422);
        }

        $dados = $request->validate([
            'id_supervisor' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $idDepartamento =
            $this->idDepartamentoDoCoordenador($request);

        $supervisor = User::query()
            ->with('perfil')
            ->where(
                'id_utilizador',
                $dados['id_supervisor']
            )
            ->where('tipo_utilizador', 'supervisor')
            ->where('ativo', true)
            ->first();

        if (! $supervisor) {
            return response()->json([
                'message' => 'O supervisor indicado não existe, está inativo ou não tem o perfil correto.',
            ], 422);
        }

        if (
            (int) $supervisor->perfil?->id_departamento
            !== $idDepartamento
        ) {
            return response()->json([
                'message' => 'O supervisor não pertence à direção do coordenador.',
            ], 422);
        }

        $estagio->candidatura?->garantirCoordenador($request->user());

        try {
            $vinculo = $estagio->supervisores()->create([
                'id_supervisor' => $supervisor->id_utilizador,

                'id_coordenador' => $request->user()->id_utilizador,

                'atribuido_em' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Este supervisor já está atribuído a este estágio.',
            ], 422);
        }

        $this->notificacoes->paraUtilizador(
            $supervisor,
            'Novo estágio atribuído',
            "Foi-te atribuído o acompanhamento do estágio #{$estagio->id_estagio}.",
            "/supervisor?estagio={$estagio->id_estagio}&aba=plano"
        );

        $this->notificacoes->paraUtilizador(
            $estagio->id_estagiario,
            'Supervisor atribuído',
            "Foi-te atribuído o supervisor {$supervisor->nome}.",
            '/area-reservada?separador=geral'
        );

        return response()->json([
            'message' => 'Supervisor atribuído com sucesso.',

            'supervisor' => $vinculo
                ->fresh()
                ->load('supervisor'),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Remover um supervisor de um estágio
    |--------------------------------------------------------------------------
    |
    | A remoção é lógica (preenche removido_em) para preservar o histórico
    | de quem acompanhou o estágio.
    |
    */

    public function removerSupervisor(
        Request $request,
        int $id,
        int $idSupervisor
    ) {

        $estagio = $this->estagioDoCoordenador($request, $id);

        if ($estagio->estaTerminado()) {
            return response()->json([
                'message' => 'Este estágio já terminou e não aceita alterações.',
            ], 422);
        }

        $vinculo = $estagio->supervisores()
            ->where('id_supervisor', $idSupervisor)
            ->whereNull('removido_em')
            ->first();

        if (! $vinculo) {
            return response()->json([
                'message' => 'Este supervisor não está atribuído ao estágio.',
            ], 404);
        }

        $estagio->candidatura?->garantirCoordenador($request->user());

        $vinculo->update([
            'removido_em' => now(),
        ]);

        return response()->json([
            'message' => 'Supervisor removido do estágio.',

            'supervisor' => $vinculo
                ->fresh()
                ->load('supervisor'),
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

        $estagio = $this->estagioDoCoordenador($request, $id);

        $plano = $estagio->planoVigente();

        if ($plano) {
            Gate::authorize('view', $plano);
        }

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'plano' => $plano
                ? new PlanoEstagioResource($plano)
                : null,

            'historico' => $plano
                ? $plano->historico()
                    ->orderByDesc('id_plano_historico')
                    ->get()
                : [],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Aprovar o plano de estágio
    |--------------------------------------------------------------------------
    */

    public function aprovarPlano(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoCoordenador($request, $id);

        if ($estagio->estaTerminado()) {
            return response()->json([
                'message' => 'Este estágio já terminou e não aceita alterações.',
            ], 422);
        }

        Gate::authorize('aprovarPlano', $estagio);

        return DB::transaction(function () use (
            $request,
            $estagio
        ) {

            $estagio->candidatura?->garantirCoordenador(
                $request->user()
            );

            $plano = $this->planoSubmetido($estagio);

            if (! $plano) {
                return response()->json([
                    'message' => 'Não há plano de estágio submetido para aprovar.',
                ], 422);
            }

            Gate::authorize('decidir', $plano);

            $estadoAnterior = $plano->estado;

            $plano->update([
                'estado' => 'aprovado',

                'motivo_recusa' => null,

                'id_coordenador' => $request->user()->id_utilizador,

                'data_decisao' => now(),
            ]);

            HistoricoEstado::create([
                'id_plano_estagio' => $plano->id_plano_estagio,

                'estado_anterior' => $estadoAnterior,

                'estado_novo' => 'aprovado',

                'motivo' => null,

                'id_utilizador' => $request->user()->id_utilizador,
            ]);

            $this->notificacoes->paraUtilizadores(
                $estagio->supervisoresUtilizadores(),
                'Plano de estágio aprovado',
                "O plano de estágio #{$plano->id_plano_estagio} foi aprovado.",
                "/supervisor?estagio={$estagio->id_estagio}&aba=plano"
            );

            return response()->json([
                'message' => 'Plano de estágio aprovado com sucesso.',

                'plano' => new PlanoEstagioResource($plano->fresh()),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Recusar o plano de estágio
    |--------------------------------------------------------------------------
    */

    public function recusarPlano(
        Request $request,
        int $id
    ) {

        $dados = $request->validate([
            'motivo' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ]);

        $estagio = $this->estagioDoCoordenador($request, $id);

        if ($estagio->estaTerminado()) {
            return response()->json([
                'message' => 'Este estágio já terminou e não aceita alterações.',
            ], 422);
        }

        Gate::authorize('aprovarPlano', $estagio);

        return DB::transaction(function () use (
            $request,
            $estagio,
            $dados
        ) {

            $estagio->candidatura?->garantirCoordenador(
                $request->user()
            );

            $plano = $this->planoSubmetido($estagio);

            if (! $plano) {
                return response()->json([
                    'message' => 'Não há plano de estágio submetido para recusar.',
                ], 422);
            }

            Gate::authorize('decidir', $plano);

            $estadoAnterior = $plano->estado;

            $plano->update([
                'estado' => 'recusado',

                'motivo_recusa' => $dados['motivo'],

                'id_coordenador' => $request->user()->id_utilizador,

                'data_decisao' => now(),
            ]);

            HistoricoEstado::create([
                'id_plano_estagio' => $plano->id_plano_estagio,

                'estado_anterior' => $estadoAnterior,

                'estado_novo' => 'recusado',

                'motivo' => $dados['motivo'],

                'id_utilizador' => $request->user()->id_utilizador,
            ]);

            $this->notificacoes->paraUtilizadores(
                $estagio->supervisoresUtilizadores(),
                'Plano de estágio recusado',
                "O plano de estágio #{$plano->id_plano_estagio} foi recusado. Motivo: {$dados['motivo']}.",
                "/supervisor?estagio={$estagio->id_estagio}&aba=plano"
            );

            return response()->json([
                'message' => 'Plano de estágio recusado.',

                'plano' => new PlanoEstagioResource($plano->fresh()),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Documentos e relatórios do estágio (visão da direção)
    |--------------------------------------------------------------------------
    */

    public function documentos(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoCoordenador($request, $id);

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'documentos' => DocumentoEstagioResource::collection(
                $estagio->documentos()
                    ->orderByDesc('versao')
                    ->get()
            ),
        ]);
    }

    public function relatorios(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoCoordenador($request, $id);

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
    | Avaliações e feedback do estágio (visão da direção)
    |--------------------------------------------------------------------------
    */

    public function avaliacoes(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoCoordenador($request, $id);

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'avaliacoes' => $estagio->avaliacoes()
                ->orderByDesc('periodo_referencia')
                ->get(),
        ]);
    }

    public function feedback(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoCoordenador($request, $id);

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'feedback' => $estagio->feedback,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Assiduidade e atividades do estágio (visão da direção)
    |--------------------------------------------------------------------------
    */

    public function assiduidades(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoCoordenador($request, $id);

        return response()->json([
            'id_estagio' => $estagio->id_estagio,

            'assiduidades' => $estagio->assiduidades()
                ->orderByDesc('data')
                ->get(),
        ]);
    }

    public function atividades(
        Request $request,
        int $id
    ) {

        $estagio = $this->estagioDoCoordenador($request, $id);

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
    | Plano submetido à espera de decisão
    |--------------------------------------------------------------------------
    */

    private function planoSubmetido(
        Estagio $estagio
    ): ?PlanoEstagio {

        return $estagio->planos()
            ->doSupervisor()
            ->where('estado', 'submetido')
            ->latest('id_plano_estagio')
            ->lockForUpdate()
            ->first();
    }
}
