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
        string $mensagem
    ): void {

        $this->paraUtilizadores(
            $utilizador === null ? [] : [$utilizador],
            $titulo,
            $mensagem
        );
    }

    public function paraUtilizadores(
        iterable $utilizadores,
        string $titulo,
        string $mensagem
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

        NotificacaoSolicitada::dispatch($ids, $titulo, $mensagem);
    }

    public function paraCandidato(
        Candidatura $candidatura,
        string $titulo,
        string $mensagem
    ): void {

        NotificacaoSolicitada::dispatch(
            $candidatura->id_utilizador !== null
                ? [(int) $candidatura->id_utilizador]
                : [],
            $titulo,
            $mensagem,
            $candidatura->id_candidatura
        );
    }

    public function paraAdministradores(
        string $titulo,
        string $mensagem
    ): int {

        $ids = User::query()
            ->where('tipo_utilizador', 'administrador')
            ->pluck('id_utilizador')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return 0;
        }

        NotificacaoSolicitada::dispatch($ids, $titulo, $mensagem);

        return count($ids);
    }
}
