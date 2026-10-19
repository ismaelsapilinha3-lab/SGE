<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartamentoController extends Controller
{
    /**
     * Lista os departamentos activos (consulta pública).
     */
    public function index()
    {
        return Departamento::where('ativo', true)
            ->select('id_departamento', 'nome')
            ->get();
    }

    /**
     * Lista todos os departamentos para a gestão administrativa,
     * incluindo os inactivos e o número de estágios activos.
     */
    public function indexAdmin()
    {
        $estagiosActivos = DB::table('estagios as e')
            ->join(
                'candidaturas as c',
                'c.id_candidatura',
                '=',
                'e.id_candidatura'
            )
            ->where('e.estado', 'ativo')
            ->groupBy('c.id_departamento_selecionado')
            ->select(
                'c.id_departamento_selecionado as id_departamento',
                DB::raw('COUNT(*) as total')
            )
            ->pluck('total', 'id_departamento');

        return Departamento::query()
            ->select('id_departamento', 'nome', 'descricao', 'ativo')
            ->orderBy('nome')
            ->get()
            ->map(function (Departamento $departamento) use ($estagiosActivos) {
                return $departamento->setAttribute(
                    'estagiarios_ativos',
                    (int) ($estagiosActivos[$departamento->id_departamento] ?? 0)
                );
            });
    }

    /**
     * Cria um departamento.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255', 'unique:departamentos,nome'],
            'descricao' => ['nullable', 'string', 'max:2000'],
        ]);

        $departamento = Departamento::create([
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'ativo' => true,
        ]);

        return response()->json([
            'mensagem' => 'Departamento criado com sucesso.',
            'departamento' => $departamento,
        ], 201);
    }

    /**
     * Actualiza um departamento (nome, descrição e estado).
     */
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
                    ->ignore($departamento->id_departamento, 'id_departamento'),
            ],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $departamento->fill($dados);
        $departamento->save();

        return response()->json([
            'mensagem' => 'Departamento actualizado com sucesso.',
            'departamento' => $departamento,
        ]);
    }
}
