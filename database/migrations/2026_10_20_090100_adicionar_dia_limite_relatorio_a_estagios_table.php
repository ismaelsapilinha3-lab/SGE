<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estagios', function (Blueprint $table) {
            /*
             * Dia do mês até ao qual o estagiário deve entregar o
             * relatório do mês anterior. Definido pelo administrador.
             */
            $table->unsignedTinyInteger('dia_limite_relatorio')
                ->default(5)
                ->after('data_fim_prevista');
        });
    }

    public function down(): void
    {
        Schema::table('estagios', function (Blueprint $table) {
            $table->dropColumn('dia_limite_relatorio');
        });
    }
};
