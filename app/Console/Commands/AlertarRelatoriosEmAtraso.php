<?php

namespace App\Console\Commands;

use App\Models\Estagio;
use App\Models\Relatorio;
use App\Services\NotificacaoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('app:alertar-relatorios-em-atraso')]
#[Description('Marca os relatórios fora de prazo e avisa o administrador')]
class AlertarRelatoriosEmAtraso extends Command
{
    public function __construct(
        private readonly NotificacaoService $notificacoes
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /*
         * Primeiro, procura-se por meses sem relatório mensal entregue em
         * estágios ativos. Assim, o aviso também dispara quando o
         * estagiário simplesmente não envia o relatório.
         */
        $criados = $this->registarMesesEmFalta();

        $hoje = now()->toDateString();

        /*
         * Relatórios cujo prazo de entrega já passou e que ainda não
         * foram entregues pelo estagiário.
         */
        $relatorios = Relatorio::query()
            ->whereNull('enviado_em')
            ->whereNull('entrega_confirmada_em')
            ->whereNotNull('prazo_envio')
            ->where('prazo_envio', '<', $hoje)
            ->get();

        if ($relatorios->isEmpty() && $criados === 0) {
            $this->info('Não existem relatórios em atraso.');

            return self::SUCCESS;
        }

        $marcados = 0;
        $avisos = 0;

        foreach ($relatorios as $relatorio) {
            $atualizados = Relatorio::query()
                ->where('id_relatorio', $relatorio->id_relatorio)
                ->whereNull('enviado_em')
                ->update([
                    'estado_avaliacao' => Relatorio::ESTADO_EM_ATRASO,
                    'updated_at' => now(),
                ]);

            if ($atualizados > 0) {
                $marcados++;
            }

            /*
             * O aviso ao administrador é enviado uma única vez.
             */
            if ($relatorio->alerta_atraso_enviado_em) {
                continue;
            }

            $mensagem = sprintf(
                'O estagiário do estágio %d não entregou o relatório %s de %s. O prazo terminou em %s.',
                $relatorio->id_estagio,
                $relatorio->tipo,
                $relatorio->mes_referencia?->format('m/Y') ?? 'período anterior',
                $relatorio->prazo_envio?->format('d/m/Y') ?? '-'
            );

            $this->notificacoes->paraAdministradores(
                'Relatório em atraso',
                $mensagem
            );

            $relatorio->forceFill([
                'alerta_atraso_enviado_em' => now(),
            ])->save();

            $avisos++;
        }

        $this->info(
            "Meses registados sem relatório: {$criados}. "
            ."Relatórios marcados em atraso: {$marcados}. "
            ."Avisos enviados ao administrador: {$avisos}."
        );

        return self::SUCCESS;
    }

    /**
     * Regista um relatório mensal em atraso por cada mês decorrido do
     * estágio que ficou sem entrega. Devolve o número de registos criados.
     */
    private function registarMesesEmFalta(): int
    {
        $criados = 0;

        $estagios = Estagio::query()
            ->where('estado', 'ativo')
            ->whereNotNull('data_inicio')
            ->get();

        foreach ($estagios as $estagio) {
            $primeiroMes = $estagio->data_inicio->copy()->startOfMonth();

            /*
             * O último mês a considerar é o mês anterior ao atual, visto
             * que o relatório de um mês é entregue no mês seguinte.
             */
            $ultimoMes = now()->startOfMonth()->subMonthNoOverflow();

            if ($primeiroMes->greaterThan($ultimoMes)) {
                continue;
            }

            $existente = Relatorio::query()
                ->where('id_estagio', $estagio->id_estagio)
                ->where('tipo', 'mensal')
                ->pluck('mes_referencia')
                ->map(fn ($data) => Carbon::parse($data)->format('Y-m'))
                ->all();

            $mes = $primeiroMes->copy();

            while ($mes->lessThanOrEqualTo($ultimoMes)) {
                $chave = $mes->format('Y-m');

                if (! in_array($chave, $existente, true)) {
                    $prazo = $estagio->prazoParaMes($mes);

                    /*
                     * Só se cria o registo depois do prazo ter passado.
                     */
                    if ($prazo->toDateString() < now()->toDateString()) {
                        Relatorio::create([
                            'id_estagio' => $estagio->id_estagio,
                            'tipo' => 'mensal',
                            'mes_referencia' => $mes->toDateString(),
                            'periodo_referencia' => $mes->toDateString(),
                            'prazo_envio' => $prazo->toDateString(),
                            'atividades' => 'Relatório mensal não entregue.',
                            'resultados' => 'Aguarda entrega pelo estagiário.',
                            'estado_avaliacao' => Relatorio::ESTADO_EM_ATRASO,
                        ]);

                        $criados++;
                    }
                }

                $mes->addMonthNoOverflow();
            }
        }

        return $criados;
    }
}
