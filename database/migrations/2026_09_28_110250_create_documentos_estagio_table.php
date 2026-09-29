<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_estagio', function (Blueprint $table) {
            $table->id('id_documento_estagio');

            $table->foreignId('id_estagio')
                ->constrained('estagios', 'id_estagio')->restrictOnDelete();

            // Repetido de propósito: consultar por estagiário sem passar por estagios
            $table->foreignId('id_estagiario')
                ->constrained('utilizadores', 'id_utilizador')->restrictOnDelete();

            $table->enum('tipo_documento', ['contrato', 'termo_responsabilidade', 'certificado_conclusao', 'outro'])
                ->default('contrato');

            $table->unsignedSmallInteger('versao')->default(1);
            $table->string('nome_arquivo');
            $table->string('caminho_arquivo');
            $table->dateTime('data_upload');
            $table->date('validade')->nullable();
            $table->enum('estado', ['pendente', 'validado', 'rejeitado'])->default('pendente');
            $table->text('descricao')->nullable();
            $table->timestamps();

            $table->unique(['id_estagio', 'tipo_documento', 'versao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_estagio');
    }
};