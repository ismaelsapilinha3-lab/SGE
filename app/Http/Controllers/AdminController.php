<?php

namespace App\Http\Controllers;

use App\Models\Candidatura;
use App\Models\User;
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
     * Permite pesquisa, filtros e paginação.
     */
    public function candidaturas(Request $request)
    {
        $dados = $request->validate([
            'pesquisa' => ['nullable', 'string', 'max:150'],

            'estado' => [
                'nullable',
                'in:em_analise,aprovada,recusada',
            ],

            'id_departamento' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'por_pagina' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = Candidatura::with([
            'departamento',
            'documentos',
        ])->orderByDesc('created_at');

        /*
        |--------------------------------------------------------------------------
        | Pesquisa
        |--------------------------------------------------------------------------
        */
        if (! empty($dados['pesquisa'])) {
            $pesquisa = $dados['pesquisa'];

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
        if (! empty($dados['estado'])) {
            $query->where(
                'estado',
                $dados['estado']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filtro por direção
        |--------------------------------------------------------------------------
        */
        if (! empty($dados['id_departamento'])) {
            $query->where(
                'id_departamento_selecionado',
                $dados['id_departamento']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Paginação
        |--------------------------------------------------------------------------
        */
        return response()->json(
            $query->paginate(
                $dados['por_pagina'] ?? 15
            )
        );
    }

    /**
     * Exibe os detalhes de uma candidatura.
     */
    public function show(int $id)
    {
        $candidatura = Candidatura::with([
            'departamento',
            'documentos',
        ])
            ->where('id_candidatura', $id)
            ->first();

        if (! $candidatura) {
            return response()->json([
                'message' => 'Candidatura não encontrada.',
            ], 404);
        }

        return response()->json($candidatura);
    }

    /**
     * Encaminha uma candidatura ao coordenador
     * da direção selecionada pelo candidato.
     *
     * O estado da candidatura permanece em_analise.
     */
    public function encaminhar(Request $request, int $id)
    {
        $dados = $request->validate([
            'id_departamento' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        return DB::transaction(function () use (

            $id,
            $dados
        ) {
            $candidatura = Candidatura::query()
                ->where('id_candidatura', $id)
                ->lockForUpdate()
                ->first();

            if (! $candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada.',
                ], 404);
            }

            if ($candidatura->estado !== 'em_analise') {
                return response()->json([
                    'message' => 'Só é possível encaminhar candidaturas em análise.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Verificar se já foi encaminhada
            |--------------------------------------------------------------------------
            */
            if (
                $candidatura->id_coordenador !== null ||
                $candidatura->encaminhada_em !== null
            ) {
                return response()->json([
                    'message' => 'Esta candidatura já foi encaminhada.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Localizar coordenador ativo da direção escolhida
            |--------------------------------------------------------------------------
            */
            $coordenador = User::query()
                ->where('tipo_utilizador', 'coordenador')
                ->where('ativo', true)
                ->whereHas('perfil', function ($q) use ($dados) {
                    $q->where(
                        'id_departamento',
                        $dados['id_departamento']
                    );
                })
                ->with('perfil')
                ->first();

            if (! $coordenador) {
                return response()->json([
                    'message' => 'Não existe coordenador activo para a direção seleccionada.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Registar o encaminhamento
            |--------------------------------------------------------------------------
            */
            $candidatura->update([
                'id_coordenador' => $coordenador->id_utilizador,
                'id_departamento_selecionado' => (int) $dados['id_departamento'],
                'encaminhada_em' => now(),
            ]);

            return response()->json([
                'message' => 'Candidatura encaminhada ao coordenador com sucesso.',

                'candidatura' => $candidatura->fresh()->load([
                    'departamento',
                    'documentos',
                ]),

                'coordenador' => [
                    'id_utilizador' => $coordenador->id_utilizador,
                    'nome' => $coordenador->nome,
                    'email' => $coordenador->email,
                    'id_departamento' => (int) $dados['id_departamento'],
                ],
            ]);
        });
    }

    /**
     * A decisão final pertence ao coordenador.
     *
     * Mantido para evitar erros nas rotas antigas,
     * mas não permite ao administrador aprovar.
     */
    public function aprovar(Request $request, int $id)
    {
        return response()->json([
            'message' => 'O administrador não pode aprovar candidaturas. Encaminhe a candidatura ao coordenador responsável.',
        ], 403);
    }

    /**
     * A decisão final pertence ao coordenador.
     *
     * Mantido para evitar erros nas rotas antigas,
     * mas não permite ao administrador recusar.
     */
    public function recusar(Request $request, int $id)
    {
        return response()->json([
            'message' => 'O administrador não pode recusar candidaturas. Encaminhe a candidatura ao coordenador responsável.',
        ], 403);
    }

    /**
     * Lista os estagiários e os estágios.
     */
    public function estagiarios(Request $request)
    {
        $query = DB::table('candidaturas as c')
            ->leftJoin(
                'estagios as e',
                'e.id_candidatura',
                '=',
                'c.id_candidatura'
            )
            ->leftJoin(
                'utilizadores as u',
                'u.id_utilizador',
                '=',
                'c.id_utilizador'
            )
            ->leftJoin(
                'departamentos as d',
                'd.id_departamento',
                '=',
                'c.id_departamento_selecionado'
            )
            ->where(function ($q) {
                $q->where('c.estado', 'aprovada')
                    ->orWhereNotNull('e.id_estagio');
            })
            ->select(
                'e.id_estagio',
                'c.id_candidatura',
                'e.data_inicio',
                'e.data_fim_prevista',
                'e.data_fim_real',
                'e.motivo_suspensao',
                DB::raw(
                    'COALESCE(e.estado, c.estado) as estado_estagio'
                ),
                'u.id_utilizador as id_estagiario',
                DB::raw('COALESCE(u.nome, c.nome) as nome'),
                DB::raw('COALESCE(u.email, c.email) as email'),
                DB::raw(
                    'COALESCE(u.telefone, c.contacto) as telefone'
                ),
                'c.curso',
                'c.universidade',
                'd.nome as departamento'
            );

        /*
        |--------------------------------------------------------------------------
        | Pesquisa
        |--------------------------------------------------------------------------
        */
        if ($request->filled('pesquisa')) {
            $pesquisa = $request->input('pesquisa');

            $query->where(function ($q) use ($pesquisa) {
                $q->where('c.nome', 'like', "%{$pesquisa}%")
                    ->orWhere('c.email', 'like', "%{$pesquisa}%")
                    ->orWhere('c.curso', 'like', "%{$pesquisa}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filtro por estado do estágio
        |--------------------------------------------------------------------------
        */
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
                ->paginate(
                    $request->integer('por_pagina', 15)
                )
        );
    }
}
