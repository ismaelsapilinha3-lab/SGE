<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller
{
    public function download(Documento $documento)
    {
        return Storage::download($documento->caminho_arquivo, $documento->nome_arquivo);
    }
}
