<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relatorios', function (Blueprint $table) {
            $table->id('id_relatorio');
            $table->foreignId('id_estagio')
                ->constrained('estagios', 'id_estagio')->restrictOnDelete();

            $table->enum('tipo', ['semanal', 'mensal', 'final']);
            $table->date('periodo_referencia'); // mesma convenção das avaliações

            $table->text('atividades');
            $table->text('resultados');
            $table->text('constrangimentos')->nullable();
            $table->text('objetivos')->nullable();
            $table->timestamp('submetido_em')->nullable();
            $table->timestamps();

            $table->unique(['id_estagio', 'tipo', 'periodo_referencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatorios');
    }
};