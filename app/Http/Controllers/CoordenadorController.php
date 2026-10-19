<?php

namespace App\Http\Controllers;

use App\Models\Candidatura;
use App\Models\HistoricoEstado;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CoordenadorController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Obter a direção do coordenador autenticado
    |--------------------------------------------------------------------------
    */

    private function idDepartamentoDoCoordenador(
        Request $request
    ): int {

        $idDepartamento = $request->user()
            ->perfil
            ?->id_departamento;

        if (! $idDepartamento) {
            abort(response()->json([
                'message' => 'O coordenador não está associado a uma direção.',
            ], 403));
        }

        return (int) $idDepartamento;
    }

    /*
    |--------------------------------------------------------------------------
    | Consultar candidaturas atribuídas ao coordenador autenticado
    |--------------------------------------------------------------------------
    */

    private function candidaturasDoCoordenador(
        Request $request
    ) {

        $user = $request->user();

        $idDepartamento =
            $this->idDepartamentoDoCoordenador($request);

        return Candidatura::query()
            ->where(
                'id_coordenador',
                $user->id_utilizador
            )
            ->where(
                'id_departamento_selecionado',
                $idDepartamento
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard do coordenador
    |--------------------------------------------------------------------------
    */

    public function dashboard(Request $request)
    {
        $query = $this->candidaturasDoCoordenador($request);

        return response()->json([
            'total_candidaturas' => (clone $query)->count(),

            'em_analise' => (clone $query)
                ->where('estado', 'em_analise')
                ->count(),

            'aprovadas' => (clone $query)
                ->where('estado', 'aprovada')
                ->count(),

            'recusadas' => (clone $query)
                ->where('estado', 'recusada')
                ->count(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Listar candidaturas
    |--------------------------------------------------------------------------
    */

    public function candidaturas(Request $request)
    {
        $dados = $request->validate([
            'pesquisa' => [
                'nullable',
                'string',
                'max:150',
            ],

            'estado' => [
                'nullable',
                'in:em_analise,aprovada,recusada,encerrada',
            ],

            'por_pagina' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = $this->candidaturasDoCoordenador($request)
            ->with('departamento')
            ->orderByDesc('created_at');

        if (! empty($dados['pesquisa'])) {
            $pesquisa = $dados['pesquisa'];

            $query->where(function ($q) use ($pesquisa) {
                $q->where(
                    'nome',
                    'like',
                    "%{$pesquisa}%"
                )
                    ->orWhere(
                        'email',
                        'like',
                        "%{$pesquisa}%"
                    )
                    ->orWhere(
                        'curso',
                        'like',
                        "%{$pesquisa}%"
                    )
                    ->orWhere(
                        'universidade',
                        'like',
                        "%{$pesquisa}%"
                    );
            });
        }

        if (! empty($dados['estado'])) {
            $query->where('estado', $dados['estado']);
        }

        return response()->json(
            $query->paginate($dados['por_pagina'] ?? 15)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Consultar uma candidatura
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        int $id
    ) {

        $candidatura = $this
            ->candidaturasDoCoordenador($request)
            ->with([
                'departamento',
                'documentos',
            ])
            ->where('id_candidatura', $id)
            ->first();

        if (! $candidatura) {
            return response()->json([
                'message' => 'Candidatura não encontrada ou não atribuída a este coordenador.',
            ], 404);
        }

        return response()->json($candidatura);
    }

    /*
    |--------------------------------------------------------------------------
    | Aprovar candidatura
    |--------------------------------------------------------------------------
    */

    public function aprovar(
        Request $request,
        int $id
    ) {

        return DB::transaction(function () use (
            $request,
            $id
        ) {

            $candidatura = $this
                ->candidaturasDoCoordenador($request)
                ->where('id_candidatura', $id)
                ->lockForUpdate()
                ->first();

            if (! $candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada ou não atribuída a este coordenador.',
                ], 404);
            }

            if ($candidatura->estado !== 'em_analise') {
                return response()->json([
                    'message' => 'Esta candidatura já foi decidida.',
                ], 422);
            }

            $estadoAnterior = $candidatura->estado;

            $candidatura->update([
                'estado' => 'aprovada',
                'motivo_recusa' => null,
                'data_decisao' => now(),
            ]);

            HistoricoEstado::create([
                'id_candidatura' => $candidatura->id_candidatura,

                'estado_anterior' => $estadoAnterior,

                'estado_novo' => 'aprovada',

                'motivo' => null,

                'id_utilizador' => $request->user()->id_utilizador,
            ]);

            return response()->json([
                'message' => 'Candidatura aprovada com sucesso.',

                'candidatura' => $candidatura
                    ->fresh()
                    ->load([
                        'departamento',
                        'documentos',
                    ]),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Recusar candidatura
    |--------------------------------------------------------------------------
    */

    public function recusar(
        Request $request,
        int $id
    ) {

        $dados = $request->validate([
            'motivo' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ]);

        return DB::transaction(function () use (
            $request,
            $id,
            $dados
        ) {

            $candidatura = $this
                ->candidaturasDoCoordenador($request)
                ->where('id_candidatura', $id)
                ->lockForUpdate()
                ->first();

            if (! $candidatura) {
                return response()->json([
                    'message' => 'Candidatura não encontrada ou não atribuída a este coordenador.',
                ], 404);
            }

            if ($candidatura->estado !== 'em_analise') {
                return response()->json([
                    'message' => 'Esta candidatura já foi decidida.',
                ], 422);
            }

            $estadoAnterior = $candidatura->estado;

            $candidatura->update([
                'estado' => 'recusada',
                'motivo_recusa' => $dados['motivo'],
                'data_decisao' => now(),
            ]);

            HistoricoEstado::create([
                'id_candidatura' => $candidatura->id_candidatura,

                'estado_anterior' => $estadoAnterior,

                'estado_novo' => 'recusada',

                'motivo' => $dados['motivo'],

                'id_utilizador' => $request->user()->id_utilizador,
            ]);

            return response()->json([
                'message' => 'Candidatura recusada com sucesso.',

                'candidatura' => $candidatura
                    ->fresh()
                    ->load([
                        'departamento',
                        'documentos',
                    ]),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Criar supervisor
    |--------------------------------------------------------------------------
    */

    public function criarSupervisor(Request $request)
    {
        $idDepartamento =
            $this->idDepartamentoDoCoordenador($request);

        $dados = $request->validate([
            'nome' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:utilizadores,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'telefone' => [
                'nullable',
                'string',
                'max:30',
            ],
        ]);

        $supervisor = DB::transaction(function () use (
            $dados,
            $idDepartamento
        ) {

            $utilizador = User::create([
                'nome' => $dados['nome'],

                'email' => $dados['email'],

                'password' => Hash::make(
                    $dados['password']
                ),

                'telefone' => $dados['telefone'] ?? null,

                'tipo_utilizador' => 'supervisor',

                'ativo' => true,
            ]);

            Perfil::create([
                'id_utilizador' => $utilizador->id_utilizador,

                'id_departamento' => $idDepartamento,
            ]);

            return $utilizador;
        });

        return response()->json([
            'message' => 'Supervisor criado com sucesso.',

            'supervisor' => [
                'id_utilizador' => $supervisor->id_utilizador,

                'nome' => $supervisor->nome,

                'email' => $supervisor->email,

                'telefone' => $supervisor->telefone,

                'tipo_utilizador' => $supervisor->tipo_utilizador,

                'ativo' => $supervisor->ativo,

                'id_departamento' => $idDepartamento,
            ],
        ], 201);
    }
}
