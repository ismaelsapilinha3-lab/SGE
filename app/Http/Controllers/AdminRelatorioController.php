<?php

namespace App\Http\Controllers;

use App\Models\Relatorio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminRelatorioController extends Controller
{
    /**
     * Listar relatórios dos estagiários.
     */
    public function index(Request $request)
    {
        $filtros = $request->validate([
            'estado' => [
                'nullable',
                Rule::in(['pendente', 'aprovado', 'rejeitado']),
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

        if (!empty($filtros['estado'])) {
            $query->where(
                'estado_avaliacao',
                $filtros['estado']
            );
        }

        if (!empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (!empty($filtros['pesquisa'])) {
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
                'periodo_referencia',
                'atividades',
                'resultados',
                'constrangimentos',
                'objetivos',
                'submetido_em',
                'estado_avaliacao',
                'comentario_admin',
                'ficheiro_nome',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('submetido_em')
            ->paginate(15);

        return response()->json($relatorios);
    }

    /**
     * Aprovar ou rejeitar um relatório.
     */
    public function avaliar(Request $request, int $id)
    {
        $dados = $request->validate([
            'estado_avaliacao' => [
                'required',
                Rule::in(['aprovado', 'rejeitado']),
            ],
            'comentario_admin' => [
                Rule::requiredIf(
                    fn () => $request->input('estado_avaliacao') === 'rejeitado'
                ),
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $relatorio = Relatorio::query()
            ->findOrFail($id);

        $relatorio->estado_avaliacao = $dados['estado_avaliacao'];
        $relatorio->comentario_admin = $dados['comentario_admin'] ?? null;
        $relatorio->save();

        return response()->json([
            'message' => $dados['estado_avaliacao'] === 'aprovado'
                ? 'Relatório aprovado com sucesso.'
                : 'Relatório rejeitado com sucesso.',
            'data' => [
                'id_relatorio' => $relatorio->id_relatorio,
                'estado_avaliacao' => $relatorio->estado_avaliacao,
                'comentario_admin' => $relatorio->comentario_admin,
            ],
        ]);
    }
}