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
        |------------------------------------------------------------------
        | Chave estrangeira: a candidatura passa a pertencer a uma conta
        | por id_utilizador em vez de por texto de e-mail.
        |------------------------------------------------------------------
        |
        | Antes, alterar o e-mail de uma conta movia todos os documentos e
        | a candidatura para outra conta sem qualquer rasto.
        |
        | nullOnDelete: apagar a conta deixa a candidatura órfã de conta
        | (o e-mail de contacto continua guardado na própria candidatura).
        |
        */

        Schema::table('candidaturas', function (Blueprint $table) {
            $table->foreignId('id_utilizador')
                ->nullable()
                ->after('email')
                ->constrained('utilizadores', 'id_utilizador')
                ->nullOnDelete();
        });

        /*
        |------------------------------------------------------------------
        | Backfill: ligar cada candidatura à conta existente cujo e-mail
        | coincide. Candidaturas sem conta correspondente ficam a NULL.
        |------------------------------------------------------------------
        */

        DB::table('candidaturas')
            ->whereNull('id_utilizador')
            ->update([
                'id_utilizador' => DB::raw(
                    '(SELECT id_utilizador FROM utilizadores WHERE utilizadores.email = candidaturas.email)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('candidaturas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_utilizador');
        });
    }
};
