<?php

namespace App\Http\Controllers;

use App\Http\Resources\DocumentoEstagioResource;
use App\Models\Assiduidade;
use App\Models\AtividadeEstagiario;
use App\Models\DocumentoEstagio;
use App\Models\Estagio;
use App\Models\FeedbackEstagio;
use App\Models\Relatorio;
use App\Models\User;
use App\Services\NotificacaoService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class EstagiarioController extends Controller
{
    public function __construct(
        private readonly NotificacaoService $notificacoes
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Estágio do estagiário autenticado
    |--------------------------------------------------------------------------
    */

    public function estagio(Request $request)
    {
        // Consulta permitida antes do início: mostra as datas do estágio.
        $estagio = $this->estagioDoEstagiario($request->user(), false);

        $estagio->load([
            'candidatura.departamento',

            'documentos' => fn ($q) => $q->orderByDesc('versao'),

            'relatorios' => fn ($q) => $q->orderByDesc('periodo_referencia'),
        ]);

        return response()->json([
            'estagio' => [
                ...$estagio->toArray(),
                'documentos' => DocumentoEstagioResource::collection(
                    $estagio->documentos
                )->resolve(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Carregar o contrato de estágio
    |--------------------------------------------------------------------------
    |
    | Cada envio cria uma nova versão. A versão anterior mantém-se para
    | efeitos de histórico.
    |
    */

    public function submeterContrato(Request $request)
    {
        $utilizador = $request->user();

        // O contrato faz parte da preparação e pode ser enviado antes do início.
        $estagio = $this->estagioDoEstagiario($utilizador, false);

        if (! $estagio->aceitaRegistos()) {
            return response()->json([
                'message' => 'Só é possível carregar o contrato de um estágio ativo.',
            ], 422);
        }

        $validated = $request->validate([
            'ficheiro' => [
                'required',
                'file',
                'max:5120',
                'mimes:pdf,jpg,jpeg,png',
            ],
        ]);

        $ficheiro = $validated['ficheiro'];

        $disk = Storage::disk('local');

        $caminhoNovo = null;

        try {
            $documento = DB::transaction(function () use (
                $estagio,
                $utilizador,
                $ficheiro,
                &$caminhoNovo
            ) {
                $estagioBloqueado = Estagio::where(
                    'id_estagio',
                    $estagio->id_estagio
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $estagioBloqueado->aceitaRegistos()) {
                    abort(
                        422,
                        'Só é possível carregar o contrato de um estágio ativo.'
                    );
                }

                $ultimaVersao = (int) DocumentoEstagio::where(
                    'id_estagio',
                    $estagio->id_estagio
                )
                    ->where('tipo_documento', 'contrato')
                    ->max('versao');

                $versaoNova = $ultimaVersao + 1;

                $pasta = 'estagios/'.
                    $estagio->id_estagio.
                    '/contrato';

                /*
                 * Guardar o novo ficheiro no disco local. O ficheiro
                 * anterior não é apagado.
                 */
                $caminhoNovo = $ficheiro->store($pasta, 'local');

                if (! $caminhoNovo) {
                    throw new RuntimeException(
                        'Não foi possível guardar o contrato.'
                    );
                }

                return DocumentoEstagio::create([
                    'id_estagio' => $estagio->id_estagio,

                    'id_estagiario' => $utilizador->id_utilizador,

                    'tipo_documento' => 'contrato',

                    'versao' => $versaoNova,

                    'nome_arquivo' => $ficheiro->getClientOriginalName(),

                    'caminho_arquivo' => $caminhoNovo,

                    'data_upload' => now(),

                    'validade' => null,

                    'estado' => 'pendente',

                    'descricao' => null,
                ]);
            });

            $this->notificacoes->paraUtilizadores(
                $estagio->supervisoresUtilizadores(),
                'Contrato submetido',
                "O estagiário {$utilizador->nome} submeteu o contrato (versão {$documento->versao}) para validação.",
                "/supervisor?estagio={$estagio->id_estagio}&aba=contrato"
            );

            return response()->json([
                'message' => 'Contrato carregado com sucesso.',

                'documento' => new DocumentoEstagioResource($documento),
            ], 201);

        } catch (Throwable $e) {
            /*
             * Se a transacção falhar, remover apenas o novo
             * ficheiro criado nesta tentativa.
             */
            if ($caminhoNovo) {
                $disk->delete($caminhoNovo);
            }

            if ($e instanceof HttpException) {
                throw $e;
            }

            report($e);

            return response()->json([
                'message' => 'Não foi possível carregar o contrato.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Relatórios de estágio
    |--------------------------------------------------------------------------
    */

    public function relatorios(Request $request)
    {
        $estagio = $this->estagioDoEstagiario($request->user());

        return response()->json([
            'relatorios' => $estagio->relatorios()
                ->orderByDesc('periodo_referencia')
                ->get(),
        ]);
    }

    public function criarRelatorio(Request $request)
    {
        $estagio = $this->estagioDoEstagiario($request->user());

        $dados = $this->validarRelatorio($request, $estagio);

        if (! $estagio->aceitaRelatoriosNoMes($dados['periodo_referencia'])) {
            return response()->json([
                'message' => 'Só é possível criar relatórios de um estágio ativo ou concluído dentro do prazo.',
            ], 422);
        }

        $mes = Carbon::parse($dados['periodo_referencia'])->startOfMonth();

        $relatorio = $estagio->relatorios()
            ->where('tipo', $dados['tipo'])
            ->whereDate('mes_referencia', $mes->toDateString())
            ->first();

        if ($relatorio && ! $relatorio->estaRejeitado()
            && $relatorio->estado_avaliacao !== Relatorio::ESTADO_EM_ATRASO) {
            return response()->json([
                'message' => 'Já existe um relatório deste tipo para este período.',
            ], 422);
        }

        $atributos = $dados + $this->dadosDeEntrega(
            $estagio,
            $dados['periodo_referencia']
        );

        if ($relatorio) {
            $relatorio->update(
                $atributos + $this->dadosReabertura($relatorio)
            );

            return response()->json([
                'message' => 'Relatório atualizado com sucesso.',

                'relatorio' => $relatorio->fresh(),
            ]);
        }

        try {
            $relatorio = $estagio->relatorios()->create($atributos);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Já existe um relatório deste tipo para este período.',
            ], 422);
        }

        return response()->json([
            'message' => 'Relatório criado com sucesso.',

            'relatorio' => $relatorio,
        ], 201);
    }

    public function atualizarRelatorio(
        Request $request,
        Relatorio $relatorio
    ) {

        $estagio = $this->relatorioDoEstagiario(
            $request->user(),
            $relatorio
        );

        if (! $relatorio->podeSerAlterado()) {
            return response()->json([
                'message' => 'Um relatório aprovado ou já submetido não pode ser alterado.',
            ], 422);
        }

        $dados = $this->validarRelatorio($request, $estagio);

        if (! $estagio->aceitaRelatoriosNoMes($dados['periodo_referencia'])) {
            return response()->json([
                'message' => 'Só é possível alterar relatórios de um estágio ativo ou concluído dentro do prazo.',
            ], 422);
        }

        $mes = Carbon::parse($dados['periodo_referencia'])->startOfMonth();

        $existe = $estagio->relatorios()
            ->where('id_relatorio', '!=', $relatorio->id_relatorio)
            ->where('tipo', $dados['tipo'])
            ->whereDate('mes_referencia', $mes->toDateString())
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Já existe um relatório deste tipo para este período.',
            ], 422);
        }

        $relatorio->update(
            $dados
                + $this->dadosDeEntrega(
                    $estagio,
                    $dados['periodo_referencia']
                )
                + $this->dadosReabertura($relatorio)
        );

        return response()->json([
            'message' => 'Relatório atualizado com sucesso.',

            'relatorio' => $relatorio->fresh(),
        ]);
    }

    public function submeterRelatorio(
        Request $request,
        Relatorio $relatorio
    ) {

        $estagio = $this->relatorioDoEstagiario(
            $request->user(),
            $relatorio
        );

        if ($relatorio->estaAprovado()) {
            return response()->json([
                'message' => 'Este relatório já foi aprovado pelo administrador.',
            ], 422);
        }

        if (! $relatorio->podeSerAlterado()) {
            return response()->json([
                'message' => 'Este relatório já foi submetido.',
            ], 422);
        }

        $mes = $relatorio->mes_referencia?->toDateString()
            ?? $relatorio->periodo_referencia?->toDateString();

        if (! $mes || ! $estagio->aceitaRelatoriosNoMes($mes)) {
            return response()->json([
                'message' => 'O estágio não aceita relatórios para este período.',
            ], 422);
        }

        $relatorio->update([
            'submetido_em' => now(),
            'enviado_em' => now(),
            'estado_avaliacao' => Relatorio::ESTADO_PENDENTE,
            'comentario_admin' => null,
            'alerta_atraso_enviado_em' => null,
        ]);

        $this->notificacoes->paraUtilizadores(
            $estagio->supervisoresUtilizadores(),
            'Relatório submetido',
            'O estagiário submeteu o relatório '.$relatorio->tipo
                .' referente a '
                .$relatorio->periodo_referencia->format('d/m/Y').'.',
            "/supervisor?estagio={$estagio->id_estagio}&aba=relatorios"
        );

        return response()->json([
            'message' => 'Relatório submetido com sucesso.',

            'relatorio' => $relatorio->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Assiduidade do estagiário
    |--------------------------------------------------------------------------
    */

    public function assiduidades(Request $request)
    {
        $estagio = $this->estagioDoEstagiario($request->user());

        return response()->json([
            'assiduidades' => $estagio->assiduidades()
                ->orderByDesc('data')
                ->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Justificar uma falta
    |--------------------------------------------------------------------------
    |
    | O estagiário submete a justificação de um dia marcado como falta; a
    | mesma fica pendente até o supervisor a aceitar ou recusar.
    |
    */

    public function justificarFalta(
        Request $request,
        Assiduidade $assiduidade
    ) {

        $estagio = $this->assiduidadeDoEstagiario(
            $request->user(),
            $assiduidade
        );

        if (! $estagio->aceitaRegistos()) {
            return response()->json([
                'message' => 'Só é possível justificar faltas de um estágio ativo.',
            ], 422);
        }

        if (! $assiduidade->podeSubmeterJustificacao()) {
            return response()->json([
                'message' => 'Esta falta não pode ser justificada.',
            ], 422);
        }

        $dados = $request->validate([
            'justificacao' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ]);

        $assiduidade->update([
            'justificacao' => $dados['justificacao'],

            'justificacao_estado' => 'pendente',

            'motivo_recusa' => null,

            'id_supervisor_justificacao' => null,

            'justificacao_submetida_em' => now(),

            'justificacao_decidida_em' => null,
        ]);

        $this->notificacoes->paraUtilizadores(
            $estagio->supervisoresUtilizadores(),
            'Falta justificada',
            'O estagiário submeteu uma justificação para a falta de '
                .$assiduidade->data->format('d/m/Y').'.',
            "/supervisor?estagio={$estagio->id_estagio}&aba=assiduidade"
        );

        return response()->json([
            'message' => 'Justificação submetida para validação.',

            'assiduidade' => $assiduidade->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Atividades do estagiário
    |--------------------------------------------------------------------------
    */

    public function atividades(Request $request)
    {
        $estagio = $this->estagioDoEstagiario($request->user());

        return response()->json([
            'atividades' => $estagio->atividades()
                ->orderByDesc('data')
                ->orderByDesc('id_atividade')
                ->get(),
        ]);
    }

    public function criarAtividade(Request $request)
    {
        $estagio = $this->estagioDoEstagiario($request->user());

        if (! $estagio->aceitaRegistos()) {
            return response()->json([
                'message' => 'Só é possível registar atividades de um estágio ativo.',
            ], 422);
        }

        $dados = $this->validarAtividade($request, $estagio);

        $atividade = $estagio->atividades()->create($dados);

        return response()->json([
            'message' => 'Atividade registada com sucesso.',

            'atividade' => $atividade,
        ], 201);
    }

    public function atualizarAtividade(
        Request $request,
        AtividadeEstagiario $atividade
    ) {

        $estagio = $this->atividadeDoEstagiario(
            $request->user(),
            $atividade
        );

        if (! $estagio->aceitaRegistos()) {
            return response()->json([
                'message' => 'Só é possível alterar atividades de um estágio ativo.',
            ], 422);
        }

        $dados = $this->validarAtividade($request, $estagio);

        $atividade->update($dados);

        return response()->json([
            'message' => 'Atividade atualizada com sucesso.',

            'atividade' => $atividade->fresh(),
        ]);
    }

    public function removerAtividade(
        Request $request,
        AtividadeEstagiario $atividade
    ) {

        $estagio = $this->atividadeDoEstagiario(
            $request->user(),
            $atividade
        );

        if (! $estagio->aceitaRegistos()) {
            return response()->json([
                'message' => 'Só é possível remover atividades de um estágio ativo.',
            ], 422);
        }

        $atividade->delete();

        return response()->json([
            'message' => 'Atividade removida com sucesso.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Avaliações recebidas e feedback do estagiário
    |--------------------------------------------------------------------------
    */

    public function avaliacoes(Request $request)
    {
        $estagio = $this->estagioDoEstagiario($request->user());

        return response()->json([
            'avaliacoes' => $estagio->avaliacoes()
                ->orderByDesc('periodo_referencia')
                ->get(),
        ]);
    }

    public function feedback(Request $request)
    {
        $estagio = $this->estagioDoEstagiario($request->user());

        return response()->json([
            'feedback' => $estagio->feedback,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Registar ou atualizar o feedback do estagiário sobre o estágio
    |--------------------------------------------------------------------------
    |
    | Só existe um feedback por estágio, pelo que enviar de novo atualiza
    | o registo existente.
    |
    */

    public function guardarFeedback(Request $request)
    {
        $estagio = $this->estagioDoEstagiario($request->user());

        if (! $estagio->aceitaRegistosDeFecho()) {
            return response()->json([
                'message' => 'Não é possível avaliar um estágio cancelado.',
            ], 422);
        }

        $dados = $request->validate([
            'nota_geral' => [
                'required',
                'integer',
                'between:0,10',
            ],

            'comentario' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'sugestoes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        try {
            $feedback = FeedbackEstagio::updateOrCreate(
                ['id_estagio' => $estagio->id_estagio],
                [
                    'nota_geral' => $dados['nota_geral'],

                    'comentario' => $dados['comentario'] ?? null,

                    'sugestoes' => $dados['sugestoes'] ?? null,
                ]
            );
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Já existe feedback registado para este estágio.',
            ], 422);
        }

        $this->notificacoes->paraUtilizadores(
            $estagio->supervisoresUtilizadores(),
            'Feedback do estagiário',
            'O estagiário registou feedback sobre o estágio.',
            "/supervisor?estagio={$estagio->id_estagio}&aba=feedback"
        );

        return response()->json([
            'message' => $feedback->wasRecentlyCreated
                ? 'Feedback registado com sucesso.'
                : 'Feedback atualizado com sucesso.',

            'feedback' => $feedback,
        ], $feedback->wasRecentlyCreated ? 201 : 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Validação comum das atividades
    |--------------------------------------------------------------------------
    */

    private function validarAtividade(
        Request $request,
        Estagio $estagio
    ): array {

        $dados = $request->validate([
            'data' => [
                'required',
                'date',
            ],

            'titulo' => [
                'required',
                'string',
                'max:150',
            ],

            'descricao' => [
                'required',
                'string',
                'max:5000',
            ],
        ]);

        if (! $estagio->contemData($dados['data'])) {
            abort(response()->json([
                'message' => 'A data indicada está fora do período do estágio.',
            ], 422));
        }

        return $dados;
    }

    /*
    |--------------------------------------------------------------------------
    | Garantir que o registo de assiduidade pertence ao estágio do estagiário
    |--------------------------------------------------------------------------
    */

    private function assiduidadeDoEstagiario(
        User $utilizador,
        Assiduidade $assiduidade
    ): Estagio {

        $estagio = $this->estagioDoEstagiario($utilizador);

        if ((int) $assiduidade->id_estagio !== (int) $estagio->id_estagio) {
            abort(response()->json([
                'message' => 'Registo de assiduidade não encontrado.',
            ], 404));
        }

        return $estagio;
    }

    /*
    |--------------------------------------------------------------------------
    | Garantir que a atividade pertence ao estágio do estagiário
    |--------------------------------------------------------------------------
    */

    private function atividadeDoEstagiario(
        User $utilizador,
        AtividadeEstagiario $atividade
    ): Estagio {

        $estagio = $this->estagioDoEstagiario($utilizador);

        if ((int) $atividade->id_estagio !== (int) $estagio->id_estagio) {
            abort(response()->json([
                'message' => 'Atividade não encontrada.',
            ], 404));
        }

        return $estagio;
    }

    /*
    |--------------------------------------------------------------------------
    | Validação comum dos relatórios
    |--------------------------------------------------------------------------
    */

    /**
     * Datas de referência e prazo usadas pelo painel do administrador.
     *
     * Mantém as duas gerações de API de relatórios alinhadas: o período
     * indicado pelo estagiário passa a preencher também o mês de
     * referência e o prazo de entrega definido para o estágio.
     *
     * @return array{mes_referencia: string, prazo_envio: string, estado_avaliacao: string}
     */
    private function dadosDeEntrega(
        Estagio $estagio,
        string $periodoReferencia
    ): array {

        $mes = Carbon::parse($periodoReferencia)->startOfMonth();

        return [
            'mes_referencia' => $mes->toDateString(),

            'prazo_envio' => $estagio->prazoParaMes($mes)->toDateString(),

            'estado_avaliacao' => Relatorio::ESTADO_PENDENTE,
        ];
    }

    /**
     * Limpa a entrega anterior de um relatório recusado ou em atraso.
     *
     * O relatório volta a rascunho para que o estagiário o possa corrigir
     * e submeter novamente.
     *
     * @return array<string, mixed>
     */
    private function dadosReabertura(Relatorio $relatorio): array
    {
        if (! $relatorio->estaRejeitado()
            && $relatorio->estado_avaliacao !== Relatorio::ESTADO_EM_ATRASO) {
            return [];
        }

        return [
            'submetido_em' => null,
            'enviado_em' => null,
            'entrega_confirmada_em' => null,
            'comentario_admin' => null,
            'alerta_atraso_enviado_em' => null,
        ];
    }

    private function validarRelatorio(
        Request $request,
        Estagio $estagio
    ): array {

        $dados = $request->validate([
            'tipo' => [
                'required',
                'in:semanal,mensal,final',
            ],

            'periodo_referencia' => [
                'required',
                'date',
            ],

            'atividades' => [
                'required',
                'string',
                'max:5000',
            ],

            'resultados' => [
                'required',
                'string',
                'max:5000',
            ],

            'constrangimentos' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'objetivos' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        if (! $estagio->contemMes($dados['periodo_referencia'])) {
            throw ValidationException::withMessages([
                'periodo_referencia' => 'O período indicado está fora do período do estágio.',
            ]);
        }

        if (Carbon::parse($dados['periodo_referencia'])
            ->startOfMonth()
            ->greaterThan(now()->startOfMonth())) {
            throw ValidationException::withMessages([
                'periodo_referencia' => 'O período indicado não pode ser futuro.',
            ]);
        }

        return $dados;
    }

    /*
    |--------------------------------------------------------------------------
    | Estágio associado ao estagiário autenticado
    |--------------------------------------------------------------------------
    */

    private function estagioDoEstagiario(
        User $utilizador,
        bool $exigeInicio = true
    ): Estagio {
        $estagio = Estagio::where(
            'id_estagiario',
            $utilizador->id_utilizador
        )
            ->latest('id_estagio')
            ->first();

        if (
            ! $estagio
            || ! Gate::forUser($utilizador)->allows('view', $estagio)
        ) {
            abort(response()->json([
                'message' => 'Não foi encontrado nenhum estágio associado a este estagiário.',
            ], 404));
        }

        /*
         * Os registos de acompanhamento (relatórios, assiduidade, atividades,
         * avaliações e feedback) só ficam disponíveis a partir do dia de
         * início. A consulta do estágio e o envio do contrato continuam
         * acessíveis antes dessa data.
         */
        if ($exigeInicio && ! $estagio->jaComecou()) {
            abort(response()->json([
                'message' => 'O estágio ainda não começou.',
                'data_inicio' => $estagio->data_inicio?->format('Y-m-d'),
            ], 403));
        }

        return $estagio;
    }

    /*
    |--------------------------------------------------------------------------
    | Garantir que o relatório pertence ao estágio do estagiário
    |--------------------------------------------------------------------------
    */

    private function relatorioDoEstagiario(
        User $utilizador,
        Relatorio $relatorio
    ): Estagio {

        $estagio = $this->estagioDoEstagiario($utilizador);

        if ((int) $relatorio->id_estagio !== (int) $estagio->id_estagio) {
            abort(response()->json([
                'message' => 'Relatório não encontrado.',
            ], 404));
        }

        return $estagio;
    }

    private function converterEstado(string $estado): string
    {
        return match ($estado) {
            'em_analise' => 'analise',
            'aprovada' => 'aceite',
            'recusada' => 'recusado',
            default => 'analise',
        };
    }
}
