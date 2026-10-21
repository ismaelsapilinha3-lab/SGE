<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartamentoController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | Estágios ativos por departamento
        |--------------------------------------------------------------------------
        */
        $ativos = DB::table('candidaturas as c')
            ->join(
                'estagios as e',
                'e.id_candidatura',
                '=',
                'c.id_candidatura'
            )
            ->where('e.estado', 'ativo')
            ->select(
                'c.id_departamento_selecionado',
                DB::raw('COUNT(DISTINCT c.id_candidatura) as total')
            )
            ->groupBy('c.id_departamento_selecionado')
            ->pluck('total', 'id_departamento_selecionado');

        return Departamento::where('ativo', true)
            ->orderBy('nome')
            ->get([
                'id_departamento',
                'nome',
                'descricao',
                'ativo',
            ])
            ->map(function (Departamento $departamento) use ($ativos) {
                return [
                    'id_departamento' => $departamento->id_departamento,
                    'nome' => $departamento->nome,
                    'descricao' => $departamento->descricao,
                    'ativo' => $departamento->ativo,
                    'estagiarios_ativos' => (int) $ativos->get(
                        $departamento->id_departamento,
                        0
                    ),
                ];
            });
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