<?php

namespace App\Policies;

use App\Models\Candidatura;
use App\Models\User;

class CandidaturaPolicy
{
    /**
     * O utilizador pode consultar a candidatura?
     */
    public function view(User $user, Candidatura $candidatura): bool
    {
        if (! $user->ativo) {
            return false;
        }

        return match ($user->tipo_utilizador) {
            'administrador' => true,
            'coordenador' => $candidatura->podeSerCoordenadaPor($user),
            'estagiario' => $candidatura->pertenceAo($user),
            default => false,
        };
    }

    /**
     * O coordenador pode tomar a decisão final?
     */
    public function decidir(User $user, Candidatura $candidatura): bool
    {
        return $user->ativo
            && $user->tipo_utilizador === 'coordenador'
            && $candidatura->estado === 'em_analise'
            && $candidatura->podeSerCoordenadaPor($user);
    }

    /**
     * O administrador pode encaminhar a candidatura para a direção?
     */
    public function encaminhar(User $user, Candidatura $candidatura): bool
    {
        return $user->ativo
            && $user->tipo_utilizador === 'administrador'
            && $candidatura->estado === 'em_analise';
    }
}
