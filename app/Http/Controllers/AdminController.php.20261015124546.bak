<?php

namespace App\Http\Controllers;

use App\Models\Candidatura;
use App\Models\HistoricoEstado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * Permite:
     * - pesquisa por nome, e-mail, curso ou universidade;
     * - filtro por estado;
     * - filtro por departamento;
     * - paginação.
     */
    public function candidaturas(Request $request)
    {
        $query = Candidatura::with('departamento')
            ->orderByDesc('created_at');

        /*
        |--------------------------------------------------------------------------
        | Pesquisa
        |--------------------------------------------------------------------------
        */
        if ($request->filled('pesquisa')) {
            $pesquisa = $request->input('pesquisa');

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
        if ($request->filled('estado')) {
            $query->where(
                'estado',
                $request->input('estado')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filtro por departamento
        |--------------------------------------------------------------------------
        */
        if ($request->filled('id_departamento')) {
            $query->where(
                'id_departamento_selecionado',
                $request->input('id_departamento')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Paginação
        |--------------------------------------------------------------------------
        */
        $candidaturas = $query->paginate(
            $request->integer('por_pagina', 15)
        );

        return response()->json($candidaturas);
    }

    /**
     * Exibe os detalhes de uma candidatura.
     */
    public function show(int $id)
    {
        $candidatura = Candidatura::with([
            'departamento',
            'documentos',
        ])->find($id);

        if (!$candidatura) {
            return response()->json([
                'message' => 'Candidatura não encontrada.'
            ], 404);
        }

        return response()->json($candidatura);
    }

    /**
     * Aprova uma candidatura.
     */
    public function aprovar(int $id)
    {
        return DB::transaction(function () use ($id) {

            $candidatura = Candidatura::find($id);

            if (!$candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada.'
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Só pode aprovar candidaturas em análise
            |--------------------------------------------------------------------------
            */
            if ($candidatura->estado !== 'em_analise') {
                return response()->json([
                    'message' =>
                        'A candidatura não pode ser aprovada porque já não está em análise.'
                ], 422);
            }

            $estadoAnterior = $candidatura->estado;

            /*
            |--------------------------------------------------------------------------
            | Atualização
            |--------------------------------------------------------------------------
            |
            | NÃO alteramos "bi_ativo" aqui.
            |
            | A coluna bi_ativo é uma coluna gerada pelo MySQL,
            | portanto o próprio banco calcula o seu valor
            | com base no estado da candidatura.
            |
            */
            $candidatura->update([
                'estado' => 'aprovada',
                'motivo_recusa' => null,
                'data_decisao' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Registo do histórico
            |--------------------------------------------------------------------------
            */
            HistoricoEstado::create([
                'id_candidatura' => $candidatura->id_candidatura,
                'estado_anterior' => $estadoAnterior,
                'estado_novo' => 'aprovada',
                'motivo' => null,
                'id_utilizador' => request()->user()->id_utilizador,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Resposta
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'message' => 'Candidatura aprovada com sucesso.',

                'candidatura' => $candidatura->fresh([
                    'departamento',
                    'documentos',
                ]),
            ]);
        });
    }

    /**
     * Recusa uma candidatura.
     */
    public function recusar(Request $request, int $id)
    {
        /*
        |--------------------------------------------------------------------------
        | Validação do motivo
        |--------------------------------------------------------------------------
        */
        $dados = $request->validate([
            'motivo' => [
                'required',
                'string',
                'min:5',
                'max:1000'
            ],
        ]);

        return DB::transaction(function () use ($id, $dados) {

            $candidatura = Candidatura::find($id);

            if (!$candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada.'
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Só pode recusar candidaturas em análise
            |--------------------------------------------------------------------------
            */
            if ($candidatura->estado !== 'em_analise') {
                return response()->json([
                    'message' =>
                        'A candidatura não pode ser recusada porque já não está em análise.'
                ], 422);
            }

            $estadoAnterior = $candidatura->estado;

            /*
            |--------------------------------------------------------------------------
            | Atualização
            |--------------------------------------------------------------------------
            |
            | NÃO alteramos "bi_ativo".
            |
            | Como essa coluna é gerada pelo MySQL, ao mudar
            | o estado para "recusada", o banco calcula
            | automaticamente o valor correspondente.
            |
            */
            $candidatura->update([
                'estado' => 'recusada',
                'motivo_recusa' => $dados['motivo'],
                'data_decisao' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Registo do histórico
            |--------------------------------------------------------------------------
            */
            HistoricoEstado::create([
                'id_candidatura' => $candidatura->id_candidatura,
                'estado_anterior' => $estadoAnterior,
                'estado_novo' => 'recusada',
                'motivo' => $dados['motivo'],
                'id_utilizador' => request()->user()->id_utilizador,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Resposta
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'message' => 'Candidatura recusada com sucesso.',

                'candidatura' => $candidatura->fresh([
                    'departamento',
                    'documentos',
                ]),
            ]);
        });
    }
}