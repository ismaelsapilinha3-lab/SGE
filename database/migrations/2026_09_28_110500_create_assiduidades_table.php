<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assiduidades', function (Blueprint $table) {
            $table->id('id_assiduidade');
            $table->foreignId('id_estagio')
                ->constrained('estagios', 'id_estagio')->restrictOnDelete();
            $table->foreignId('id_supervisor')->nullable()
                ->constrained('utilizadores', 'id_utilizador')->nullOnDelete(); // quem lançou

            $table->date('data');
            $table->enum('estado', ['presente', 'atraso', 'falta', 'falta_justificada']);
            $table->time('hora_entrada')->nullable();
            $table->time('hora_saida')->nullable();
            $table->text('justificacao')->nullable();
            $table->timestamps();

            $table->unique(['id_estagio', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assiduidades');
    }
};