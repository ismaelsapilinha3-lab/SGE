<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller
{
    public function download(Documento $documento)
    {
        $disk = Storage::disk('local');

        // Verifica se o ficheiro realmente existe
        if (!$disk->exists($documento->caminho_arquivo)) {
            return response()->json([
                'message' => 'O ficheiro solicitado não foi encontrado.'
            ], 404);
        }

        $caminho = $disk->path($documento->caminho_arquivo);

        // Abre o ficheiro diretamente no navegador
        return response()->file(
            $caminho,
            [
                'Content-Disposition' => 'inline; filename="' .
                    addslashes($documento->nome_arquivo) .
                    '"',
            ]
        );
    }
}


