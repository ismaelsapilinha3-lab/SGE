<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id('id_notificacao');

            // Destinatário: utilizador com conta, ou candidato ainda sem conta
            $table->foreignId('id_utilizador')->nullable()
                ->constrained('utilizadores', 'id_utilizador')->restrictOnDelete();
            $table->foreignId('id_candidatura')->nullable()
                ->constrained('candidaturas', 'id_candidatura')->restrictOnDelete();

            $table->enum('canal', ['sistema', 'email'])->default('sistema');
            $table->string('titulo');
            $table->text('mensagem');
            $table->timestamp('enviada_em')->nullable();
            $table->timestamp('lida_em')->nullable();
            $table->timestamps();

            $table->index(['id_utilizador', 'lida_em']);
        });

        DB::statement('ALTER TABLE notificacoes ADD CONSTRAINT chk_notificacao_destinatario
            CHECK (id_utilizador IS NOT NULL OR id_candidatura IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};