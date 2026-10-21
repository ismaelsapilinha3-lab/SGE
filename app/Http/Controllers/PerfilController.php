<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PerfilController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Dados do próprio perfil
    |--------------------------------------------------------------------------
    |
    | Devolve os dados do utilizador autenticado, incluindo o URL da foto de
    | perfil (ou `null` quando não existe).
    |
    */

    public function mostrar(Request $request)
    {
        $utilizador = $request->user();

        return response()->json([
            'utilizador' => [
                'id_utilizador' => $utilizador->id_utilizador,
                'nome' => $utilizador->nome,
                'email' => $utilizador->email,
                'telefone' => $utilizador->telefone,
                'tipo_utilizador' => $utilizador->tipo_utilizador,
                'foto' => $utilizador->foto,
                'foto_url' => $utilizador->fotoUrl(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Atualizar a foto de perfil
    |--------------------------------------------------------------------------
    |
    | Cada utilizador carrega a sua própria foto. O ficheiro anterior é
    | removido do disco para não acumular lixo.
    |
    */

    public function atualizarFoto(Request $request)
    {
        $request->validate([
            'foto' => [
                'required',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png,webp',
            ],
        ], [
            'foto.required' => 'Escolhe uma imagem para a foto de perfil.',
            'foto.max' => 'A foto não pode ultrapassar 5 MB.',
            'foto.mimes' => 'A foto deve estar em JPG, PNG ou WEBP.',
        ]);

        $utilizador = $request->user();

        $anterior = $utilizador->foto;

        $caminho = $request
            ->file('foto')
            ->store('perfis', 'public');

        $utilizador->foto = $caminho;
        $utilizador->save();

        if ($anterior && $anterior !== $caminho) {
            Storage::disk('public')->delete($anterior);
        }

        return response()->json([
            'message' => 'Foto de perfil atualizada com sucesso.',
            'foto' => $utilizador->foto,
            'foto_url' => $utilizador->fotoUrl(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Remover a foto de perfil
    |--------------------------------------------------------------------------
    */

    public function removerFoto(Request $request)
    {
        $utilizador = $request->user();

        if ($utilizador->foto) {
            Storage::disk('public')->delete($utilizador->foto);
        }

        $utilizador->foto = null;
        $utilizador->save();

        return response()->json([
            'message' => 'Foto de perfil removida com sucesso.',
            'foto' => null,
            'foto_url' => null,
        ]);
    }
}
