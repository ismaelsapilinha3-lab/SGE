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
                ->constrained('estagios', 'id_estagio')
                ->onDelete('cascade');
            $table->string('titulo');
            // CORRIGIDO: tipo_relatorio passa de string livre para enum controlado
            $table->enum('tipo_relatorio', ['parcial', 'final'])->default('parcial');
            $table->string('caminho_arquivo');
            $table->date('data_entrega');
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatorios');
    }
};