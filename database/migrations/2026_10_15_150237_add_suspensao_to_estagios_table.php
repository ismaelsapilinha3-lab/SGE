<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estagios', function (Blueprint $table) {
            $table->text('motivo_suspensao')->nullable();
        });

        // Permite suspender um estágio sem apagar os estados existentes.
        // Usa-se ->change() em vez de SQL MySQL para funcionar também em SQLite.
        Schema::table('estagios', function (Blueprint $table) {
            $table->enum('estado', ['ativo', 'suspenso', 'concluido', 'cancelado'])
                ->default('ativo')
                ->change();
        });
    }

    public function down(): void
    {
        // Reverte suspensões antes de remover o estado suspenso.
        DB::table('estagios')
            ->where('estado', 'suspenso')
            ->update(['estado' => 'ativo']);

        Schema::table('estagios', function (Blueprint $table) {
            $table->enum('estado', ['ativo', 'concluido', 'cancelado'])
                ->default('ativo')
                ->change();
        });

        Schema::table('estagios', function (Blueprint $table) {
            $table->dropColumn('motivo_suspensao');
        });
    }
};
