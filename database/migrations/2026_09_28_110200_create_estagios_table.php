<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estagios', function (Blueprint $table) {
            $table->id('id_estagio');
            $table->foreignId('id_candidatura')->unique()
                ->constrained('candidaturas', 'id_candidatura')->restrictOnDelete();
            $table->foreignId('id_estagiario')
                ->constrained('utilizadores', 'id_utilizador')->restrictOnDelete();

            // Registadas pelo administrador (a duração é definida por ele)
            $table->date('data_inicio');
            $table->date('data_fim_prevista');
            $table->date('data_fim_real')->nullable();

            $table->enum('estado', ['ativo', 'concluido', 'cancelado'])->default('ativo');

            // Regra: um estagiário só tem um estágio ativo de cada vez
            $table->unsignedBigInteger('estagiario_ativo')->nullable()
                ->storedAs("CASE WHEN estado = 'ativo' THEN id_estagiario ELSE NULL END")
                ->unique();

            $table->timestamps();
        });

        DB::statement('ALTER TABLE estagios ADD CONSTRAINT chk_estagios_datas CHECK (data_fim_prevista > data_inicio)');
    }

    public function down(): void
    {
        Schema::dropIfExists('estagios');
    }
};