<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atividades_estagiario', function (Blueprint $table) {
            $table->id('id_atividade');
            $table->foreignId('id_estagio')
                ->constrained('estagios', 'id_estagio')->restrictOnDelete();
            $table->date('data');
            $table->string('titulo');
            $table->text('descricao');
            $table->timestamps();

            $table->index(['id_estagio', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividades_estagiario');
    }
};