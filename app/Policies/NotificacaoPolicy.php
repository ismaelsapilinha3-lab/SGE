<?php

namespace App\Policies;

use App\Models\Notificacao;
use App\Models\User;

class NotificacaoPolicy
{
    /**
     * Só o destinatário pode consultar a notificação.
     */
    public function view(User $user, Notificacao $notificacao): bool
    {
        return $user->ativo
            && (int) $notificacao->id_utilizador
                === (int) $user->id_utilizador;
    }

    /**
     * Só o destinatário pode remover a notificação.
     */
    public function delete(User $user, Notificacao $notificacao): bool
    {
        return $this->view($user, $notificacao);
    }
}
