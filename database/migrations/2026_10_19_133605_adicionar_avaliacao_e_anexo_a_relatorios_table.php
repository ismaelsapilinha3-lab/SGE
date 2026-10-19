<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relatorios', function (Blueprint $table) {
            $table->string('estado_avaliacao', 20)
                ->default('pendente');

            $table->text('comentario_admin')->nullable();

            $table->string('ficheiro_path')->nullable();

            $table->string('ficheiro_nome')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('relatorios', function (Blueprint $table) {
            $table->dropColumn([
                'estado_avaliacao',
                'comentario_admin',
                'ficheiro_path',
                'ficheiro_nome',
            ]);
        });
    }
};