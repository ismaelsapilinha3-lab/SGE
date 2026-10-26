<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidatura;
use App\Models\Estagio;
use App\Models\HistoricoEstado;
use App\Models\User;
use App\Services\NotificacaoService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EstagioAdminController extends Controller
{
    public function __construct(
        private readonly NotificacaoService $notificacoes
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Listar estágios
    |--------------------------------------------------------------------------
    |
    | Ao contrário de /admin/estagiarios (que mistura candidaturas ainda sem
    | estágio), esta listagem devolve apenas estágios reais, com o estagiário,
    | a candidatura e os supervisores ativos carregados.
    |
    */

    public function index(Request $request)
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

            'trimestre' => [
                'nullable',
                'regex:/^\d{4}-[1-4]$/',
            ],

            'por_pagina' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
        ]);

        $trimestre = $this->trimestreDe($dados['trimestre'] ?? null);

        $query = Estagio::query()
            ->with([
                'estagiario',
                'candidatura.departamento',
                'supervisoresAtivos.supervisor',
            ])
            ->orderByDesc('created_at');

        if (! empty($dados['pesquisa'])) {
            $pesquisa = $dados['pesquisa'];

            $query->where(function ($q) use ($pesquisa) {
                $q->whereHas('estagiario', function ($sub) use ($pesquisa) {
                    $sub->where('nome', 'like', "%{$pesquisa}%")
                        ->orWhere('email', 'like', "%{$pesquisa}%");
                })
                    ->orWhereHas('candidatura', function ($sub) use ($pesquisa) {
                        $sub->where('nome', 'like', "%{$pesquisa}%")
                            ->orWhere('email', 'like', "%{$pesquisa}%")
                            ->orWhere('curso', 'like', "%{$pesquisa}%");
                    });
            });
        }

        if (! empty($dados['estado'])) {
            $query->where('estado', $dados['estado']);
        }

        if ($trimestre !== null) {
            /*
             * Intervalo semiaberto para o filtro continuar a usar o índice
             * de `data_inicio` e incluir todo o último dia do trimestre.
             */
            $query
                ->where(
                    'data_inicio',
                    '>=',
                    $trimestre['inicio']->toDateString()
                )
                ->where(
                    'data_inicio',
                    '<',
                    $trimestre['fim']->copy()->addDay()->toDateString()
                );
        }

        $pagina = $query
            ->paginate($dados['por_pagina'] ?? 15)
            ->appends($request->query());

        return response()->json([
            ...$pagina->toArray(),

            'trimestre' => $trimestre === null
                ? null
                : [
                    'ano' => $trimestre['ano'],
                    'numero' => $trimestre['numero'],
                    'inicio' => $trimestre['inicio']->toDateString(),
                    'fim' => $trimestre['fim']->toDateString(),
                    'rotulo' => $trimestre['rotulo'],
                ],
        ]);
    }

    /**
     * Intervalo do trimestre civil indicado no formato `AAAA-T`.
     *
     * @return array{inicio: Carbon, fim: Carbon, ano: int, numero: int, rotulo: string}|null
     */
    private function trimestreDe(?string $valor): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        [$ano, $numero] = array_map('intval', explode('-', $valor));

        $inicio = Carbon::createFromDate(
            $ano,
            (($numero - 1) * 3) + 1,
            1
        )->startOfDay();

        return [
            'inicio' => $inicio,
            'fim' => $inicio->copy()->endOfQuarter(),
            'ano' => $ano,
            'numero' => $numero,
            'rotulo' => sprintf('%d.º trimestre de %d', $numero, $ano),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Consultar um estágio
    |--------------------------------------------------------------------------
    */

    public function show(int $id)
    {
        $estagio = Estagio::query()
            ->with([
                'estagiario',
                'candidatura.departamento',
                'supervisoresAtivos.supervisor',
                'planoAprovado',
            ])
            ->where('id_estagio', $id)
            ->first();

        if (! $estagio) {
            return response()->json([
                'message' => 'Estágio não encontrado.',
            ], 404);
        }

        return response()->json($estagio);
    }

    /*
    |--------------------------------------------------------------------------
    | Iniciar ou alterar o estado de um estágio
    |--------------------------------------------------------------------------
    |
    | Recebe o id da candidatura (não o do estágio) para que a mesma rota
    | sirva tanto para iniciar o estágio de uma candidatura aprovada como
    | para o suspender, concluir ou cancelar.
    |
    */

    public function atualizarEstado(
        Request $request,
        int $idCandidatura
    ) {
        $dados = $request->validate([
            'estado' => [
                'required',
                'in:ativo,suspenso,concluido,cancelado',
            ],

            'data_inicio' => [
                'nullable',
                'date',
            ],

            'data_fim_prevista' => [
                'nullable',
                'date',
            ],

            'duracao_meses' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'dia_limite_relatorio' => [
                'nullable',
                'integer',
                'min:1',
                'max:28',
            ],

            'motivo_suspensao' => [
                'nullable',
                'string',
                'required_if:estado,suspenso',
                'max:5000',
            ],
        ]);

        return DB::transaction(function () use (
            $request,
            $dados,
            $idCandidatura
        ) {

            $candidatura = Candidatura::query()
                ->where('id_candidatura', $idCandidatura)
                ->lockForUpdate()
                ->first();

            if (! $candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada.',
                ], 404);
            }

            $estagio = Estagio::query()
                ->where('id_candidatura', $idCandidatura)
                ->lockForUpdate()
                ->first();

            if (! $estagio) {
                return $this->iniciarEstagio(
                    $request,
                    $candidatura,
                    $dados
                );
            }

            return $this->transitarEstagio(
                $request,
                $estagio,
                $dados
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Iniciar um novo estágio
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $dados
     */
    private function iniciarEstagio(
        Request $request,
        Candidatura $candidatura,
        array $dados
    ) {

        if ($candidatura->estado !== 'aprovada') {
            return response()->json([
                'message' => 'Só é possível iniciar um estágio para uma candidatura aprovada.',
            ], 422);
        }

        if ($dados['estado'] !== 'ativo') {
            return response()->json([
                'message' => 'Um novo estágio tem de começar no estado ativo.',
            ], 422);
        }

        $utilizador = $this->contaDoEstagiario($candidatura);

        if (! $utilizador) {
            return response()->json([
                'message' => 'Esta candidatura ainda não tem uma conta de estagiário. Cria a conta antes de iniciar o estágio.',
            ], 422);
        }

        /*
        | Ao iniciar o estágio o candidato passa a estagiário ativo, pelo que a
        | candidatura tem de ficar atribuída a um coordenador da sua direção.
        | Sem isto o coordenador não a encontra para lhe atribuir supervisor.
        */
        $candidatura->garantirCoordenador();

        $inicio = $dados['data_inicio'] ?? null;

        $fimPrevisto = $dados['data_fim_prevista'] ?? null;

        $duracaoMeses = isset($dados['duracao_meses'])
            ? (int) $dados['duracao_meses']
            : null;

        if (! $inicio) {
            return response()->json([
                'message' => 'Indica a data de início do estágio.',
            ], 422);
        }

        if ($duracaoMeses !== null) {
            $fimPrevisto = Carbon::parse($inicio)
                ->addMonthsNoOverflow($duracaoMeses)
                ->toDateString();
        }

        if (! $fimPrevisto) {
            return response()->json([
                'message' => 'Indica a duração ou a data prevista de fim do estágio.',
            ], 422);
        }

        if ($fimPrevisto <= $inicio) {
            return response()->json([
                'message' => 'A data prevista de fim não pode ser anterior à data de início.',
            ], 422);
        }

        $duracaoMeses ??= $this->calcularDuracaoMeses($inicio, $fimPrevisto);

        try {
            $estagio = Estagio::create([
                'id_candidatura' => $candidatura->id_candidatura,
                'id_estagiario' => $utilizador->id_utilizador,
                'data_inicio' => $inicio,
                'duracao_meses' => $duracaoMeses,
                'data_fim_prevista' => $fimPrevisto,
                'dia_limite_relatorio' => $dados['dia_limite_relatorio'] ?? 5,
                'estado' => 'ativo',
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'O estagiário já tem um estágio ativo.',
            ], 422);
        }

        HistoricoEstado::create([
            'id_estagio' => $estagio->id_estagio,

            'estado_anterior' => null,

            'estado_novo' => 'ativo',

            'motivo' => null,

            'id_utilizador' => $request->user()->id_utilizador,
        ]);

        $this->notificacoes->paraUtilizador(
            $utilizador,
            'Estágio iniciado',
            'O teu estágio foi iniciado em '
                .$estagio->data_inicio->format('d/m/Y')
                .' e tem fim previsto para '
                .$estagio->data_fim_prevista->format('d/m/Y').'.'
        );

        return response()->json([
            'message' => 'Estágio iniciado com sucesso.',

            'data' => $estagio
                ->fresh()
                ->load('estagiario'),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Alterar o estado de um estágio existente
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $dados
     */
    private function transitarEstagio(
        Request $request,
        Estagio $estagio,
        array $dados
    ) {

        $estadoAtual = $estagio->estado;

        $novoEstado = $dados['estado'];

        if ($estagio->estaTerminado()) {
            return response()->json([
                'message' => 'Este estágio já terminou e não pode ser alterado.',
            ], 422);
        }

        $mudaEstado = $novoEstado !== $estadoAtual;

        if ($mudaEstado && ! $estagio->podeTransitarPara($novoEstado)) {
            return response()->json([
                'message' => "Transição de estado inválida: de {$estadoAtual} para {$novoEstado}.",
            ], 422);
        }

        $inicio = $dados['data_inicio']
            ?? $estagio->data_inicio?->toDateString();

        $duracaoMeses = isset($dados['duracao_meses'])
            ? (int) $dados['duracao_meses']
            : (int) ($estagio->duracao_meses ?? 0);

        $fimPrevisto = $dados['data_fim_prevista']
            ?? $estagio->data_fim_prevista?->toDateString();

        if (isset($dados['duracao_meses'])) {
            $fimPrevisto = Carbon::parse($inicio)
                ->addMonthsNoOverflow($duracaoMeses)
                ->toDateString();
        } elseif (
            isset($dados['data_inicio'])
            && ! isset($dados['data_fim_prevista'])
            && $duracaoMeses >= 1
        ) {
            $fimPrevisto = Carbon::parse($inicio)
                ->addMonthsNoOverflow($duracaoMeses)
                ->toDateString();
        } elseif (
            $inicio
            && $fimPrevisto
            && (
                isset($dados['data_inicio'])
                || isset($dados['data_fim_prevista'])
            )
        ) {
            $duracaoMeses = $this->calcularDuracaoMeses(
                $inicio,
                $fimPrevisto
            );
        }

        if ($inicio && $fimPrevisto && $fimPrevisto <= $inicio) {
            return response()->json([
                'message' => 'A data prevista de fim não pode ser anterior à data de início.',
            ], 422);
        }

        $motivoSuspensao = $novoEstado === 'suspenso'
            ? trim($dados['motivo_suspensao'] ?? '')
            : null;

        try {
            $estagio->update([
                'data_inicio' => $inicio,

                'duracao_meses' => $duracaoMeses,

                'data_fim_prevista' => $fimPrevisto,

                'dia_limite_relatorio' => $dados['dia_limite_relatorio']
                    ?? $estagio->dia_limite_relatorio,

                'estado' => $novoEstado,

                'motivo_suspensao' => $motivoSuspensao,

                'data_fim_real' => $novoEstado === 'concluido'
                        ? ($estagio->data_fim_real ?? now()->toDateString())
                        : $estagio->data_fim_real,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'O estagiário já tem outro estágio ativo.',
            ], 422);
        }

        if ($mudaEstado) {
            HistoricoEstado::create([
                'id_estagio' => $estagio->id_estagio,

                'estado_anterior' => $estadoAtual,

                'estado_novo' => $novoEstado,

                'motivo' => $motivoSuspensao,

                'id_utilizador' => $request->user()->id_utilizador,
            ]);

            $mensagemEstagiario = 'O estado do teu estágio passou de '
                .$estadoAtual.' para '.$novoEstado.'.';

            if ($motivoSuspensao) {
                $mensagemEstagiario .= ' Motivo: '.$motivoSuspensao.'.';
            }

            $this->notificacoes->paraUtilizador(
                $estagio->id_estagiario,
                'Estado do estágio atualizado',
                $mensagemEstagiario
            );

            $this->notificacoes->paraUtilizadores(
                $estagio->supervisoresUtilizadores(),
                'Estado do estágio atualizado',
                "O estágio #{$estagio->id_estagio} passou de {$estadoAtual} para {$novoEstado}."
            );
        }

        return response()->json([
            'message' => 'Estado do estágio atualizado com sucesso.',

            'data' => $estagio
                ->fresh()
                ->load('estagiario'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Localizar a conta do estagiário
    |--------------------------------------------------------------------------
    |
    | A procura faz-se por id_utilizador. O e-mail só é usado para
    | candidaturas antigas ainda sem a chave preenchida.
    |
    */

    private function calcularDuracaoMeses(
        string $inicio,
        string $fimPrevisto
    ): int {
        $meses = Carbon::parse($inicio)
            ->startOfDay()
            ->diffInMonths(Carbon::parse($fimPrevisto)->startOfDay());

        return max(1, (int) $meses);
    }

    private function contaDoEstagiario(
        Candidatura $candidatura
    ): ?User {

        if ($candidatura->id_utilizador !== null) {
            return User::query()
                ->where('id_utilizador', $candidatura->id_utilizador)
                ->first();
        }

        return User::query()
            ->where('email', $candidatura->email)
            ->first();
    }
}
