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

            // restrict: apagar uma candidatura obriga a tratar primeiro os
            // documentos (e os ficheiros no disco), sem deixar órfãos.
            $table->foreignId('id_candidatura')
                ->constrained('candidaturas', 'id_candidatura')
                ->restrictOnDelete();

            $table->enum('tipo_documento', [
                'bi', 'cv', 'declaracao_escolar', 'carta_solicitacao', 'foto',
            ]);

            $table->unsignedSmallInteger('versao')->default(1);
            $table->string('nome_arquivo');
            $table->string('caminho_arquivo');
            $table->dateTime('data_upload');
            $table->date('validade')->nullable();
            $table->enum('estado', ['pendente', 'validado', 'rejeitado'])->default('pendente');
            $table->text('descricao')->nullable();
            $table->timestamps();

            // Um reenvio do mesmo tipo cria a versão seguinte, nunca duplicado
            $table->unique(['id_candidatura', 'tipo_documento', 'versao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};