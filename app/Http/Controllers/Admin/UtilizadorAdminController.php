<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Perfil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UtilizadorAdminController extends Controller
{
    /**
     * Listar utilizadores com pesquisa e paginação.
     */
    public function index(Request $request)
    {
        $dados = $request->validate([
            'pesquisa' => ['nullable', 'string', 'max:150'],
            'tipo_utilizador' => [
                'nullable',
                Rule::in([
                    'administrador',
                    'coordenador',
                    'supervisor',
                    'estagiario',
                ]),
            ],
            'ativo' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()
            ->select([
                'id_utilizador',
                'nome',
                'email',
                'telefone',
                'foto',
                'tipo_utilizador',
                'ativo',
                'created_at',
            ]);

        if (!empty($dados['pesquisa'])) {
            $pesquisa = $dados['pesquisa'];

            $query->where(function ($q) use ($pesquisa) {
                $q->where('nome', 'like', "%{$pesquisa}%")
                    ->orWhere('email', 'like', "%{$pesquisa}%");
            });
        }

        if (!empty($dados['tipo_utilizador'])) {
            $query->where(
                'tipo_utilizador',
                $dados['tipo_utilizador']
            );
        }

        if (array_key_exists('ativo', $dados)) {
            $query->where('ativo', $dados['ativo']);
        }

        return response()->json(
            $query->orderBy('nome')
                ->paginate($dados['per_page'] ?? 15)
        );
    }

    /**
     * Criar um utilizador.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:utilizadores,email'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'tipo_utilizador' => [
                'required',
                Rule::in([
                    'coordenador',
                    'supervisor',
                    'estagiario',
                ]),
            ],
            'id_departamento' => [
                'nullable',
                'integer',
                'exists:departamentos,id_departamento',
            ],
        ]);

        $utilizador = DB::transaction(function () use ($dados) {
            $utilizador = User::create([
                'nome' => $dados['nome'],
                'email' => $dados['email'],
                'password' => Hash::make($dados['password']),
                'telefone' => $dados['telefone'] ?? null,
                'tipo_utilizador' => $dados['tipo_utilizador'],
                'ativo' => true,
            ]);

            if (
                $utilizador->tipo_utilizador === 'coordenador'
                && !empty($dados['id_departamento'])
            ) {
                Perfil::create([
                    'id_utilizador' => $utilizador->id_utilizador,
                    'id_departamento' => $dados['id_departamento'],
                ]);
            }

            return $utilizador;
        });

        return response()->json([
            'message' => 'Utilizador criado com sucesso.',
            'utilizador' => $utilizador->only([
                'id_utilizador',
                'nome',
                'email',
                'telefone',
                'tipo_utilizador',
                'ativo',
            ]),
        ], 201);
    }

    /**
     * Consultar um utilizador.
     */
    public function show(int $id)
    {
        $utilizador = User::query()
            ->with('perfil.departamento')
            ->findOrFail($id);

        return response()->json([
            'utilizador' => $utilizador->only([
                'id_utilizador',
                'nome',
                'email',
                'telefone',
                'foto',
                'tipo_utilizador',
                'ativo',
                'created_at',
            ]),
            'perfil' => $utilizador->perfil,
        ]);
    }

    /**
     * Actualizar os dados de um utilizador.
     */
    public function update(Request $request, int $id)
    {
        $utilizador = User::findOrFail($id);

        $dados = $request->validate([
            'nome' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('utilizadores', 'email')
                    ->ignore($utilizador->id_utilizador, 'id_utilizador'),
            ],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'tipo_utilizador' => [
                'sometimes',
                'required',
                Rule::in([
                    'coordenador',
                    'supervisor',
                    'estagiario',
                ]),
            ],
            'password' => [
                'sometimes',
                'nullable',
                'string',
                Password::default(),
                'confirmed',
            ],
        ]);

        if (isset($dados['password']) && $dados['password'] !== '') {
            $utilizador->password = Hash::make($dados['password']);
        }

        unset($dados['password']);

        $utilizador->fill($dados);
        $utilizador->save();

        return response()->json([
            'message' => 'Utilizador actualizado com sucesso.',
            'utilizador' => $utilizador->only([
                'id_utilizador',
                'nome',
                'email',
                'telefone',
                'tipo_utilizador',
                'ativo',
            ]),
        ]);
    }

    /**
     * Activar ou desactivar uma conta.
     */
    public function alterarEstado(int $id)
    {
        $utilizador = User::findOrFail($id);

        if ($utilizador->tipo_utilizador === 'administrador') {
            throw ValidationException::withMessages([
                'utilizador' => [
                    'Esta operação não pode alterar uma conta de administrador.',
                ],
            ]);
        }

        $utilizador->ativo = !$utilizador->ativo;
        $utilizador->save();

        return response()->json([
            'message' => $utilizador->ativo
                ? 'Conta activada com sucesso.'
                : 'Conta desactivada com sucesso.',
            'utilizador' => $utilizador->only([
                'id_utilizador',
                'nome',
                'email',
                'tipo_utilizador',
                'ativo',
            ]),
        ]);
    }
}

