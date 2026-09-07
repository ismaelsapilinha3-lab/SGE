<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estagios', function (Blueprint $table) {
            $table->id('id_estagio');
            $table->foreignId('id_estagiario')
                ->constrained('estagiarios', 'id_estagiario')
                ->onDelete('cascade');
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            // CORRIGIDO: estado passa de string livre para enum controlado
            $table->enum('estado', ['em_curso', 'concluido', 'cancelado'])->default('em_curso');
            $table->foreignId('id_departamento')
                ->constrained('departamentos', 'id_departamento')
                ->onDelete('restrict');
            $table->foreignId('id_supervisor')
                ->constrained('supervisores', 'id_supervisor')
                ->onDelete('restrict');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estagios');
    }
};