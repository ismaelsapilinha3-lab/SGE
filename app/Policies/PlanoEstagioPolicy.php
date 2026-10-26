<?php

namespace App\Policies;

use App\Models\Estagio;
use App\Models\PlanoEstagio;
use App\Models\User;

class PlanoEstagioPolicy
{
    /**
     * O utilizador pode consultar o plano de estágio?
     */
    public function view(User $user, PlanoEstagio $plano): bool
    {
        if (! $user->ativo) {
            return false;
        }

        if ($user->tipo_utilizador === 'administrador') {
            return true;
        }

        $estagio = $plano->estagio;

        if (! $estagio) {
            return false;
        }

        if ($user->tipo_utilizador === 'estagiario') {
            return (int) $estagio->id_estagiario
                === (int) $user->id_utilizador;
        }

        if ($user->tipo_utilizador === 'supervisor') {
            return $estagio->supervisores()
                ->where('id_supervisor', $user->id_utilizador)
                ->whereNull('removido_em')
                ->exists();
        }

        if ($user->tipo_utilizador === 'coordenador') {
            return $this->coordenaEstagio($user, $estagio);
        }

        return false;
    }

    /**
     * O coordenador pode decidir um plano de atividades submetido.
     */
    public function decidir(User $user, PlanoEstagio $plano): bool
    {
        if (! $user->ativo || $user->tipo_utilizador !== 'coordenador') {
            return false;
        }

        if (! $plano->foiSubmetidoPeloSupervisor()) {
            return false;
        }

        if ($plano->estado !== 'submetido') {
            return false;
        }

        $estagio = $plano->estagio;

        return $estagio !== null
            && $this->coordenaEstagio($user, $estagio);
    }

    private function coordenaEstagio(User $user, Estagio $estagio): bool
    {
        return (bool) $estagio->candidatura?->podeSerCoordenadaPor($user);
    }
}
