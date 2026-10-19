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

    public function estagiarios(Request $request)
    {
        $query = DB::table('candidaturas as c')
            ->leftJoin('estagios as e', 'e.id_candidatura', '=', 'c.id_candidatura')
            ->leftJoin('utilizadores as u', 'u.email', '=', 'c.email')
            ->leftJoin('departamentos as d', 'd.id_departamento', '=', 'c.id_departamento_selecionado')
            ->where(function ($q) {
                $q->where('c.estado', 'aprovada')
                  ->orWhereNotNull('e.id_estagio');
            })
            ->select(
                'e.id_estagio',
                'c.id_candidatura',
                'e.data_inicio',
                'e.duracao_meses',
                'e.data_fim_prevista',
                'e.data_fim_real',
              'e.motivo_suspensao',
                DB::raw('COALESCE(e.estado, c.estado) as estado_estagio'),
                'u.id_utilizador as id_estagiario',
                DB::raw('COALESCE(u.nome, c.nome) as nome'),
                DB::raw('COALESCE(u.email, c.email) as email'),
                DB::raw('COALESCE(u.telefone, c.contacto) as telefone'),
                'c.curso',
                'c.universidade',
                'd.nome as departamento'
            );

        if ($request->filled('pesquisa')) {
            $pesquisa = $request->input('pesquisa');

            $query->where(function ($q) use ($pesquisa) {
                $q->where('c.nome', 'like', "%{$pesquisa}%")
                  ->orWhere('c.email', 'like', "%{$pesquisa}%")
                  ->orWhere('c.curso', 'like', "%{$pesquisa}%");
            });
        }

        if ($request->filled('estado')) {
            $estado = $request->input('estado');

            if ($estado === 'aprovada') {
                $query->where('c.estado', 'aprovada')
                      ->whereNull('e.id_estagio');
            } else {
                $query->where('e.estado', $estado);
            }
        }

        return response()->json(
            $query->orderByDesc('c.data_decisao')
                  ->paginate($request->integer('por_pagina', 15))
        );
    }

    public function atualizarEstadoEstagio(Request $request, int $idCandidatura)
    {
        $dados = $request->validate([
            'estado' => 'required|in:ativo,suspenso,concluido,cancelado',
            'data_inicio' => 'required|date',
            'duracao_meses' => 'nullable|integer|min:1',
            'motivo_suspensao' => 'nullable|string|required_if:estado,suspenso|max:5000',
        ]);

        return DB::transaction(function () use ($dados, $idCandidatura) {
            $candidatura = DB::table('candidaturas')
                ->where('id_candidatura', $idCandidatura)
                ->lockForUpdate()
                ->first();

            if (!$candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada.'
                ], 404);
            }

            $estagio = DB::table('estagios')
                ->where('id_candidatura', $idCandidatura)
                ->lockForUpdate()
                ->first();

            if (!$estagio && $candidatura->estado !== 'aprovada') {
                return response()->json([
                    'message' => 'Só é possível iniciar um estágio para uma candidatura aprovada.'
                ], 422);
            }

            // Resolver a conta do estagiário no servidor.
            $utilizador = DB::table('utilizadores')
                ->where('email', $candidatura->email)
                ->first();

            if (!$utilizador) {
                return response()->json([
                    'message' => 'Esta candidatura ainda não tem uma conta de estagiário. Cria a conta antes de iniciar o estágio.'
                ], 422);
            }

            $inicio = $dados['data_inicio']
                ?? ($estagio->data_inicio ?? null);

            $duracaoMeses = (int) (
                $dados['duracao_meses']
                ?? ($estagio->duracao_meses ?? 6)
            );

            if (!$inicio) {
                return response()->json([
                    'message' => 'Indica a data de início do estágio.'
                ], 422);
            }

            if ($duracaoMeses < 1) {
                return response()->json([
                    'message' => 'A duração deve ser de pelo menos um mês.'
                ], 422);
            }

            // O servidor calcula a data final; não confia na data
            // prevista enviada pelo navegador.
            $fimPrevisto = \Illuminate\Support\Carbon::parse($inicio)
                ->addMonthsNoOverflow($duracaoMeses)
                ->toDateString();

            $agora = now();

            $valores = [
                'id_candidatura' => $idCandidatura,
                'id_estagiario' => $utilizador->id_utilizador,
                'data_inicio' => $inicio,
                'duracao_meses' => $duracaoMeses,
                'data_fim_prevista' => $fimPrevisto,
                'estado' => $dados['estado'],
                'motivo_suspensao' => $dados['estado'] === 'suspenso'
                    ? trim($dados['motivo_suspensao'] ?? '')
                    : null,
                'data_fim_real' => $dados['estado'] === 'concluido'
                    ? ($estagio->data_fim_real ?? $agora->toDateString())
                    : ($estagio->data_fim_real ?? null),
                'updated_at' => $agora,
            ];

            if ($estagio) {
                DB::table('estagios')
                    ->where('id_estagio', $estagio->id_estagio)
                    ->update($valores);

                $idEstagio = $estagio->id_estagio;
            } else {
                $valores['created_at'] = $agora;

                $idEstagio = DB::table('estagios')->insertGetId(
                    $valores,
                    'id_estagio'
                );
            }

            return response()->json([
                'message' => 'Estado do estágio atualizado com sucesso.',
                'data' => DB::table('estagios')
                    ->where('id_estagio', $idEstagio)
                    ->first(),
            ]);
        });
    }
}
