<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * O estagiário ativo pode enviar o relatório em PDF ou Word.
         * Este tipo de documento passa a existir na tabela de documentos.
         *
         * Em SQLite a coluna é um CHECK com os valores originais e nada
         * grava 'relatorio_estagio' nessa tabela (o ficheiro fica em
         * relatorios.ficheiro_path), por isso o MODIFY só é preciso no MySQL.
         */
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE documentos MODIFY tipo_documento ENUM(
                'bi',
                'cv',
                'declaracao_escolar',
                'carta_solicitacao',
                'foto',
                'relatorio_estagio'
            ) NOT NULL"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('documentos')
            ->where('tipo_documento', 'relatorio_estagio')
            ->delete();

        DB::statement(
            "ALTER TABLE documentos MODIFY tipo_documento ENUM(
                'bi',
                'cv',
                'declaracao_escolar',
                'carta_solicitacao',
                'foto'
            ) NOT NULL"
        );
    }
};
