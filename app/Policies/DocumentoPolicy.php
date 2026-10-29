<?php

namespace App\Policies;

use App\Models\Documento;
use App\Models\User;

class DocumentoPolicy
{
    /**
     * O utilizador pode consultar o documento da candidatura?
     */
    public function view(User $user, Documento $documento): bool
    {
        if (! $user->ativo) {
            return false;
        }

        $candidatura = $documento->candidatura;

        if (! $candidatura) {
            return false;
        }

        return match ($user->tipo_utilizador) {
            'administrador' => true,
            'estagiario' => $candidatura->pertenceAo($user),
            'coordenador' => $candidatura->podeSerCoordenadaPor($user),
            default => false,
        };
    }

    /**
     * Só o dono pode substituir o documento, durante a análise.
     */
    public function substituir(User $user, Documento $documento): bool
    {
        $candidatura = $documento->candidatura;

        return $user->ativo
            && $user->tipo_utilizador === 'estagiario'
            && $candidatura !== null
            && $candidatura->pertenceAo($user);
    }
}
