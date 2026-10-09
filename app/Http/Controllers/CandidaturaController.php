<?php

namespace App\Http\Controllers;

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CandidaturaController extends Controller
{
    private const TIPOS_DOCUMENTOS = [
        'declaracao_escolar',
        'foto',
        'bi',
        'carta_solicitacao',
        'cv',
    ];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
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

            'curso' => ['required', 'string', 'max:255'],
            'universidade' => ['required', 'string', 'max:255'],
            'nascimento' => ['required', 'date', 'before:today'],
            'sexo' => ['required', 'in:Masculino,Feminino'],
            'bi' => ['required', 'string', 'max:20'],
            'contacto' => ['required', 'string', 'max:20'],

            'id_departamento_selecionado' => [
                'required',
                'exists:departamentos,id_departamento',
            ],

            'documentos.declaracao_escolar' => [
                'required',
                'file',
                'max:5120',
                'mimes:pdf,jpg,jpeg,png',
            ],

            'documentos.foto' => [
                'required',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png',
            ],

            'documentos.bi' => [
                'required',
                'file',
                'max:5120',
                'mimes:pdf,jpg,jpeg,png',
            ],

            'documentos.carta_solicitacao' => [
                'required',
                'file',
                'max:5120',
                'mimes:pdf',
            ],

            'documentos.cv' => [
                'required',
                'file',
                'max:5120',
                'mimes:pdf,jpg,jpeg,png',
            ],
        ]);

        $jaTemAtiva = Candidatura::whereIn('estado', [
            'em_analise',
            'aprovada',
        ])
            ->where('bi', $validated['bi'])
            ->exists();

        if ($jaTemAtiva) {
            return response()->json([
                'message' => 'Já existe uma candidatura ativa para este BI.',
            ], 422);
        }

        $pasta = null;

        try {
            $resultado = DB::transaction(function () use (
                $request,
                $validated,
                &$pasta
            ) {
                $utilizador = User::create([
                    'nome' => $validated['nome'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'telefone' => $validated['contacto'],
                    'tipo_utilizador' => 'estagiario',
                    'ativo' => true,
                ]);

                $candidatura = Candidatura::create([
                    'nome' => $validated['nome'],
                    'email' => $validated['email'],
                    'curso' => $validated['curso'],
                    'universidade' => $validated['universidade'],
                    'nascimento' => $validated['nascimento'],
                    'sexo' => $validated['sexo'],
                    'bi' => $validated['bi'],
                    'contacto' => $validated['contacto'],
                    'id_departamento_selecionado' =>
                        $validated['id_departamento_selecionado'],
                    'estado' => 'em_analise',
                ]);

                $pasta = "documentos/{$candidatura->id_candidatura}";

                foreach (self::TIPOS_DOCUMENTOS as $tipo) {
                    $ficheiro = $request->file("documentos.$tipo");

                    Documento::create([
                        'id_candidatura' =>
                            $candidatura->id_candidatura,

                        'tipo_documento' =>
                            $tipo,

                        'versao' => 1,

                        'nome_arquivo' =>
                            $ficheiro->getClientOriginalName(),

                        'caminho_arquivo' =>
                            $ficheiro->store($pasta),

                        'data_upload' => now(),

                        'estado' => 'pendente',
                    ]);
                }

                return [
                    'utilizador' => $utilizador,
                    'candidatura' => $candidatura,
                ];
            });

            $token = $resultado['utilizador']
                ->createToken('angular-sge')
                ->plainTextToken;

        } catch (UniqueConstraintViolationException $e) {

            if ($pasta) {
                Storage::deleteDirectory($pasta);
            }

            return response()->json([
                'message' => 'Já existe um utilizador com este e-mail.',
            ], 422);

        } catch (\Throwable $e) {

            if ($pasta) {
                Storage::deleteDirectory($pasta);
            }

            report($e);

            return response()->json([
                'message' => 'Não foi possível guardar a candidatura.',
            ], 500);
        }

        return response()->json([
            'id_candidatura' =>
                $resultado['candidatura']->id_candidatura,

            'token' => $token,

            'user' => [
                'id_utilizador' =>
                    $resultado['utilizador']->id_utilizador,

                'nome' =>
                    $resultado['utilizador']->nome,

                'email' =>
                    $resultado['utilizador']->email,

                'tipo_utilizador' =>
                    $resultado['utilizador']->tipo_utilizador,
            ],
        ], 201);
    }

    public function minhaCandidatura(Request $request)
    {
        $utilizador = $request->user();

        if (!$utilizador) {
            return response()->json([
                'message' => 'Utilizador não autenticado.',
            ], 401);
        }

        $candidatura = Candidatura::where(
            'email',
            $utilizador->email
        )
            ->latest('created_at')
            ->first();

        if (!$candidatura) {
            return response()->json([
                'message' =>
                    'Não foi encontrada uma candidatura para este utilizador.',
            ], 404);
        }

        $departamento = Departamento::where(
            'id_departamento',
            $candidatura->id_departamento_selecionado
        )->first();

        $documentos = Documento::where(
            'id_candidatura',
            $candidatura->id_candidatura
        )
            ->orderBy('tipo_documento')
            ->get();

        $estado = match ($candidatura->estado) {
            'aprovada' => 'aceite',
            'recusada' => 'recusado',
            'em_analise' => 'analise',
            default => 'analise',
        };

        return response()->json([
            'id' =>
                $candidatura->id_candidatura,

            'nome' =>
                $candidatura->nome,

            'bi' =>
                $candidatura->bi,

            'contacto' =>
                $candidatura->contacto,

            'email' =>
                $candidatura->email,

            'nascimento' =>
                $candidatura->nascimento,

            'sexo' =>
                $candidatura->sexo,

            'curso' =>
                $candidatura->curso,

            'universidade' =>
                $candidatura->universidade,

            'departamento' =>
                $departamento?->nome ?? 'Não definido',

            'estado' =>
                $estado,

            'data_submissao' =>
                $candidatura->created_at,

            'documentos' =>
                $documentos->map(function ($documento) {
                    return [
                        'id_documento' =>
                            $documento->id_documento,

                        'id_candidatura' =>
                            $documento->id_candidatura,

                        'tipo_documento' =>
                            $documento->tipo_documento,

                        'versao' =>
                            $documento->versao,

                        'nome_arquivo' =>
                            $documento->nome_arquivo,

                        'caminho_arquivo' =>
                            $documento->caminho_arquivo,

                        'data_upload' =>
                            $documento->data_upload,

                        'validade' =>
                            $documento->validade,

                        'estado' =>
                            $documento->estado,

                        'descricao' =>
                            $documento->descricao,
                    ];
                })->values(),
        ]);
    }
}

