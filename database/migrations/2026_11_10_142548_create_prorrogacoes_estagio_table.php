<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histórico das prorrogações de estágio.
     *
     * Cada registo guarda o período anterior e o novo, os meses
     * acrescentados, o motivo apresentado e quem autorizou.
     */
    public function up(): void
    {
        Schema::create('prorrogacoes_estagio', function (Blueprint $table) {
            $table->id('id_prorrogacao');

            $table->foreignId('id_estagio')
                ->constrained('estagios', 'id_estagio')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('meses');

            $table->unsignedSmallInteger('duracao_anterior')->nullable();
            $table->unsignedSmallInteger('duracao_nova');

            $table->date('data_fim_anterior')->nullable();
            $table->date('data_fim_nova');

            $table->text('motivo');

            $table->foreignId('id_utilizador')
                ->nullable()
                ->constrained('utilizadores', 'id_utilizador')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('id_estagio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prorrogacoes_estagio');
    }
};
