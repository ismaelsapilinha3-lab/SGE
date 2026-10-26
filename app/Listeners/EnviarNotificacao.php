<?php

namespace App\Listeners;

use App\Events\NotificacaoSolicitada;
use App\Models\Notificacao;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class EnviarNotificacao implements ShouldQueue
{
    public function handle(NotificacaoSolicitada $event): void
    {
        if ($event->idCandidatura !== null) {
            Notificacao::paraCandidatoPorId(
                $event->idCandidatura,
                $event->idsUtilizadores[0] ?? null,
                $event->titulo,
                $event->mensagem
            );

            return;
        }

        foreach (array_unique($event->idsUtilizadores) as $idUtilizador) {
            Notificacao::enviar(
                (int) $idUtilizador,
                $event->titulo,
                $event->mensagem
            );
        }

        if ($event->idsUtilizadores === []) {
            Log::warning(
                'Notificação sem destinatários.',
                ['titulo' => $event->titulo]
            );
        }
    }
}
