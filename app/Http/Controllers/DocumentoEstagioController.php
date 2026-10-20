<?php

namespace App\Http\Controllers;

use App\Models\DocumentoEstagio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoEstagioController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Descarregar um documento de estágio
    |--------------------------------------------------------------------------
    |
    | A autorização depende do papel: o estagiário vê os seus; o supervisor,
    | os dos estágios que acompanha; o coordenador, os da sua direção.
    |
    */

    public function download(
        Request $request,
        DocumentoEstagio $documento
    ) {
        $utilizador = $request->user();

        if (! $utilizador || ! $utilizador->ativo) {
            return response()->json([
                'message' => 'Utilizador não autorizado.',
            ], 403);
        }

        if (! $this->podeConsultar($utilizador, $documento)) {
            return response()->json([
                'message' => 'Não tem autorização para consultar este documento.',
            ], 403);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($documento->caminho_arquivo)) {
            return response()->json([
                'message' => 'O ficheiro solicitado não foi encontrado.',
            ], 404);
        }

        return response()->file(
            $disk->path($documento->caminho_arquivo),
            [
                'Content-Disposition' => 'inline; filename="'.
                    addslashes($documento->nome_arquivo).
                    '"',
            ]
        );
    }

    private function podeConsultar(
        User $utilizador,
        DocumentoEstagio $documento
    ): bool {

        if ($utilizador->tipo_utilizador === 'administrador') {
            return true;
        }

        if ($utilizador->tipo_utilizador === 'estagiario') {
            return (int) $documento->id_estagiario
                === (int) $utilizador->id_utilizador;
        }

        $estagio = $documento->estagio;

        if (! $estagio) {
            return false;
        }

        if ($utilizador->tipo_utilizador === 'supervisor') {
            return $estagio->supervisores()
                ->where('id_supervisor', $utilizador->id_utilizador)
                ->whereNull('removido_em')
                ->exists();
        }

        if ($utilizador->tipo_utilizador === 'coordenador') {
            return (int) $estagio->candidatura?->id_coordenador
                === (int) $utilizador->id_utilizador;
        }

        return false;
    }
}
