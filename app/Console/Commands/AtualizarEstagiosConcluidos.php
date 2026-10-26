<?php

namespace App\Console\Commands;

use App\Models\Estagio;
use App\Models\HistoricoEstado;
use App\Services\NotificacaoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:atualizar-estagios-concluidos')]
#[Description('Conclui automaticamente os estágios cuja data prevista terminou')]
class AtualizarEstagiosConcluidos extends Command
{
    public function __construct(
        private readonly NotificacaoService $notificacoes
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $hoje = now()->toDateString();

        $ids = Estagio::query()
            ->where('estado', 'ativo')
            ->where('data_fim_prevista', '<', $hoje)
            ->pluck('id_estagio');

        $quantidade = 0;

        foreach ($ids as $id) {
            if ($this->concluirEstagio((int) $id)) {
                $quantidade++;
            }
        }

        $this->info(
            "Verificação concluída. Estágios atualizados: {$quantidade}."
        );

        return self::SUCCESS;
    }

    /**
     * Conclui um estágio, registando a transição no histórico de estados.
     *
     * Devolve `false` quando o estágio já não estava `ativo` (por exemplo,
     * já concluído ou cancelado entretanto).
     */
    private function concluirEstagio(int $idEstagio): bool
    {
        return DB::transaction(function () use ($idEstagio) {
            $estagio = Estagio::query()
                ->where('id_estagio', $idEstagio)
                ->lockForUpdate()
                ->first();

            if (! $estagio || ! $estagio->estaAtivo()) {
                return false;
            }

            $estadoAnterior = $estagio->estado;

            $estagio->update([
                'estado' => 'concluido',
                'data_fim_real' => $estagio->data_fim_real
                    ?? $estagio->data_fim_prevista,
            ]);

            HistoricoEstado::create([
                'id_estagio' => $estagio->id_estagio,
                'estado_anterior' => $estadoAnterior,
                'estado_novo' => 'concluido',
                'motivo' => 'Concluído automaticamente ao atingir a data prevista de fim.',
                'id_utilizador' => null,
            ]);

            $this->notificacoes->paraUtilizador(
                $estagio->id_estagiario,
                'Estágio concluído',
                "O teu estágio #{$estagio->id_estagio} foi concluído por ter chegado ao fim previsto."
            );

            $this->notificacoes->paraUtilizadores(
                $estagio->supervisoresUtilizadores(),
                'Estágio concluído',
                "O estágio #{$estagio->id_estagio} foi concluído por ter chegado ao fim previsto."
            );

            return true;
        });
    }
}
