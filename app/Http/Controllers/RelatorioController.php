<?php

namespace App\Http\Controllers;

use App\Models\Estagio;
use App\Models\Relatorio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelatorioController extends Controller
{
    /**
     * Lista os relatórios do estágio ativo do estagiário autenticado.
     */
    public function meusRelatorios(Request $request)
    {
        $utilizador = $request->user();

        if (!$utilizador || $utilizador->tipo_utilizador !== 'estagiario') {
            return response()->json([
                'message' => 'Apenas estagiários podem consultar os seus relatórios.',
            ], 403);
        }

        $estagio = Estagio::query()
            ->where('id_estagiario', $utilizador->id_utilizador)
            ->where('estado', 'ativo')
            ->first();

        if (!$estagio) {
            return response()->json([
                'message' => 'Não existe um estágio ativo associado à sua conta.',
            ], 404);
        }

        $relatorios = Relatorio::query()
            ->where('id_estagio', $estagio->id_estagio)
            ->orderByDesc('periodo_referencia')
            ->get();

        return response()->json([
            'data' => $relatorios,
        ]);
    }

    /**
     * Regista um relatório para o estágio ativo do estagiário autenticado.
     */
    public function guardarRelatorio(Request $request)
    {
        $utilizador = $request->user();

        if (!$utilizador || $utilizador->tipo_utilizador !== 'estagiario') {
            return response()->json([
                'message' => 'Apenas estagiários podem submeter relatórios.',
            ], 403);
        }

        $dados = $request->validate([
            'tipo' => ['required', 'in:semanal,mensal,final'],
            'periodo_referencia' => ['required', 'date'],
            'atividades' => ['required', 'string', 'min:10'],
            'resultados' => ['required', 'string', 'min:10'],
            'constrangimentos' => ['nullable', 'string'],
            'objetivos' => ['nullable', 'string'],
        ]);

        $estagio = Estagio::query()
            ->where('id_estagiario', $utilizador->id_utilizador)
            ->where('estado', 'ativo')
            ->first();

        if (!$estagio) {
            return response()->json([
                'message' => 'Não existe um estágio ativo associado à sua conta.',
            ], 404);
        }

        $duplicado = Relatorio::query()
            ->where('id_estagio', $estagio->id_estagio)
            ->where('tipo', $dados['tipo'])
            ->whereDate('periodo_referencia', $dados['periodo_referencia'])
            ->exists();

        if ($duplicado) {
            return response()->json([
                'message' => 'Já existe um relatório deste tipo para esse período.',
            ], 422);
        }

        $relatorio = DB::transaction(function () use ($dados, $estagio) {
            return Relatorio::create([
                ...$dados,
                'id_estagio' => $estagio->id_estagio,
                'submetido_em' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Relatório submetido com sucesso.',
            'data' => $relatorio,
        ], 201);
    }
}