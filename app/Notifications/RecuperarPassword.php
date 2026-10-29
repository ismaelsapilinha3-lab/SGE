<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecuperarPassword extends Notification
{
    public function __construct(
        public readonly string $url,
        public readonly string $nome,
        public readonly int $minutos,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Recuperação de palavra-passe — Portal de Estágios Multitel')
            ->greeting("Olá, {$this->nome}.")
            ->line('Recebemos um pedido para redefinir a palavra-passe da tua conta.')
            ->action('Redefinir palavra-passe', $this->url)
            ->line("Este link é válido durante {$this->minutos} minutos.")
            ->line('Se não pediste esta alteração, ignora este e-mail. A tua palavra-passe mantém-se igual.');
    }
}
