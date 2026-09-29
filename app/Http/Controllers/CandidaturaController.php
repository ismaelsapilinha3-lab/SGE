<?php

namespace App\Http\Controllers;

use App\Models\Candidatura;
use App\Models\Documento;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CandidaturaController extends Controller
{
    private const TIPOS = ['declaracao_escolar', 'foto', 'bi', 'carta_solicitacao', 'cv'];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'curso' => ['required', 'string', 'max:255'],
            'universidade' => ['required', 'string', 'max:255'],
            'nascimento' => ['required', 'date', 'before:today'],
            'sexo' => ['required', 'in:Masculino,Feminino'],
            'bi' => ['required', 'string', 'max:20'],
            'contacto' => ['required', 'string', 'max:20'],
            'id_departamento_selecionado' => ['required', 'exists:departamentos,id_departamento'],

            'documentos.declaracao_escolar' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'documentos.foto' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png'],
            'documentos.bi' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'documentos.carta_solicitacao' => ['required', 'file', 'max:5120', 'mimes:pdf'],
            'documentos.cv' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        // Regra: só uma candidatura ativa por estudante (mesmo BI)
        $jaTemAtiva = Candidatura::whereIn('estado', ['em_analise', 'aprovada'])
            ->where('bi', $validated['bi'])
            ->exists();

        if ($jaTemAtiva) {
            return response()->json([
                'message' => 'Já existe uma candidatura ativa para este BI.',
            ], 422);
        }

        $pasta = null;

        try {
            $candidatura = DB::transaction(function () use ($request, $validated, &$pasta) {
                $candidatura = Candidatura::create([
                    'nome' => $validated['nome'],
                    'email' => $validated['email'],
                    'curso' => $validated['curso'],
                    'universidade' => $validated['universidade'],
                    'nascimento' => $validated['nascimento'],
                    'sexo' => $validated['sexo'],
                    'bi' => $validated['bi'],
                    'contacto' => $validated['contacto'],
                    'id_departamento_selecionado' => $validated['id_departamento_selecionado'],
                    'estado' => 'em_analise',
                ]);

                $pasta = "documentos/{$candidatura->id_candidatura}";

                foreach (self::TIPOS as $tipo) {
                    $ficheiro = $request->file("documentos.$tipo");

                    Documento::create([
                        'id_candidatura' => $candidatura->id_candidatura,
                        'tipo_documento' => $tipo,
                        'versao' => 1,
                        'nome_arquivo' => $ficheiro->getClientOriginalName(),
                        'caminho_arquivo' => $ficheiro->store($pasta),
                        'data_upload' => now(),
                        'estado' => 'pendente',
                    ]);
                }

                return $candidatura;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Dois envios simultâneos com o mesmo BI: a base de dados travou o segundo
            if ($pasta) {
                Storage::deleteDirectory($pasta);
            }

            return response()->json([
                'message' => 'Já existe uma candidatura ativa para este BI.',
            ], 422);
        } catch (\Throwable $e) {
            if ($pasta) {
                Storage::deleteDirectory($pasta);
            }
            report($e);

            return response()->json(['message' => 'Não foi possível guardar a candidatura.'], 500);
        }

        return response()->json(['id_candidatura' => $candidatura->id_candidatura], 201);
    }
}