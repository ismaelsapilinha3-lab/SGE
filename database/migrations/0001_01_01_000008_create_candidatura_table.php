<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidaturas', function (Blueprint $table) {
            $table->id('id_candidatura');
            $table->string('nome');
            $table->string('email');
            $table->string('curso');
            $table->string('cv_arquivo');
            // CORRIGIDO: estado passa de string livre para enum controlado
            $table->enum('estado', ['pendente', 'aprovada', 'rejeitada'])->default('pendente');
            $table->foreignId('id_departamento_selecionado')
                ->constrained('departamentos', 'id_departamento')
                ->onDelete('restrict');
            $table->foreignId('id_administrador')
                ->nullable()
                ->constrained('administradores', 'id_administrador')
                ->onDelete('set null');
            // CORRIGIDO: falta rastrear qual coordenador decidiu a candidatura e quando
            $table->foreignId('id_coordenador')
                ->nullable()
                ->constrained('coordenadores', 'id_coordenador')
                ->onDelete('set null');
            $table->timestamp('data_decisao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidaturas');
    }
};