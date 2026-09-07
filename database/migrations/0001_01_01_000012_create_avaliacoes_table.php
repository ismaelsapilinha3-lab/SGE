<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacoes', function (Blueprint $table) {
            $table->id('id_avaliacao');
            $table->foreignId('id_supervisor')
                ->constrained('supervisores', 'id_supervisor')
                ->onDelete('restrict');
            // CORRIGIDO: nota passa de float para decimal(4,2), evita erros de arredondamento binario
            $table->decimal('nota', 4, 2);
            $table->text('observacao')->nullable();
            $table->date('data_avaliacao');
            $table->foreignId('id_actividade')
                ->constrained('actividades_estagiario', 'id_actividade')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacoes');
    }
};