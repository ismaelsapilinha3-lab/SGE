<?php

namespace App\Policies;

use App\Models\DocumentoEstagio;
use App\Models\Estagio;
use App\Models\User;

class DocumentoEstagioPolicy
{
    /**
     * O utilizador pode consultar o documento do estágio?
     */
    public function view(
        User $user,
        DocumentoEstagio $documentoEstagio
    ): bool {

        if (! $user->ativo) {
            return false;
        }

        if ($user->tipo_utilizador === 'administrador') {
            return true;
        }

        if ($user->tipo_utilizador === 'estagiario') {
            return (int) $documentoEstagio->id_estagiario
                === (int) $user->id_utilizador;
        }

        $estagio = $documentoEstagio->estagio;

        if (! $estagio) {
            return false;
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
     * O supervisor atribuído pode validar ou rejeitar o documento.
     */
    public function validar(
        User $user,
        DocumentoEstagio $documentoEstagio
    ): bool {
        if (! $user->ativo || $user->tipo_utilizador !== 'supervisor') {
            return false;
        }

        $estagio = $documentoEstagio->estagio;

        return $estagio !== null
            && $estagio->supervisoresAtivos()
                ->where('id_supervisor', $user->id_utilizador)
                ->exists();
    }

    /**
     * O coordenador vê os estágios da sua direção, incluindo as
     * candidaturas que ainda não têm coordenador atribuído.
     */
    private function coordenaEstagio(
        User $user,
        Estagio $estagio
    ): bool {
        return (bool) $estagio->candidatura?->podeSerCoordenadaPor($user);
    }
}
