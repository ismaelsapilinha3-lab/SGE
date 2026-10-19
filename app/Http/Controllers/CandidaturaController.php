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

    /**
     * Criar uma conta de estagiário, uma candidatura
     * e guardar os documentos enviados.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => [
                'required',
                'string',
                'max:255',
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

            'curso' => [
                'required',
                'string',
                'max:255',
            ],

            'universidade' => [
                'required',
                'string',
                'max:255',
            ],

            'nascimento' => [
                'required',
                'date',
                'before:today',
            ],

            'sexo' => [
                'required',
                'in:Masculino,Feminino',
            ],

            'bi' => [
                'required',
                'string',
                'max:20',
            ],

            'contacto' => [
                'required',
                'string',
                'max:20',
            ],

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
        ], [
            'nome.required' =>
                'Introduz o teu nome completo.',

            'email.required' =>
                'Introduz o teu endereço de e-mail.',

            'email.email' =>
                'Introduz um endereço de e-mail válido.',

            'email.unique' =>
                'Este e-mail já está registado.',

            'password.required' =>
                'Introduz uma palavra-passe.',

            'password.min' =>
                'A palavra-passe deve ter pelo menos 8 caracteres.',

            'password.confirmed' =>
                'As palavras-passe não coincidem.',

            'curso.required' =>
                'Introduz o teu curso.',

            'universidade.required' =>
                'Introduz o nome da tua universidade.',

            'nascimento.required' =>
                'Introduz a tua data de nascimento.',

            'nascimento.before' =>
                'A data de nascimento deve ser anterior a hoje.',

            'sexo.required' =>
                'Seleciona o sexo.',

            'sexo.in' =>
                'O valor selecionado para o sexo não é válido.',

            'bi.required' =>
                'Introduz o número do BI.',

            'contacto.required' =>
                'Introduz o teu contacto.',

            'id_departamento_selecionado.required' =>
                'Seleciona o departamento pretendido.',

            'id_departamento_selecionado.exists' =>
                'O departamento selecionado não existe.',

            'documentos.declaracao_escolar.required' =>
                'Envia a declaração ou o certificado académico.',

            'documentos.declaracao_escolar.max' =>
                'O documento académico não pode ultrapassar 5 MB.',

            'documentos.declaracao_escolar.mimes' =>
                'O documento académico deve estar em PDF, JPG ou PNG.',

            'documentos.foto.required' =>
                'Envia a foto tipo passe.',

            'documentos.foto.max' =>
                'A foto não pode ultrapassar 5 MB.',

            'documentos.foto.mimes' =>
                'A foto deve estar em JPG ou PNG.',

            'documentos.bi.required' =>
                'Envia a cópia do BI ou passaporte.',

            'documentos.bi.max' =>
                'A cópia do BI não pode ultrapassar 5 MB.',

            'documentos.bi.mimes' =>
                'A cópia do BI deve estar em PDF, JPG ou PNG.',

            'documentos.carta_solicitacao.required' =>
                'Envia a carta de solicitação.',

            'documentos.carta_solicitacao.max' =>
                'A carta não pode ultrapassar 5 MB.',

            'documentos.carta_solicitacao.mimes' =>
                'A carta de solicitação deve estar em PDF.',

            'documentos.cv.required' =>
                'Envia o teu Curriculum Vitae.',

            'documentos.cv.max' =>
                'O Curriculum Vitae não pode ultrapassar 5 MB.',

            'documentos.cv.mimes' =>
                'O Curriculum Vitae deve estar em PDF, JPG ou PNG.',
        ]);

        /*
         * Impedir uma nova candidatura ativa para o mesmo BI.
         */
        $jaTemAtiva = Candidatura::whereIn('estado', [
            'em_analise',
            'aprovada',
        ])
            ->where('bi', $validated['bi'])
            ->exists();

        if ($jaTemAtiva) {
            return response()->json([
                'message' =>
                    'Já existe uma candidatura ativa para este BI.',
            ], 422);
        }

        $pasta = null;

        try {
            $resultado = DB::transaction(function () use (
                $request,
                $validated,
                &$pasta
            ) {
                /*
                 * Criar a conta do estagiário.
                 */
                $utilizador = User::create([
                    'nome' => $validated['nome'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'telefone' => $validated['contacto'],
                    'tipo_utilizador' => 'estagiario',
                    'ativo' => true,
                ]);

                /*
                 * Criar a candidatura.
                 */
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

                /*
                 * Guardar cada documento associado à candidatura.
                 */
                foreach (self::TIPOS_DOCUMENTOS as $tipo) {
                    $ficheiro = $request->file("documentos.$tipo");

                    $caminho = $ficheiro->store($pasta);

                    if (!$caminho) {
                        throw new \RuntimeException(
                            "Não foi possível guardar o documento: {$tipo}."
                        );
                    }

                    Documento::create([
                        'id_candidatura' =>
                            $candidatura->id_candidatura,

                        'tipo_documento' => $tipo,
                        'versao' => 1,

                        'nome_arquivo' =>
                            $ficheiro->getClientOriginalName(),

                        'caminho_arquivo' => $caminho,
                        'data_upload' => now(),
                        'estado' => 'pendente',
                    ]);
                }

                return [
                    'utilizador' => $utilizador,
                    'candidatura' => $candidatura,
                ];
            });

            /*
             * Criar o token de autenticação.
             */
            $token = $resultado['utilizador']
                ->createToken('angular-sge')
                ->plainTextToken;

        } catch (UniqueConstraintViolationException $e) {
            if ($pasta) {
                Storage::deleteDirectory($pasta);
            }

            return response()->json([
                'message' =>
                    'Já existe um utilizador com este e-mail ou outro dado único.',
            ], 422);

        } catch (\Throwable $e) {
            if ($pasta) {
                Storage::deleteDirectory($pasta);
            }

            report($e);

            return response()->json([
                'message' =>
                    'Não foi possível guardar a candidatura. Verifica os dados e tenta novamente.',
            ], 500);
        }

        /*
         * Resposta de sucesso.
         */
        return response()->json([
            'message' =>
                'Candidatura enviada com sucesso! A tua conta foi criada.',

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

    /**
     * Obter a candidatura do estagiário autenticado.
     */
    public function estagioAtivo(Request $request)
    {
        $utilizador = $request->user();

        if (!$utilizador) {
            return response()->json([
                'message' => 'Utilizador nao autenticado.',
            ], 401);
        }

        if ($utilizador->tipo_utilizador !== 'estagiario') {
            return response()->json([
                'message' => 'Esta area e exclusiva para estagiarios.',
            ], 403);
        }

        $estagio = DB::table('estagios')
            ->where('id_estagiario', $utilizador->id_utilizador)
            ->where('estado', 'ativo')
            ->first();

        if (!$estagio) {
            return response()->json([
                'message' => 'Nao existe um estagio activo para esta conta.',
            ], 403);
        }

        return response()->json([
            'id_estagio' => $estagio->id_estagio,
            'id_candidatura' => $estagio->id_candidatura,
            'data_inicio' => $estagio->data_inicio,
            'data_fim_prevista' => $estagio->data_fim_prevista,
            'estado_estagio' => $estagio->estado,
        ]);
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

        $estagio = DB::table('estagios')
            ->where('id_candidatura', $candidatura->id_candidatura)
            ->first();

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
            'message' =>
                'Dados da candidatura carregados com sucesso.',

            'id' => $candidatura->id_candidatura,
            'nome' => $candidatura->nome,
            'bi' => $candidatura->bi,
            'contacto' => $candidatura->contacto,
            'email' => $candidatura->email,
            'nascimento' => $candidatura->nascimento,
            'sexo' => $candidatura->sexo,
            'curso' => $candidatura->curso,
            'universidade' => $candidatura->universidade,

            'departamento' =>
                $departamento?->nome ?? 'Não definido',

            'estado' => $estado,
            'id_estagio' => $estagio?->id_estagio,
            'estado_estagio' => $estagio?->estado,
            'data_submissao' => $candidatura->created_at,

            'documentos' => $documentos->map(function ($documento) {
                return [
                    'id_documento' => $documento->id_documento,
                    'id_candidatura' => $documento->id_candidatura,
                    'tipo_documento' => $documento->tipo_documento,
                    'versao' => $documento->versao,
                    'nome_arquivo' => $documento->nome_arquivo,
                    'caminho_arquivo' => $documento->caminho_arquivo,
                    'data_upload' => $documento->data_upload,
                    'validade' => $documento->validade,
                    'estado' => $documento->estado,
                    'descricao' => $documento->descricao,
                ];
            })->values(),
        ]);
    }
}