<?php

namespace App\Policies;

use App\Models\Candidatura;
use App\Models\Entrevista;
use App\Models\User;

class EntrevistaPolicy
{
    /**
     * O utilizador pode consultar a entrevista?
     */
    public function view(User $user, Entrevista $entrevista): bool
    {
        if (! $user->ativo) {
            return false;
        }

        if ($user->tipo_utilizador === 'administrador') {
            return true;
        }

        $candidatura = $entrevista->candidatura;

        return match ($user->tipo_utilizador) {
            'coordenador' => $candidatura?->podeSerCoordenadaPor($user)
                ?? false,
            'supervisor' => (int) $entrevista->id_supervisor_representante
                === (int) $user->id_utilizador,
            'estagiario' => $candidatura?->pertenceAo($user) ?? false,
            default => false,
        };
    }

    /**
     * O administrador pode agendar uma entrevista para a candidatura?
     */
    public function agendar(User $user, Candidatura $candidatura): bool
    {
        return $user->ativo
            && $user->tipo_utilizador === 'administrador'
            && $candidatura->estado === 'em_analise'
            && ! $candidatura->entrevista()->exists();
    }

    /**
     * O administrador pode reagendar uma entrevista ainda pendente?
     */
    public function atualizar(User $user, Entrevista $entrevista): bool
    {
        return $user->ativo
            && $user->tipo_utilizador === 'administrador'
            && $entrevista->candidatura?->estado === 'em_analise'
            && ! $entrevista->estaDecidida();
    }

    /**
     * O administrador pode registar o resultado da entrevista?
     */
    public function registarResultado(
        User $user,
        Entrevista $entrevista
    ): bool {
        return $this->atualizar($user, $entrevista);
    }
}
