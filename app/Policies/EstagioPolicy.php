<?php

namespace App\Policies;

use App\Models\Estagio;
use App\Models\User;

class EstagioPolicy
{
    /**
     * O utilizador pode consultar o estágio?
     */
    public function view(User $user, Estagio $estagio): bool
    {
        if (! $user->ativo) {
            return false;
        }

        return match ($user->tipo_utilizador) {
            'administrador' => true,
            'coordenador' => (bool) $estagio->candidatura
                ?->podeSerCoordenadaPor($user),
            'supervisor' => $this->eSupervisor($user, $estagio),
            'estagiario' => (int) $estagio->id_estagiario
                === (int) $user->id_utilizador,
            default => false,
        };
    }

    /**
     * Apenas o administrador altera o estado do estágio.
     */
    public function gerir(User $user, Estagio $estagio): bool
    {
        return $user->ativo
            && $user->tipo_utilizador === 'administrador';
    }

    /**
     * O supervisor atribuído pode submeter o plano de atividades.
     */
    public function submeterPlano(User $user, Estagio $estagio): bool
    {
        return $user->ativo
            && $user->tipo_utilizador === 'supervisor'
            && ! $estagio->estaTerminado()
            && $this->eSupervisor($user, $estagio);
    }

    /**
     * O coordenador da direção pode decidir o plano de atividades.
     */
    public function aprovarPlano(User $user, Estagio $estagio): bool
    {
        return $user->ativo
            && $user->tipo_utilizador === 'coordenador'
            && ! $estagio->estaTerminado()
            && (bool) $estagio->candidatura
                ?->podeSerCoordenadaPor($user);
    }

    private function eSupervisor(User $user, Estagio $estagio): bool
    {
        return $estagio->supervisoresAtivos()
            ->where('id_supervisor', $user->id_utilizador)
            ->exists();
    }
}
