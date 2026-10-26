<?php

namespace App\Http\Controllers;

use App\Models\DocumentoEstagio;
use App\Services\AcessoFicheiroService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentoEstagioController extends Controller
{
    public function __construct(
        private readonly AcessoFicheiroService $acessos
    ) {}

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

        if (! Gate::forUser($utilizador)->allows('view', $documento)) {
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

        $this->acessos->registarDocumentoEstagio(
            $request,
            $utilizador,
            $documento
        );

        return response()->file(
            $disk->path($documento->caminho_arquivo),
            [
                'Content-Disposition' => 'inline; filename="'.
                    addslashes($documento->nome_arquivo).
                    '"',
            ]
        );
    }
}
