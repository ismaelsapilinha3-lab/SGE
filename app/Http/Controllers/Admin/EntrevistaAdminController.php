<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidatura;
use App\Models\Entrevista;
use App\Services\NotificacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EntrevistaAdminController extends Controller
{
    public function __construct(
        private readonly NotificacaoService $notificacoes
    ) {}

    /**
     * Consultar a entrevista de uma candidatura.
     */
    public function show(int $idCandidatura): JsonResponse
    {
        $candidatura = Candidatura::query()
            ->where('id_candidatura', $idCandidatura)
            ->first();

        if (! $candidatura) {
            return response()->json([
                'message' => 'Candidatura não encontrada.',
            ], 404);
        }

        $entrevista = $candidatura->entrevista()
            ->with($this->relacoes())
            ->first();

        if (! $entrevista) {
            return response()->json([
                'message' => 'Ainda não existe entrevista para esta candidatura.',
            ], 404);
        }

        Gate::authorize('view', $entrevista);

        return response()->json([
            'entrevista' => $entrevista,
        ]);
    }

    /**
     * Agendar uma entrevista para uma candidatura em análise.
     */
    public function store(
        Request $request,
        int $idCandidatura
    ): JsonResponse {
        $candidatura = Candidatura::query()
            ->where('id_candidatura', $idCandidatura)
            ->first();

        if (! $candidatura) {
            return response()->json([
                'message' => 'Candidatura não encontrada.',
            ], 404);
        }

        if ($candidatura->estado !== 'em_analise') {
            return response()->json([
                'message' => 'Só é possível agendar entrevistas para candidaturas em análise.',
            ], 422);
        }

        if ($candidatura->entrevista()->exists()) {
            return response()->json([
                'message' => 'Esta candidatura já tem uma entrevista agendada.',
            ], 422);
        }

        Gate::authorize('agendar', [Entrevista::class, $candidatura]);

        $dados = $this->validarAgendamento($request);

        $entrevista = Entrevista::create([
            'id_candidatura' => $candidatura->id_candidatura,
            'id_administrador' => $request->user()->id_utilizador,
            'id_supervisor_representante' => $dados['id_supervisor_representante'],
            'data_hora' => $dados['data_hora'],
            'resultado' => 'pendente',
            'motivo_reprovacao' => null,
        ]);

        $this->notificarAgendamento($entrevista, $candidatura, false);

        return response()->json([
            'message' => 'Entrevista agendada com sucesso.',
            'entrevista' => $this->carregar($entrevista),
        ], 201);
    }

    /**
     * Reagendar uma entrevista ainda pendente.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $entrevista = Entrevista::query()
            ->with('candidatura')
            ->where('id_entrevista', $id)
            ->first();

        if (! $entrevista) {
            return response()->json([
                'message' => 'Entrevista não encontrada.',
            ], 404);
        }

        $candidatura = $entrevista->candidatura;

        if (! $candidatura || $candidatura->estado !== 'em_analise') {
            return response()->json([
                'message' => 'Só é possível reagendar entrevistas de candidaturas em análise.',
            ], 422);
        }

        if ($entrevista->estaDecidida()) {
            return response()->json([
                'message' => 'Esta entrevista já tem resultado registado.',
            ], 422);
        }

        Gate::authorize('atualizar', $entrevista);

        $dados = $this->validarAgendamento($request);

        $entrevista->update([
            'id_supervisor_representante' => $dados['id_supervisor_representante'],
            'data_hora' => $dados['data_hora'],
        ]);

        $this->notificarAgendamento($entrevista, $candidatura, true);

        return response()->json([
            'message' => 'Entrevista reagendada com sucesso.',
            'entrevista' => $this->carregar($entrevista),
        ]);
    }

    /**
     * Registar o resultado da entrevista.
     */
    public function resultado(Request $request, int $id): JsonResponse
    {
        $entrevista = Entrevista::query()
            ->with('candidatura')
            ->where('id_entrevista', $id)
            ->first();

        if (! $entrevista) {
            return response()->json([
                'message' => 'Entrevista não encontrada.',
            ], 404);
        }

        $candidatura = $entrevista->candidatura;

        if (! $candidatura || $candidatura->estado !== 'em_analise') {
            return response()->json([
                'message' => 'Só é possível avaliar entrevistas de candidaturas em análise.',
            ], 422);
        }

        if ($entrevista->estaDecidida()) {
            return response()->json([
                'message' => 'Esta entrevista já tem resultado registado.',
            ], 422);
        }

        Gate::authorize('registarResultado', $entrevista);

        $dados = $request->validate([
            'resultado' => [
                'required',
                Rule::in(['aprovado', 'reprovado']),
            ],
            'motivo_reprovacao' => [
                'nullable',
                'string',
                'min:5',
                'max:2000',
                'required_if:resultado,reprovado',
            ],
        ], [
            'resultado.required' => 'Indica o resultado da entrevista.',
            'resultado.in' => 'O resultado deve ser aprovado ou reprovado.',
            'motivo_reprovacao.required_if' => 'Indica o motivo da reprovação.',
            'motivo_reprovacao.min' => 'O motivo deve ter pelo menos 5 caracteres.',
        ]);

        $entrevista->update([
            'resultado' => $dados['resultado'],
            'motivo_reprovacao' => $dados['resultado'] === 'reprovado'
                ? $dados['motivo_reprovacao']
                : null,
        ]);

        $this->notificarResultado($entrevista, $candidatura);

        return response()->json([
            'message' => 'Resultado da entrevista registado com sucesso.',
            'entrevista' => $this->carregar($entrevista),
        ]);
    }

    /**
     * @return array{data_hora: string, id_supervisor_representante: int}
     */
    private function validarAgendamento(Request $request): array
    {
        return $request->validate([
            'data_hora' => [
                'required',
                'date',
                'after:now',
            ],
            'id_supervisor_representante' => [
                'required',
                'integer',
                Rule::exists('utilizadores', 'id_utilizador')
                    ->where('tipo_utilizador', 'supervisor')
                    ->where('ativo', true),
            ],
        ], [
            'data_hora.required' => 'Indica a data e a hora da entrevista.',
            'data_hora.date' => 'A data e a hora da entrevista não são válidas.',
            'data_hora.after' => 'A entrevista tem de ser agendada para o futuro.',
            'id_supervisor_representante.required' => 'Seleciona o supervisor representante.',
            'id_supervisor_representante.exists' => 'O supervisor selecionado não está ativo.',
        ]);
    }

    private function notificarAgendamento(
        Entrevista $entrevista,
        Candidatura $candidatura,
        bool $reagendada
    ): void {
        $quando = $entrevista->data_hora->format('d/m/Y')
            .' às '
            .$entrevista->data_hora->format('H:i');

        $titulo = $reagendada
            ? 'Entrevista reagendada'
            : 'Entrevista agendada';

        $acao = $reagendada ? 'reagendada' : 'agendada';

        $this->notificacoes->paraCandidato(
            $candidatura,
            $titulo,
            "A tua entrevista foi {$acao} para {$quando}.",
            '/estagiario'
        );

        $this->notificacoes->paraUtilizador(
            $entrevista->supervisorRepresentante,
            $titulo,
            "A entrevista de {$candidatura->nome} foi {$acao} para {$quando}.",
            '/supervisor?secao=entrevistas'
        );
    }

    private function notificarResultado(
        Entrevista $entrevista,
        Candidatura $candidatura
    ): void {
        $aprovada = $entrevista->resultado === 'aprovado';

        $titulo = $aprovada
            ? 'Entrevista aprovada'
            : 'Entrevista reprovada';

        $mensagemCandidato = $aprovada
            ? 'A tua entrevista foi aprovada. A candidatura continua em análise para decisão final.'
            : "A tua entrevista foi reprovada. Motivo: {$entrevista->motivo_reprovacao}.";

        $this->notificacoes->paraCandidato(
            $candidatura,
            $titulo,
            $mensagemCandidato,
            '/estagiario'
        );

        $mensagemEquipa = $aprovada
            ? "A entrevista de {$candidatura->nome} foi aprovada."
            : "A entrevista de {$candidatura->nome} foi reprovada. Motivo: {$entrevista->motivo_reprovacao}.";

        $this->notificacoes->paraUtilizador(
            $entrevista->supervisorRepresentante,
            $titulo,
            $mensagemEquipa,
            '/supervisor?secao=entrevistas'
        );

        $this->notificacoes->paraUtilizador(
            $candidatura->id_coordenador,
            'Resultado de entrevista',
            $mensagemEquipa,
            '/coordenador'
        );
    }

    private function carregar(Entrevista $entrevista): Entrevista
    {
        return $entrevista->load($this->relacoes());
    }

    /**
     * @return array<int, string>
     */
    private function relacoes(): array
    {
        return [
            'administrador:id_utilizador,nome,email',
            'supervisorRepresentante:id_utilizador,nome,email',
        ];
    }
}
