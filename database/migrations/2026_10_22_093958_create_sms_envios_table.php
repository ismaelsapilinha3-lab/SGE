<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_envios', function (Blueprint $table) {
            $table->id('id_sms_envio');

            $table->foreignId('id_utilizador')->nullable()
                ->constrained('utilizadores', 'id_utilizador')->nullOnDelete();

            $table->foreignId('id_candidatura')->nullable()
                ->constrained('candidaturas', 'id_candidatura')->nullOnDelete();

            // Número de destino, tal como foi enviado ao provedor
            $table->string('telefone', 32);

            $table->text('mensagem');

            // Provedor usado (simulado, http, twilio)
            $table->string('provedor', 32);

            $table->enum('estado', ['pendente', 'enviado', 'falhado'])
                ->default('pendente');

            // Identificador do envio devolvido pelo provedor
            $table->string('referencia', 120)->nullable();

            // Motivo da falha, quando o envio não foi aceite
            $table->text('erro')->nullable();

            // Dados auxiliares (motivo do envio, etc.)
            $table->json('contexto')->nullable();

            $table->timestamp('enviado_em')->nullable();

            $table->timestamps();

            $table->index(['id_candidatura', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_envios');
    }
};
