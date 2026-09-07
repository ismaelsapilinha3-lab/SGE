<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id('id_documento');
            $table->foreignId('id_estagio')
                ->constrained('estagios', 'id_estagio')
                ->onDelete('cascade');
            // CORRIGIDO: tipo_documento passa de string livre para enum controlado
            $table->enum('tipo_documento', ['cv', 'carta_apresentacao', 'certificado', 'contrato', 'outro'])->default('outro');
            $table->string('nome_arquivo');
            $table->string('caminho_arquivo');
            // CORRIGIDO: data_upload passa de date para datetime (momento exato do upload)
            $table->dateTime('data_upload');
            $table->text('descricao')->nullable();
            // CORRIGIDO: estado passa de string livre para enum controlado
            $table->enum('estado', ['pendente', 'validado', 'rejeitado'])->default('pendente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};