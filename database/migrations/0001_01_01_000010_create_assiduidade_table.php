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
                ->constrained('estagios', 'id_estagio')
                ->onDelete('cascade');
            $table->date('data');
            // CORRIGIDO: entrada/saida passam de string livre para time
            $table->time('entrada')->nullable();
            $table->time('saida')->nullable();
            // CORRIGIDO: estado passa de string livre para enum controlado
            $table->enum('estado', ['presente', 'falta', 'falta_justificada'])->default('presente');
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->unique(['id_estagio', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assiduidades');
    }
};