<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A remoção de assiduidade e avaliações passa a ser lógica, para
         * preservar o histórico sem impedir o supervisor de voltar a
         * registar o mesmo dia ou período.
         */
        Schema::table('assiduidades', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('avaliacoes', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('assiduidades', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('avaliacoes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
