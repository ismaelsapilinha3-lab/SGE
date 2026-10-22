<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        | O contrato é o único documento de estágio carregado como ficheiro.
        | Os relatórios de estágio têm tabela própria (relatorios), pelo que
        | os restantes tipos deixam de fazer sentido.
        |
        | Antes de restringir o enum, os valores removidos passam a "outro".
        */

        DB::table('documentos_estagio')
            ->whereIn('tipo_documento', [
                'termo_responsabilidade',
                'certificado_conclusao',
            ])
            ->update(['tipo_documento' => 'outro']);

        // Usa-se ->change() em vez de SQL MySQL para funcionar também em SQLite.
        Schema::table('documentos_estagio', function (Blueprint $table) {
            $table->enum('tipo_documento', ['contrato', 'outro'])
                ->default('contrato')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('documentos_estagio', function (Blueprint $table) {
            $table->enum('tipo_documento', [
                'contrato',
                'termo_responsabilidade',
                'certificado_conclusao',
                'outro',
            ])->default('contrato')->change();
        });
    }
};
