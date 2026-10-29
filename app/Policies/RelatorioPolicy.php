<?php

namespace App\Policies;

use App\Models\Relatorio;
use App\Models\User;

class RelatorioPolicy
{
    /**
     * O utilizador pode consultar o relatório e o respetivo ficheiro?
     */
    public function view(User $user, Relatorio $relatorio): bool
    {
        if (! $user->ativo) {
            return false;
        }

        $estagio = $relatorio->estagio;

        if (! $estagio) {
            return false;
        }

        return match ($user->tipo_utilizador) {
            'administrador' => true,
            'estagiario' => (int) $estagio->id_estagiario
                === (int) $user->id_utilizador,
            'supervisor' => $estagio->supervisoresAtivos()
                ->where('id_supervisor', $user->id_utilizador)
                ->exists(),
            'coordenador' => (bool) $estagio->candidatura
                ?->podeSerCoordenadaPor($user),
            default => false,
        };
    }

    /**
     * Apenas o estagiário dono do estágio cria relatórios.
     */
    public function criar(User $user, Relatorio $relatorio): bool
    {
        return $user->ativo
            && $user->tipo_utilizador === 'estagiario'
            && (int) $relatorio->estagio?->id_estagiario
                === (int) $user->id_utilizador;
    }

    /**
     * A avaliação final do relatório pertence ao administrador.
     */
    public function avaliar(User $user, Relatorio $relatorio): bool
    {
        return $user->ativo
            && $user->tipo_utilizador === 'administrador';
    }
}
