<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartamentoController extends Controller
{
    public function index()
    {
        return Departamento::where('ativo', true)
            ->select(
                'id_departamento',
                'nome',
                'descricao',
                'ativo'
            )
            ->get();
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departamentos', 'nome'),
            ],
            'descricao' => ['nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $departamento = Departamento::create($dados);

        return response()->json([
            'mensagem' => 'Departamento criado com sucesso.',
            'departamento' => $departamento,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $departamento = Departamento::findOrFail($id);

        $dados = $request->validate([
            'nome' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('departamentos', 'nome')
                    ->ignore(
                        $departamento->id_departamento,
                        'id_departamento'
                    ),
            ],
            'descricao' => ['sometimes', 'nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $departamento->update($dados);

        return response()->json([
            'mensagem' => 'Departamento atualizado com sucesso.',
            'departamento' => $departamento->fresh(),
        ]);
    }
}