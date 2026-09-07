<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades_estagiario', function (Blueprint $table) {
            $table->id('id_actividade');
            $table->foreignId('id_estagio')
                ->constrained('estagios', 'id_estagio')
                ->onDelete('cascade');
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->date('data_actividade');
            $table->text('resultado')->nullable();
            $table->foreignId('id_supervisor')
                ->constrained('supervisores', 'id_supervisor')
                ->onDelete('restrict');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades_estagiario');
    }
};