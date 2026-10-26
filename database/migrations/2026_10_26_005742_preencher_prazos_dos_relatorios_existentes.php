<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Relatórios criados pela API antiga não tinham mês de referência
         * nem prazo. Sem estes campos não apareciam corretamente na
         * listagem do administrador nem entravam no cálculo de atrasos.
         */
        DB::table('relatorios')
            ->join(
                'estagios',
                'estagios.id_estagio',
                '=',
                'relatorios.id_estagio'
            )
            ->whereNotNull('relatorios.periodo_referencia')
            ->select([
                'relatorios.id_relatorio',
                'relatorios.periodo_referencia',
                'relatorios.mes_referencia',
                'relatorios.prazo_envio',
                'relatorios.submetido_em',
                'relatorios.enviado_em',
                'estagios.dia_limite_relatorio',
            ])
            ->orderBy('relatorios.id_relatorio')
            ->chunk(100, function ($relatorios) {
                foreach ($relatorios as $relatorio) {
                    $mes = $relatorio->mes_referencia
                        ?? $relatorio->periodo_referencia;

                    $dia = (int) ($relatorio->dia_limite_relatorio ?? 0);

                    if ($dia < 1 || $dia > 28) {
                        $dia = 5;
                    }

                    $atualizacoes = [];

                    if ($relatorio->mes_referencia === null) {
                        $atualizacoes['mes_referencia'] = $mes;
                    }

                    if ($relatorio->prazo_envio === null) {
                        $atualizacoes['prazo_envio'] = Carbon::parse($mes)
                            ->startOfMonth()
                            ->addMonthNoOverflow()
                            ->day($dia)
                            ->toDateString();
                    }

                    if (
                        $relatorio->enviado_em === null
                        && $relatorio->submetido_em !== null
                    ) {
                        $atualizacoes['enviado_em'] = $relatorio->submetido_em;
                    }

                    if ($atualizacoes !== []) {
                        DB::table('relatorios')
                            ->where('id_relatorio', $relatorio->id_relatorio)
                            ->update($atualizacoes);
                    }
                }
            });
    }

    public function down(): void
    {
        /*
         * O preenchimento é uma correção de dados e não deve ser revertido
         * para evitar voltar a esconder relatórios no painel.
         */
    }
};
