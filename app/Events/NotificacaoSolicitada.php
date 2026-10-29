<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pedido de notificação interna.
 *
 * A entrega é feita por um listener em fila, depois de a transação de
 * negócio estar concluída. Assim, um pedido de notificação nunca fica
 * visível se a operação principal falhar.
 */
class NotificacaoSolicitada implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<int, int>  $idsUtilizadores
     */
    public function __construct(
        public readonly array $idsUtilizadores,
        public readonly string $titulo,
        public readonly string $mensagem,
        public readonly ?int $idCandidatura = null,
        public readonly ?string $url = null,
    ) {}
}
