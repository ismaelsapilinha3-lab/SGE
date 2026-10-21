<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A tabela pode ter ficado a meio de uma tentativa anterior,
         * por isso cada coluna só é criada se ainda não existir.
         */
        Schema::table('relatorios', function (Blueprint $table) {
            if (!Schema::hasColumn('relatorios', 'mes_referencia')) {
                /*
                 * Mês a que o relatório diz respeito (primeiro dia do mês).
                 * Substitui o antigo período em texto.
                 */
                $table->date('mes_referencia')->nullable()->after('periodo_referencia');
            }

            if (!Schema::hasColumn('relatorios', 'prazo_envio')) {
                /*
                 * Prazo de entrega definido pelo administrador para o
                 * período a que o relatório diz respeito.
                 */
                $table->date('prazo_envio')->nullable()->after('mes_referencia');
            }

            if (!Schema::hasColumn('relatorios', 'enviado_em')) {
                /*
                 * Momento em que o estagiário enviou o relatório.
                 * Permite mostrar a data e a hora do envio.
                 */
                $table->timestamp('enviado_em')->nullable()->after('submetido_em');
            }

            if (!Schema::hasColumn('relatorios', 'entrega_confirmada_em')) {
                /*
                 * Confirmação da entrega. Fica preenchida quando o
                 * estagiário carrega o documento em PDF ou Word.
                 */
                $table->timestamp('entrega_confirmada_em')->nullable()->after('enviado_em');
            }

            if (!Schema::hasColumn('relatorios', 'alerta_atraso_enviado_em')) {
                /*
                 * Aviso de atraso já enviado ao administrador, para
                 * não repetir a notificação todos os dias.
                 */
                $table->timestamp('alerta_atraso_enviado_em')->nullable()->after('entrega_confirmada_em');
            }
        });

        /*
         * Relatórios antigos passam a ter o mês de referência, para
         * que os filtros e a ordenação continuem a funcionar.
         */
        DB::table('relatorios')
            ->whereNull('mes_referencia')
            ->update([
                'mes_referencia' => DB::raw('periodo_referencia'),
                'enviado_em' => DB::raw('submetido_em'),
            ]);

        /*
         * O aviso de atraso passa a ser um valor possível do estado
         * da avaliação, para o administrador o distinguir de um
         * relatório apenas pendente.
         */
        DB::statement(
            "ALTER TABLE relatorios MODIFY estado_avaliacao VARCHAR(20) NOT NULL DEFAULT 'pendente'"
        );

        /*
         * Um relatório passa a ser identificado pelo tipo e pelo mês
         * de referência, porque o período em texto deixou de existir.
         * A chave estrangeira tem de ser recriada para libertar o
         * índice único antigo.
         */
        $indices = collect(DB::select('SHOW INDEX FROM relatorios'))
            ->pluck('Key_name')
            ->unique();

        if ($indices->contains('relatorios_id_estagio_tipo_periodo_referencia_unique')) {
            Schema::table('relatorios', function (Blueprint $table) {
                $table->dropForeign(['id_estagio']);
            });

            Schema::table('relatorios', function (Blueprint $table) {
                $table->dropUnique(['id_estagio', 'tipo', 'periodo_referencia']);
            });
        }

        if (!$indices->contains('relatorios_id_estagio_tipo_mes_referencia_unique')) {
            Schema::table('relatorios', function (Blueprint $table) {
                $table->unique(['id_estagio', 'tipo', 'mes_referencia']);
            });
        }

        $temChaveEstrangeira = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'relatorios'
               AND COLUMN_NAME = 'id_estagio'
               AND REFERENCED_TABLE_NAME = 'estagios'"
        ))->isNotEmpty();

        if (!$temChaveEstrangeira) {
            Schema::table('relatorios', function (Blueprint $table) {
                $table->foreign('id_estagio')
                    ->references('id_estagio')
                    ->on('estagios')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('relatorios', function (Blueprint $table) {
            $table->dropForeign(['id_estagio']);
        });

        Schema::table('relatorios', function (Blueprint $table) {
            $table->dropUnique(['id_estagio', 'tipo', 'mes_referencia']);
            $table->foreign('id_estagio')
                ->references('id_estagio')
                ->on('estagios')
                ->restrictOnDelete();
            $table->unique(['id_estagio', 'tipo', 'periodo_referencia']);

            $table->dropColumn([
                'mes_referencia',
                'prazo_envio',
                'enviado_em',
                'entrega_confirmada_em',
                'alerta_atraso_enviado_em',
            ]);
        });

        DB::statement(
            "ALTER TABLE relatorios MODIFY estado_avaliacao VARCHAR(20) NOT NULL DEFAULT 'pendente'"
        );
    }
};
