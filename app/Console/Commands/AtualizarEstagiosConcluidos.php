<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:atualizar-estagios-concluidos')]
#[Description('Conclui automaticamente os estágios cuja data prevista terminou')]
class AtualizarEstagiosConcluidos extends Command
{
    public function handle(): int
    {
        $hoje = now()->toDateString();

        $estagios = DB::table('estagios')
            ->where('estado', 'ativo')
            ->whereDate('data_fim_prevista', '<=', $hoje)
            ->get(['id_estagio', 'data_fim_prevista']);

        $quantidade = 0;

        foreach ($estagios as $estagio) {
            $atualizados = DB::table('estagios')
                ->where('id_estagio', $estagio->id_estagio)
                ->where('estado', 'ativo')
                ->update([
                    'estado' => 'concluido',
                    'data_fim_real' => $estagio->data_fim_prevista,
                    'updated_at' => now(),
                ]);

            $quantidade += $atualizados;
        }

        $this->info(
            "Verificação concluída. Estágios atualizados: {$quantidade}."
        );

        return self::SUCCESS;
    }
}