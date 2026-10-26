<?php

namespace App\Http\Controllers;

use App\Models\Relatorio;
use App\Services\NotificacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminRelatorioController extends Controller
{
    public function __construct(
        private readonly NotificacaoService $notificacoes
    ) {}

    /**
     * Listar relatórios dos estagiários.
     */
    public function index(Request $request)
    {
        $filtros = $request->validate([
            'estado' => [
                'nullable',
                Rule::in([
                    'pendente',
                    'aprovado',
                    'rejeitado',
                    'em_atraso',
                ]),
            ],
            'tipo' => [
                'nullable',
                Rule::in(['semanal', 'mensal', 'final']),
            ],
            'pesquisa' => ['nullable', 'string', 'max:150'],
        ]);

        $query = Relatorio::query()
            ->with([
                'estagio.estagiario:id_utilizador,nome,email',
            ]);

        if (! empty($filtros['estado'])) {
            if ($filtros['estado'] === 'em_atraso') {
                $query->whereNull('enviado_em')
                    ->whereNull('entrega_confirmada_em')
                    ->whereNotNull('prazo_envio')
                    ->where('prazo_envio', '<', now()->toDateString());
            } else {
                $query->where(
                    'estado_avaliacao',
                    $filtros['estado']
                );
            }
        }

        if (! empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (! empty($filtros['pesquisa'])) {
            $pesquisa = $filtros['pesquisa'];

            $query->whereHas(
                'estagio.estagiario',
                function ($subquery) use ($pesquisa) {
                    $subquery->where('nome', 'like', "%{$pesquisa}%")
                        ->orWhere('email', 'like', "%{$pesquisa}%");
                }
            );
        }

        $relatorios = $query
            ->select([
                'id_relatorio',
                'id_estagio',
                'tipo',
                'mes_referencia',
                'prazo_envio',
                'periodo_referencia',
                'atividades',
                'resultados',
                'constrangimentos',
                'objetivos',
                'submetido_em',
                'enviado_em',
                'entrega_confirmada_em',
                'estado_avaliacao',
                'comentario_admin',
                'ficheiro_path',
                'ficheiro_nome',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('mes_referencia')
            ->orderByDesc('id_relatorio')
            ->paginate(15);

        $relatorios->getCollection()->transform(
            fn (Relatorio $relatorio) => $this->formatar($relatorio)
        );

        return response()->json($relatorios);
    }

    /**
     * Resumo dos relatórios em atraso, para o aviso do painel.
     */
    public function atrasos()
    {
        $emAtraso = Relatorio::query()
            ->with('estagio.estagiario:id_utilizador,nome,email')
            ->whereNull('enviado_em')
            ->whereNull('entrega_confirmada_em')
            ->whereNotNull('prazo_envio')
            ->where('prazo_envio', '<', now()->toDateString())
            ->orderBy('prazo_envio')
            ->get();

        return response()->json([
            'total' => $emAtraso->count(),
            'data' => $emAtraso
                ->map(fn (Relatorio $relatorio) => $this->formatar($relatorio))
                ->values(),
        ]);
    }

    /**
     * Aprovar ou rejeitar um relatório.
     *
     * A aprovação encerra o processo. A rejeição exige uma descrição,
     * avisa o estagiário e obriga-o a enviar o relatório de novo.
     */
    public function avaliar(Request $request, int $id)
    {
        $dados = $request->validate([
            'estado_avaliacao' => [
                'required',
                Rule::in(['aprovado', 'rejeitado']),
            ],
            'comentario_admin' => [
                'required_if:estado_avaliacao,rejeitado',
                'nullable',
                'string',
                'max:5000',
            ],
        ], [
            'comentario_admin.required_if' => 'Escreve a descrição que explica a recusa do relatório.',
        ]);

        $relatorio = Relatorio::query()
            ->with('estagio')
            ->findOrFail($id);

        if (! $relatorio->entregue()) {
            return response()->json([
                'message' => 'Só é possível avaliar relatórios já entregues pelo estagiário.',
            ], 422);
        }

        $rejeicao = $dados['estado_avaliacao'] === 'rejeitado';

        DB::transaction(function () use ($relatorio, $dados, $rejeicao) {
            $relatorio->estado_avaliacao = $dados['estado_avaliacao'];
            $relatorio->comentario_admin = $dados['comentario_admin'] ?? null;
            $relatorio->save();

            if (! $rejeicao) {
                return;
            }

            /*
             * A recusa chega ao estagiário no painel dele.
             */
            if ($relatorio->estagio?->id_estagiario) {
                $this->notificacoes->paraUtilizador(
                    $relatorio->estagio->id_estagiario,
                    'Relatório recusado',
                    sprintf(
                        'O teu relatório %s de %s foi recusado pela Multitel. Motivo: %s Tens de o enviar de novo.',
                        $relatorio->tipo,
                        $relatorio->mes_referencia?->format('m/Y') ?? '-',
                        $dados['comentario_admin'] ?? 'Não indicado.'
                    )
                );
            }
        });

        return response()->json([
            'message' => $dados['estado_avaliacao'] === 'aprovado'
                ? 'Relatório aprovado com sucesso.'
                : 'Relatório recusado. O estagiário foi notificado e terá de o enviar de novo.',
            'data' => [
                'id_relatorio' => $relatorio->id_relatorio,
                'estado_avaliacao' => $relatorio->estado_avaliacao,
                'comentario_admin' => $relatorio->comentario_admin,
            ],
        ]);
    }

    private function formatar(Relatorio $relatorio): array
    {
        return [
            ...$relatorio->toArray(),
            'mes_referencia_legivel' => $relatorio->mes_referencia?->format('m/Y'),
            'prazo_envio_legivel' => $relatorio->prazo_envio?->format('d/m/Y'),
            'data_envio_legivel' => $relatorio->enviado_em?->format('d/m/Y H:i'),
            'entregue' => $relatorio->entregue(),
            'em_atraso' => $relatorio->emAtraso(),
            'tem_ficheiro' => (bool) $relatorio->ficheiro_path,
            'ficheiro_url' => $relatorio->ficheiro_path
                ? "/api/admin/relatorios/{$relatorio->id_relatorio}/ficheiro"
                : null,
        ];
    }
}
