<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eliminação lógica: a conta desaparece das consultas normais, mas o
     * registo mantém-se para preservar o histórico de estágios, relatórios
     * e documentos que lhe estão associados.
     */
    public function up(): void
    {
        Schema::table('utilizadores', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('utilizadores', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
