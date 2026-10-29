<?php

namespace App\Http\Controllers;

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Documento;
use App\Models\Estagio;
use App\Models\Notificacao;
use App\Models\PlanoEstagio;
use App\Models\Relatorio;
use App\Models\User;
use App\Services\AcessoFicheiroService;
use App\Services\NotificacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RelatorioController extends Controller
{
    public function __construct(
        private readonly NotificacaoService $notificacoes,
        private readonly AcessoFicheiroService $acessos
    ) {}

    /**
     * Formatos aceites no relatório em ficheiro.
     */
    private const FORMATOS_RELATORIO = [
        'pdf',
        'doc',
        'docx',
    ];

    /**
     * Tamanho máximo do ficheiro do relatório (10 MB).
     */
    private const TAMANHO_MAXIMO_RELATORIO = 10240;

    /*
    |--------------------------------------------------------------------------
    | Área do estagiário
    |--------------------------------------------------------------------------
    */

    /**
     * Dados completos do estágio ativo do estagiário autenticado:
     * candidatura, departamento, plano de estágio, relatórios e avisos.
     */
    public function painel(Request $request)
    {
        $utilizador = $request->user();

        if (! $utilizador || $utilizador->tipo_utilizador !== 'estagiario') {
            return response()->json([
                'message' => 'Apenas estagiários podem consultar os seus relatórios.',
            ], 403);
        }

        $estagio = $this->estagioParaConsulta($utilizador);

        if (! $estagio) {
            return response()->json([
                'message' => 'Não existe um estágio associado à sua conta.',
            ], 404);
        }

        $candidatura = Candidatura::query()
            ->with('departamento')
            ->find($estagio->id_candidatura);

        $departamento = $candidatura?->departamento
            ?? Departamento::find($candidatura?->id_departamento_selecionado);

        /*
         * Documentos da candidatura: apenas a versão mais recente de
         * cada tipo, para o estagiário poder consultar e substituir.
         */
        $documentos = $candidatura
            ? Documento::query()
                ->where('id_candidatura', $candidatura->id_candidatura)
                ->orderBy('tipo_documento')
                ->orderByDesc('versao')
                ->get()
                ->unique('tipo_documento')
                ->values()
            : collect();

        $relatorios = Relatorio::query()
            ->where('id_estagio', $estagio->id_estagio)
            ->orderByDesc('mes_referencia')
            ->get();

        $planos = PlanoEstagio::query()
            ->where('id_estagio', $estagio->id_estagio)
            ->doAdministrador()
            ->whereNotNull('caminho_arquivo')
            ->orderByDesc('data_upload')
            ->get();

        return response()->json([
            'message' => 'Dados do estágio carregados com sucesso.',

            'estagio' => [
                'id_estagio' => $estagio->id_estagio,
                'id_candidatura' => $estagio->id_candidatura,
                'estado_estagio' => $estagio->estado,
                'estado_estagio_legivel' => $this->rotuloEstado($estagio->estado),
                'data_inicio' => $estagio->data_inicio?->toDateString(),
                'data_fim_prevista' => $estagio->data_fim_prevista?->toDateString(),
                'duracao_meses' => $estagio->duracao_meses,
                'dia_limite_relatorio' => $estagio->diaLimiteRelatorio(),
                'motivo_suspensao' => $estagio->motivo_suspensao,
            ],

            'candidatura' => $candidatura
                ? [
                    'id_candidatura' => $candidatura->id_candidatura,
                    'nome' => $candidatura->nome,
                    'email' => $candidatura->email,
                    'bi' => $candidatura->bi,
                    'contacto' => $candidatura->contacto,
                    'nascimento' => $candidatura->nascimento?->toDateString(),
                    'sexo' => $candidatura->sexo,
                    'curso' => $candidatura->curso,
                    'universidade' => $candidatura->universidade,
                    'estado' => $candidatura->estado,
                ]
                : null,

            'departamento' => $departamento
                ? [
                    'id_departamento' => $departamento->id_departamento,
                    'nome' => $departamento->nome,
                    'descricao' => $departamento->descricao,
                ]
                : null,

            'documentos' => $documentos
                ->map(fn (Documento $documento) => [
                    'id_documento' => $documento->id_documento,
                    'tipo_documento' => $documento->tipo_documento,
                    'tipo_legivel' => $this->rotuloDocumento($documento->tipo_documento),
                    'versao' => $documento->versao,
                    'nome_arquivo' => $documento->nome_arquivo,
                    'data_upload' => $documento->data_upload?->toDateTimeString(),
                    'data_upload_legivel' => $documento->data_upload?->format('d/m/Y H:i'),
                    'estado' => $documento->estado,
                    'descricao' => $documento->descricao,
                    'url' => "/api/documentos/{$documento->id_documento}",
                    'pode_substituir' => $candidatura?->estado === 'em_analise',
                ])
                ->values(),

            'plano_estagio' => $planos->first()
                ? $this->formatarPlano($planos->first())
                : null,

            'planos_anteriores' => $planos
                ->skip(1)
                ->values()
                ->map(fn (PlanoEstagio $plano) => $this->formatarPlano($plano)),

            'relatorios' => $relatorios
                ->map(fn (Relatorio $relatorio) => $this->formatarRelatorio($relatorio))
                ->values(),

            'alertas' => $this->alertas($utilizador, $relatorios),

            'notificacoes' => $this->notificacoesRecentes($utilizador),
        ]);
    }

    /**
     * Lista os relatórios do estágio ativo do estagiário autenticado.
     */
    public function meusRelatorios(Request $request)
    {
        $utilizador = $request->user();

        if (! $utilizador || $utilizador->tipo_utilizador !== 'estagiario') {
            return response()->json([
                'message' => 'Apenas estagiários podem consultar os seus relatórios.',
            ], 403);
        }

        $estagio = $this->estagioParaConsulta($utilizador);

        if (! $estagio) {
            return response()->json([
                'message' => 'Não existe um estágio associado à sua conta.',
            ], 404);
        }

        $relatorios = Relatorio::query()
            ->where('id_estagio', $estagio->id_estagio)
            ->orderByDesc('mes_referencia')
            ->get();

        return response()->json([
            'data' => $relatorios
                ->map(fn (Relatorio $relatorio) => $this->formatarRelatorio($relatorio))
                ->values(),
            'alertas' => $this->alertas($utilizador, $relatorios),
        ]);
    }

    /**
     * Relatórios em atraso no estágio ativo do estagiário.
     *
     * Serve o aviso mostrado na área reservada e no painel do
     * administrador (o administrador usa o mesmo cálculo nos
     * relatórios listados por ele).
     */
    public function atrasos(Request $request)
    {
        $utilizador = $request->user();

        if (! $utilizador || $utilizador->tipo_utilizador !== 'estagiario') {
            return response()->json([
                'message' => 'Apenas estagiários podem consultar os seus atrasos.',
            ], 403);
        }

        $estagio = $this->estagioParaConsulta($utilizador);

        if (! $estagio) {
            return response()->json([
                'message' => 'Não existe um estágio associado à sua conta.',
            ], 404);
        }

        $relatorios = Relatorio::query()
            ->where('id_estagio', $estagio->id_estagio)
            ->get();

        $atrasados = $relatorios
            ->filter(fn (Relatorio $relatorio) => $relatorio->emAtraso())
            ->map(fn (Relatorio $relatorio) => $this->formatarRelatorio($relatorio))
            ->values();

        return response()->json([
            'total' => $atrasados->count(),
            'data' => $atrasados,
        ]);
    }

    /**
     * Primeira forma de envio: formulário preenchido no portal.
     */
    public function guardarRelatorio(Request $request)
    {
        $utilizador = $request->user();

        if (! $utilizador || $utilizador->tipo_utilizador !== 'estagiario') {
            return response()->json([
                'message' => 'Apenas estagiários podem submeter relatórios.',
            ], 403);
        }

        $dados = $request->validate([
            'tipo' => ['required', Rule::in(['semanal', 'mensal', 'final'])],
            'mes_referencia' => ['required', 'date'],
            'atividades' => ['required', 'string', 'min:10'],
            'resultados' => ['required', 'string', 'min:10'],
            'constrangimentos' => ['nullable', 'string'],
            'objetivos' => ['nullable', 'string'],
        ], [
            'mes_referencia.required' => 'Indica o mês a que o relatório diz respeito.',
            'atividades.required' => 'Descreve as actividades realizadas.',
            'resultados.required' => 'Descreve os resultados alcançados.',
        ]);

        $estagio = $this->estagioParaRelatorios($utilizador);

        if (! $estagio) {
            return response()->json([
                'message' => 'Não existe um estágio associado à sua conta.',
            ], 404);
        }

        if ($estagio->estado === 'suspenso') {
            return response()->json([
                'message' => 'O estágio está suspenso. Não é possível enviar relatórios.',
            ], 422);
        }

        $mes = Carbon::parse($dados['mes_referencia'])->startOfMonth();

        if ($this->mesNoFuturo($mes)) {
            return response()->json([
                'message' => 'Não é possível enviar relatórios de um mês futuro.',
            ], 422);
        }

        if (! $estagio->aceitaRelatoriosNoMes($mes)) {
            return response()->json([
                'message' => 'O estágio não aceita relatórios para esse mês.',
            ], 422);
        }

        $relatorio = Relatorio::query()
            ->where('id_estagio', $estagio->id_estagio)
            ->where('tipo', $dados['tipo'])
            ->whereDate('mes_referencia', $mes->toDateString())
            ->first();

        if ($relatorio && $relatorio->estaAprovado()) {
            return response()->json([
                'message' => 'Este relatório já foi aprovado pelo administrador.',
            ], 422);
        }

        if ($relatorio && ! $relatorio->podeSerAlterado()) {
            return response()->json([
                'message' => 'Este relatório já foi submetido e aguarda avaliação.',
            ], 422);
        }

        $relatorio = DB::transaction(function () use ($dados, $estagio, $mes, $relatorio) {
            $atributos = [
                ...$dados,
                'mes_referencia' => $mes->toDateString(),
                'periodo_referencia' => $mes->toDateString(),
                'prazo_envio' => $estagio->prazoParaMes($mes)->toDateString(),
                'submetido_em' => now(),
                'enviado_em' => now(),
                'estado_avaliacao' => Relatorio::ESTADO_PENDENTE,
                'comentario_admin' => null,
                'alerta_atraso_enviado_em' => null,
            ];

            if ($relatorio) {
                $relatorio->update($atributos);

                return $relatorio;
            }

            return Relatorio::create([
                ...$atributos,
                'id_estagio' => $estagio->id_estagio,
            ]);
        });

        $this->notificarAdmin(
            'Relatório submetido',
            sprintf(
                '%s submeteu o relatório %s de %s.',
                $utilizador->nome,
                $relatorio->tipo,
                $mes->format('m/Y')
            ),
            '/admin?secao=relatorios'
        );

        return response()->json([
            'message' => 'Relatório submetido com sucesso.',
            'data' => $this->formatarRelatorio($relatorio),
        ], 201);
    }

    /**
     * Segunda forma de envio: ficheiro do relatório em PDF ou Word.
     */
    public function carregarRelatorio(Request $request)
    {
        $utilizador = $request->user();

        if (! $utilizador || $utilizador->tipo_utilizador !== 'estagiario') {
            return response()->json([
                'message' => 'Apenas estagiários podem carregar relatórios.',
            ], 403);
        }

        $dados = $request->validate([
            'tipo' => ['required', Rule::in(['semanal', 'mensal', 'final'])],
            'mes_referencia' => ['nullable', 'date'],
            'prazo_envio' => ['nullable', 'date'],
            'relatorio_id' => ['nullable', 'integer'],
            'ficheiro' => [
                'required',
                'file',
                'max:'.self::TAMANHO_MAXIMO_RELATORIO,
                'mimes:'.implode(',', self::FORMATOS_RELATORIO),
            ],
        ], [
            'ficheiro.required' => 'Selecciona o ficheiro do relatório.',
            'ficheiro.max' => 'O ficheiro do relatório não pode ultrapassar 10 MB.',
            'ficheiro.mimes' => 'O relatório deve estar em PDF ou Word (DOC/DOCX).',
        ]);

        $estagio = $this->estagioParaRelatorios($utilizador);

        if (! $estagio) {
            return response()->json([
                'message' => 'Não existe um estágio associado à sua conta.',
            ], 404);
        }

        if ($estagio->estado === 'suspenso') {
            return response()->json([
                'message' => 'O estágio está suspenso. Não é possível enviar relatórios.',
            ], 422);
        }

        $ficheiro = $dados['ficheiro'];

        $relatorio = null;
        $tipo = $dados['tipo'];

        if (! empty($dados['relatorio_id'])) {
            $relatorio = Relatorio::query()
                ->where('id_estagio', $estagio->id_estagio)
                ->where('id_relatorio', $dados['relatorio_id'])
                ->first();

            if (! $relatorio) {
                return response()->json([
                    'message' => 'Relatório não encontrado.',
                ], 404);
            }

            $tipo = $relatorio->tipo;
            $mes = $relatorio->mes_referencia
                ?->copy()
                ->startOfMonth()
                ?? Carbon::parse($relatorio->periodo_referencia)->startOfMonth();
        } else {
            $mes = $dados['mes_referencia']
                ? Carbon::parse($dados['mes_referencia'])->startOfMonth()
                : now()->startOfMonth();

            $relatorio = Relatorio::query()
                ->where('id_estagio', $estagio->id_estagio)
                ->where('tipo', $tipo)
                ->whereDate('mes_referencia', $mes->toDateString())
                ->first();
        }

        if ($this->mesNoFuturo($mes)) {
            return response()->json([
                'message' => 'Não é possível enviar relatórios de um mês futuro.',
            ], 422);
        }

        if ($relatorio?->estaAprovado()) {
            return response()->json([
                'message' => 'Este relatório já foi aprovado pelo administrador.',
            ], 422);
        }

        if (! $estagio->aceitaRelatoriosNoMes($mes)) {
            return response()->json([
                'message' => 'O estágio não aceita relatórios para esse mês.',
            ], 422);
        }

        /*
         * Sem relatório identificado, cria-se um novo registo para o
         * tipo e mês indicados.
         */
        if (! $relatorio) {
            $relatorio = Relatorio::create([
                'id_estagio' => $estagio->id_estagio,
                'tipo' => $tipo,
                'mes_referencia' => $mes->toDateString(),
                'periodo_referencia' => $mes->toDateString(),
                'prazo_envio' => $estagio->prazoParaMes($mes)->toDateString(),
                'atividades' => 'Relatório entregue em ficheiro ('.strtoupper($ficheiro->getClientOriginalExtension()).').',
                'resultados' => 'Consultar o ficheiro anexado.',
                'estado_avaliacao' => Relatorio::ESTADO_PENDENTE,
            ]);
        }

        $pasta = 'relatorios_estagio/'.$estagio->id_estagio;

        $caminhoAnterior = $relatorio->ficheiro_path;

        $caminhoNovo = $ficheiro->store($pasta, 'local');

        if (! $caminhoNovo) {
            return response()->json([
                'message' => 'Não foi possível guardar o ficheiro do relatório.',
            ], 500);
        }

        $relatorio->forceFill([
            'ficheiro_path' => $caminhoNovo,
            'ficheiro_nome' => $ficheiro->getClientOriginalName(),
            'submetido_em' => now(),
            'enviado_em' => now(),
            'entrega_confirmada_em' => now(),
            'estado_avaliacao' => Relatorio::ESTADO_PENDENTE,
            'alerta_atraso_enviado_em' => null,
            'comentario_admin' => null,
        ])->save();

        /*
         * O ficheiro anterior só é removido depois de guardar o novo.
         */
        if ($caminhoAnterior && $caminhoAnterior !== $caminhoNovo) {
            Storage::disk('local')->delete($caminhoAnterior);
        }

        $this->notificarAdmin(
            'Relatório submetido',
            sprintf(
                '%s submeteu o relatório %s de %s em ficheiro.',
                $utilizador->nome,
                $relatorio->tipo,
                $relatorio->mes_referencia?->format('m/Y') ?? '-'
            ),
            '/admin?secao=relatorios'
        );

        return response()->json([
            'message' => 'Relatório submetido com sucesso.',
            'data' => $this->formatarRelatorio($relatorio->refresh()),
        ], 201);
    }

    /**
     * Visualizar o ficheiro do relatório do próprio estagiário.
     */
    public function verFicheiro(Request $request, int $id)
    {
        $utilizador = $request->user();

        $relatorio = Relatorio::query()
            ->with('estagio')
            ->find($id);

        if (! $relatorio || ! $relatorio->estagio) {
            return response()->json([
                'message' => 'Relatório não encontrado.',
            ], 404);
        }

        if (
            ! $utilizador
            || ! Gate::forUser($utilizador)->allows('view', $relatorio)
        ) {
            return response()->json([
                'message' => 'Não tem autorização para consultar este relatório.',
            ], 403);
        }

        $this->acessos->registarRelatorio(
            $request,
            $utilizador,
            $relatorio
        );

        return $this->servirFicheiro(
            $relatorio->ficheiro_path,
            $relatorio->ficheiro_nome
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Gestão do plano de estágio pelo administrador
    |--------------------------------------------------------------------------
    */

    /**
     * Carrega (ou substitui) o plano de estágio de um estagiário.
     */
    public function carregarPlano(Request $request)
    {
        $dados = $request->validate([
            'id_estagio' => ['required', 'integer', 'exists:estagios,id_estagio'],
            'ficheiro' => [
                'required',
                'file',
                'max:'.self::TAMANHO_MAXIMO_RELATORIO,
                'mimes:'.implode(',', array_keys(PlanoEstagio::FORMATOS_ACEITES)),
            ],
        ], [
            'ficheiro.required' => 'Seleciona o documento do plano de estágio.',
            'ficheiro.max' => 'O plano não pode ultrapassar 10 MB.',
            'ficheiro.mimes' => 'O plano deve estar em PDF ou Word (DOC/DOCX).',
        ]);

        $estagio = Estagio::findOrFail($dados['id_estagio']);

        $ficheiro = $dados['ficheiro'];

        $formato = strtolower($ficheiro->getClientOriginalExtension());

        $versao = (int) PlanoEstagio::query()
            ->where('id_estagio', $estagio->id_estagio)
            ->max('versao') + 1;

        $pasta = 'planos_estagio/'.$estagio->id_estagio;

        $caminho = $ficheiro->store($pasta, 'local');

        if (! $caminho) {
            return response()->json([
                'message' => 'Não foi possível guardar o plano de estágio.',
            ], 500);
        }

        $plano = DB::transaction(function () use (
            $request,
            $estagio,
            $ficheiro,
            $formato,
            $versao,
            $caminho
        ) {
            /*
             * Os documentos oficiais anteriores deixam de estar em vigor.
             * Os planos de atividades dos supervisores não são afetados.
             */
            PlanoEstagio::query()
                ->where('id_estagio', $estagio->id_estagio)
                ->doAdministrador()
                ->update(['estado' => 'substituido']);

            return PlanoEstagio::create([
                'id_estagio' => $estagio->id_estagio,
                'origem' => PlanoEstagio::ORIGEM_ADMINISTRADOR,
                'nome_arquivo' => $ficheiro->getClientOriginalName(),
                'caminho_arquivo' => $caminho,
                'formato' => $formato,
                'tamanho' => $ficheiro->getSize(),
                'id_autor' => $request->user()?->id_utilizador,
                'versao' => $versao,
                'data_upload' => now(),
                'data_inicio' => $estagio->data_inicio,
                'data_fim' => $estagio->data_fim_prevista,
                'estado' => 'aprovado',
                'data_decisao' => now(),
            ]);
        });

        /*
         * O estagiário é avisado de que o plano está disponível.
         */
        if ($estagio->id_estagiario) {
            $this->notificacoes->paraUtilizador(
                $estagio->id_estagiario,
                'Plano de estágio disponível',
                'O teu plano de estágio foi carregado pela Multitel. Consulta-o na tua área reservada.',
                '/area-reservada?separador=plano'
            );
        }

        return response()->json([
            'message' => 'Plano de estágio carregado com sucesso.',
            'data' => $this->formatarPlano($plano),
        ], 201);
    }

    /**
     * Plano de estágio em vigor de um estágio (painel do administrador).
     */
    public function planoDoEstagio(int $id)
    {
        $plano = PlanoEstagio::query()
            ->where('id_estagio', $id)
            ->doAdministrador()
            ->whereNotNull('caminho_arquivo')
            ->orderByDesc('data_upload')
            ->first();

        return response()->json(
            $plano ? $this->formatarPlano($plano) : null
        );
    }

    /**
     * Visualizar o ficheiro de um plano de estágio.
     */
    public function verPlano(Request $request, int $id)
    {
        $utilizador = $request->user();

        $plano = PlanoEstagio::query()
            ->with('estagio')
            ->find($id);

        if (! $plano || ! $plano->estagio) {
            return response()->json([
                'message' => 'Plano de estágio não encontrado.',
            ], 404);
        }

        if (
            ! $utilizador
            || ! Gate::forUser($utilizador)->allows('view', $plano)
        ) {
            return response()->json([
                'message' => 'Não tem autorização para consultar este plano.',
            ], 403);
        }

        $this->acessos->registarPlano($request, $utilizador, $plano);

        return $this->servirFicheiro(
            $plano->caminho_arquivo,
            $plano->nomeSeguro(),
            $plano->tipoConteudo()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Apoio
    |--------------------------------------------------------------------------
    */

    /**
     * Estágio consultável pelo estagiário.
     *
     * A leitura mantém-se disponível depois da conclusão e durante uma
     * suspensão. A entrega de relatórios tem regras próprias em
     * `estagioParaRelatorios()`.
     */
    private function estagioParaConsulta(User $utilizador): ?Estagio
    {
        $estagio = Estagio::query()
            ->where('id_estagiario', $utilizador->id_utilizador)
            ->where('estado', 'ativo')
            ->latest('id_estagio')
            ->first();

        $estagio ??= Estagio::query()
            ->where('id_estagiario', $utilizador->id_utilizador)
            ->whereIn('estado', ['suspenso', 'concluido'])
            ->latest('id_estagio')
            ->first();

        if ($estagio && ! $estagio->jaComecou()) {
            abort(response()->json([
                'message' => 'O estágio ainda não começou.',
                'data_inicio' => $estagio->data_inicio?->format('Y-m-d'),
            ], 403));
        }

        return $estagio;
    }

    private function mesNoFuturo(Carbon $mes): bool
    {
        return $mes->greaterThan(now()->startOfMonth());
    }

    /**
     * Estágio usado para entregar relatórios.
     *
     * Ao contrário do painel, a entrega continua disponível depois da
     * conclusão, dentro do prazo do mês do relatório.
     */
    private function estagioParaRelatorios(User $utilizador): ?Estagio
    {
        $estagio = Estagio::query()
            ->where('id_estagiario', $utilizador->id_utilizador)
            ->latest('id_estagio')
            ->first();

        if ($estagio && ! $estagio->jaComecou()) {
            abort(response()->json([
                'message' => 'O estágio ainda não começou.',
                'data_inicio' => $estagio->data_inicio?->format('Y-m-d'),
            ], 403));
        }

        return $estagio;
    }

    private function servirFicheiro(
        ?string $caminho,
        ?string $nome,
        ?string $tipo = null
    ) {
        if (! $caminho || ! Storage::disk('local')->exists($caminho)) {
            return response()->json([
                'message' => 'O ficheiro solicitado não foi encontrado.',
            ], 404);
        }

        $disk = Storage::disk('local');

        return response()->file(
            $disk->path($caminho),
            [
                'Content-Type' => $tipo ?: $disk->mimeType($caminho),
                'Content-Disposition' => 'inline; filename="'
                    .addslashes($nome ?: basename($caminho))
                    .'"',
            ]
        );
    }

    private function formatarRelatorio(Relatorio $relatorio): array
    {
        return [
            'id_relatorio' => $relatorio->id_relatorio,
            'id_estagio' => $relatorio->id_estagio,
            'tipo' => $relatorio->tipo,
            'mes_referencia' => $relatorio->mes_referencia?->toDateString(),
            'mes_referencia_legivel' => $relatorio->mes_referencia?->format('m/Y'),
            'prazo_envio' => $relatorio->prazo_envio?->toDateString(),
            'prazo_envio_legivel' => $relatorio->prazo_envio?->format('d/m/Y'),
            'atividades' => $relatorio->atividades,
            'resultados' => $relatorio->resultados,
            'constrangimentos' => $relatorio->constrangimentos,
            'objetivos' => $relatorio->objetivos,
            'submetido_em' => $relatorio->submetido_em?->toDateTimeString(),
            'enviado_em' => $relatorio->enviado_em?->toDateTimeString(),
            'data_envio_legivel' => $relatorio->enviado_em?->format('d/m/Y H:i'),
            'dia_envio' => $relatorio->enviado_em?->format('d'),
            'mes_envio' => $relatorio->enviado_em?->format('m'),
            'ano_envio' => $relatorio->enviado_em?->format('Y'),
            'hora_envio' => $relatorio->enviado_em?->format('H:i'),
            'entrega_confirmada_em' => $relatorio->entrega_confirmada_em?->toDateTimeString(),
            'entregue' => $relatorio->entregue(),
            'em_atraso' => $relatorio->emAtraso(),
            'estado_avaliacao' => $relatorio->estado_avaliacao,
            'estado_avaliacao_legivel' => $this->rotuloEstadoAvaliacao($relatorio),
            'comentario_admin' => $relatorio->comentario_admin,
            'ficheiro_nome' => $relatorio->ficheiro_nome,
            'tem_ficheiro' => (bool) $relatorio->ficheiro_path,
            'ficheiro_url' => $relatorio->ficheiro_path
                ? "/api/estagiario/relatorios/{$relatorio->id_relatorio}/ficheiro"
                : null,
        ];
    }

    private function formatarPlano(PlanoEstagio $plano): array
    {
        return [
            'id_plano_estagio' => $plano->id_plano_estagio,
            'origem' => $plano->origem,
            'nome_arquivo' => $plano->nome_arquivo,
            'formato' => strtoupper((string) $plano->formato),
            'formato_legivel' => $plano->formatoLegivel(),
            'tamanho' => $plano->tamanho,
            'tamanho_legivel' => $this->tamanhoLegivel($plano->tamanho),
            'versao' => $plano->versao,
            'data_upload' => $plano->data_upload?->toDateTimeString(),
            'data_upload_legivel' => $plano->data_upload?->format('d/m/Y H:i'),
            'estado' => $plano->estado,
            'url' => "/api/estagiario/planos/{$plano->id_plano_estagio}/ficheiro",
        ];
    }

    /**
     * Avisos mostrados ao estagiário: atrasos e decisões do administrador.
     */
    private function alertas(User $utilizador, $relatorios): array
    {
        $alertas = [];

        foreach ($relatorios as $relatorio) {
            if ($relatorio->emAtraso()) {
                $alertas[] = [
                    'tipo' => 'atraso',
                    'id_relatorio' => $relatorio->id_relatorio,
                    'titulo' => 'Relatório em atraso',
                    'mensagem' => sprintf(
                        'O relatório %s de %s devia ter sido enviado até %s. Envia-o o mais rápido possível.',
                        $relatorio->tipo,
                        $relatorio->mes_referencia?->format('m/Y') ?? '-',
                        $relatorio->prazo_envio?->format('d/m/Y') ?? '-'
                    ),
                ];
            }

            if ($relatorio->estado_avaliacao === Relatorio::ESTADO_REJEITADO) {
                $alertas[] = [
                    'tipo' => 'rejeitado',
                    'id_relatorio' => $relatorio->id_relatorio,
                    'titulo' => 'Relatório recusado',
                    'mensagem' => sprintf(
                        'O relatório %s de %s foi recusado pela Multitel. %s Tens de o enviar de novo.',
                        $relatorio->tipo,
                        $relatorio->mes_referencia?->format('m/Y') ?? '-',
                        $relatorio->comentario_admin
                            ? 'Motivo: '.$relatorio->comentario_admin
                            : ''
                    ),
                ];
            }
        }

        /*
         * Mensagens enviadas directamente ao estagiário (recusas, avisos).
         */
        foreach ($this->notificacoesRecentes($utilizador) as $notificacao) {
            $alertas[] = [
                'tipo' => 'notificacao',
                'id_notificacao' => $notificacao['id_notificacao'],
                'titulo' => $notificacao['titulo'],
                'mensagem' => $notificacao['mensagem'],
                'data' => $notificacao['enviada_em_legivel'],
            ];
        }

        return $alertas;
    }

    private function notificacoesRecentes(User $utilizador): array
    {
        return Notificacao::query()
            ->where('id_utilizador', $utilizador->id_utilizador)
            ->orderByDesc('enviada_em')
            ->limit(10)
            ->get()
            ->map(fn (Notificacao $notificacao) => [
                'id_notificacao' => $notificacao->id_notificacao,
                'titulo' => $notificacao->titulo,
                'mensagem' => $notificacao->mensagem,
                'enviada_em' => $notificacao->enviada_em?->toDateTimeString(),
                'enviada_em_legivel' => $notificacao->enviada_em?->format('d/m/Y H:i'),
                'lida' => $notificacao->lida_em !== null,
            ])
            ->values()
            ->all();
    }

    /**
     * Aviso interno para o administrador.
     *
     * A tabela de notificações exige sempre um destinatário, por isso o
     * aviso é entregue a cada conta de administrador.
     */
    private function notificarAdmin(
        string $titulo,
        string $mensagem,
        ?string $url = null
    ): void {
        $this->notificacoes->paraAdministradores($titulo, $mensagem, $url);
    }

    private function rotuloEstado(?string $estado): string
    {
        return match ($estado) {
            'ativo' => 'Estágio ativo',
            'suspenso' => 'Estágio suspenso',
            'concluido' => 'Estágio concluído',
            'cancelado' => 'Estágio cancelado',
            default => 'Estado não definido',
        };
    }

    private function rotuloDocumento(?string $tipo): string
    {
        return match ($tipo) {
            'bi' => 'Bilhete de identidade',
            'cv' => 'Curriculum vitae',
            'declaracao_escolar' => 'Declaração escolar',
            'carta_solicitacao' => 'Carta de solicitação',
            'foto' => 'Fotografia',
            'relatorio_estagio' => 'Relatório de estágio',
            'contrato' => 'Contrato',
            'termo_responsabilidade' => 'Termo de responsabilidade',
            'certificado_conclusao' => 'Certificado de conclusão',
            'outro' => 'Outro documento',
            default => ucfirst((string) $tipo),
        };
    }

    private function rotuloEstadoAvaliacao(Relatorio $relatorio): string
    {
        return match ($relatorio->estado_avaliacao) {
            Relatorio::ESTADO_APROVADO => 'Aprovado',
            Relatorio::ESTADO_REJEITADO => 'Recusado',
            Relatorio::ESTADO_EM_ATRASO => 'Em atraso',
            default => $relatorio->entregue() ? 'Aguarda avaliação' : 'Por enviar',
        };
    }

    private function tamanhoLegivel(?int $bytes): string
    {
        if (! $bytes) {
            return '-';
        }

        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' MB';
        }

        return max(1, round($bytes / 1024)).' KB';
    }
}
