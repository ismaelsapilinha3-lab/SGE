<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Duração prevista do estágio indicada na candidatura.
     *
     * Fica na candidatura para o administrador a poder corrigir antes de
     * o estágio ser iniciado. Ao iniciar o estágio, este valor serve de
     * duração predefinida.
     */
    public function up(): void
    {
        Schema::table('candidaturas', function (Blueprint $table) {
            $table->unsignedSmallInteger('duracao_meses')
                ->nullable()
                ->after('id_grupo_estagio');
        });
    }

    public function down(): void
    {
        Schema::table('candidaturas', function (Blueprint $table) {
            $table->dropColumn('duracao_meses');
        });
    }
};
