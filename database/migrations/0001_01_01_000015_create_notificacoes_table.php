<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id('id_notificacoes');
            $table->foreignId('id_utilizador')
                ->constrained('utilizadores', 'id_utilizador')
                ->onDelete('cascade');
            // CORRIGIDO: erro ortografico "menssagem" -> "mensagem"
            $table->text('mensagem');
            $table->boolean('lida')->default(false);
            // CORRIGIDO: nao havia campo de data; created_at (via timestamps) cobre data de envio
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};