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
        | Permite registar no histórico as decisões tomadas sobre os
        | documentos de estágio (contrato), tal como já acontece com o
        | plano de estágio e os documentos de candidatura.
        */

        Schema::table('historico_estados', function (Blueprint $table) {
            $table->foreignId('id_documento_estagio')->nullable()
                ->after('id_plano_estagio')
                ->constrained('documentos_estagio', 'id_documento_estagio')
                ->restrictOnDelete();
        });

        // O CHECK original só contava quatro entidades: actualiza-se.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE historico_estados DROP CHECK chk_historico_uma_entidade');

            DB::statement('ALTER TABLE historico_estados ADD CONSTRAINT chk_historico_uma_entidade CHECK (
                (id_candidatura IS NOT NULL) + (id_estagio IS NOT NULL)
                + (id_documento IS NOT NULL) + (id_plano_estagio IS NOT NULL)
                + (id_documento_estagio IS NOT NULL) = 1
            )');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE historico_estados DROP CHECK chk_historico_uma_entidade');

            DB::statement('ALTER TABLE historico_estados ADD CONSTRAINT chk_historico_uma_entidade CHECK (
                (id_candidatura IS NOT NULL) + (id_estagio IS NOT NULL)
                + (id_documento IS NOT NULL) + (id_plano_estagio IS NOT NULL) = 1
            )');
        }

        Schema::table('historico_estados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_documento_estagio');
        });
    }
};
