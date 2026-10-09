<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EstagiarioController extends Controller
{
    public function minhaCandidatura(Request $request)
    {
        $utilizador = $request->user();

        $candidatura = \App\Models\Candidatura::with([
            'departamento',
            'documentos'
        ])
            ->where('email', $utilizador->email)
            ->latest('id_candidatura')
            ->first();

        if (!$candidatura) {
            return response()->json([
                'message' => 'Não foi encontrada nenhuma candidatura associada a este utilizador.'
            ], 404);
        }

        return response()->json([
            'id_candidatura' => $candidatura->id_candidatura,
            'nome' => $candidatura->nome,
            'email' => $candidatura->email,
            'curso' => $candidatura->curso,
            'universidade' => $candidatura->universidade,
            'nascimento' => $candidatura->nascimento,
            'sexo' => $candidatura->sexo,
            'bi' => $candidatura->bi,
            'contacto' => $candidatura->contacto,

            'estado' => $this->converterEstado(
                $candidatura->estado
            ),

            'data_submissao' => $candidatura->created_at,

            'departamento' => $candidatura->departamento?->nome,

            'documentos' => $candidatura->documentos->map(function ($documento) {
                return [
                    'id_documento' => $documento->id_documento,
                    'id_candidatura' => $documento->id_candidatura,
                    'tipo_documento' => $documento->tipo_documento,
                    'nome_arquivo' => $documento->nome_arquivo,
                    'caminho_arquivo' => $documento->caminho_arquivo,
                    'data_upload' => $documento->data_upload,
                    'validade' => $documento->validade,
                    'estado' => $documento->estado,
                    'descricao' => $documento->descricao,
                ];
            }),
        ]);
    }

    private function converterEstado(string $estado): string
    {
        return match ($estado) {
            'em_analise' => 'analise',
            'aprovada' => 'aceite',
            'recusada' => 'recusado',
            default => 'analise',
        };
    }
}