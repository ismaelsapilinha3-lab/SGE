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
            $table->foreignId('id_estagio')
                ->constrained('estagios', 'id_estagio')->restrictOnDelete();
            $table->foreignId('id_supervisor')
                ->constrained('utilizadores', 'id_utilizador')->restrictOnDelete(); // autor

            $table->enum('tipo', ['semanal', 'mensal', 'final']);
            // semanal = segunda-feira da semana; mensal = dia 1 do mês; final = data de encerramento
            $table->date('periodo_referencia');

            $table->decimal('nota_atividades', 4, 2)->nullable();
            $table->decimal('nota_assiduidade', 4, 2)->nullable();
            $table->decimal('nota_pontualidade', 4, 2)->nullable();
            $table->decimal('nota', 4, 2); // nota global
            $table->text('comentarios')->nullable();

            // Só nas avaliações mensal e final
            $table->text('pontos_fortes')->nullable();
            $table->text('areas_melhoria')->nullable();

            $table->timestamps();

            $table->unique(['id_estagio', 'tipo', 'periodo_referencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacoes');
    }
};