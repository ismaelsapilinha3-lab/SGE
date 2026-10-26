<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estagios', function (Blueprint $table) {
            $table->index(
                ['estado', 'data_fim_prevista'],
                'estagios_estado_data_fim_prevista_index'
            );
        });

        Schema::table('relatorios', function (Blueprint $table) {
            $table->index(
                ['prazo_envio', 'enviado_em', 'entrega_confirmada_em'],
                'relatorios_prazo_entrega_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('relatorios', function (Blueprint $table) {
            $table->dropIndex('relatorios_prazo_entrega_index');
        });

        Schema::table('estagios', function (Blueprint $table) {
            $table->dropIndex('estagios_estado_data_fim_prevista_index');
        });
    }
};
