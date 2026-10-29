<?php

namespace App\Services;

use App\Events\NotificacaoSolicitada;
use App\Models\Candidatura;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificacaoService
{
    public function paraUtilizador(
        User|int|null $utilizador,
        string $titulo,
        string $mensagem,
        ?string $url = null
    ): void {

        $this->paraUtilizadores(
            $utilizador === null ? [] : [$utilizador],
            $titulo,
            $mensagem,
            $url
        );
    }

    public function paraUtilizadores(
        iterable $utilizadores,
        string $titulo,
        string $mensagem,
        ?string $url = null
    ): void {

        $ids = collect($utilizadores)
            ->map(fn (User|int|null $utilizador) => $utilizador instanceof User
                ? $utilizador->id_utilizador
                : $utilizador)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            Log::warning(
                'Notificação sem destinatários.',
                ['titulo' => $titulo]
            );

            return;
        }

        NotificacaoSolicitada::dispatch(
            $ids,
            $titulo,
            $mensagem,
            null,
            $url
        );
    }

    public function paraCandidato(
        Candidatura $candidatura,
        string $titulo,
        string $mensagem,
        ?string $url = null
    ): void {

        NotificacaoSolicitada::dispatch(
            $candidatura->id_utilizador !== null
                ? [(int) $candidatura->id_utilizador]
                : [],
            $titulo,
            $mensagem,
            $candidatura->id_candidatura,
            $url
        );
    }

    public function paraAdministradores(
        string $titulo,
        string $mensagem,
        ?string $url = null
    ): int {

        $ids = User::query()
            ->where('tipo_utilizador', 'administrador')
            ->pluck('id_utilizador')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return 0;
        }

        NotificacaoSolicitada::dispatch(
            $ids,
            $titulo,
            $mensagem,
            null,
            $url
        );

        return count($ids);
    }
}
