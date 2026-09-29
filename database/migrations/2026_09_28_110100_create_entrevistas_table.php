<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrevistas', function (Blueprint $table) {
            $table->id('id_entrevista');
            $table->foreignId('id_candidatura')->unique()
                ->constrained('candidaturas', 'id_candidatura')->restrictOnDelete();
            $table->foreignId('id_administrador')
                ->constrained('utilizadores', 'id_utilizador')->restrictOnDelete();
            // Representante técnico, não necessariamente o supervisor definitivo
            $table->foreignId('id_supervisor_representante')
                ->constrained('utilizadores', 'id_utilizador')->restrictOnDelete();

            $table->dateTime('data_hora');
            $table->enum('resultado', ['pendente', 'aprovado', 'reprovado'])->default('pendente');
            $table->text('motivo_reprovacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrevistas');
    }
};