<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estagios', function (Blueprint $table) {
            $table->unsignedInteger('duracao_meses')
                ->default(1)
                ->after('data_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('estagios', function (Blueprint $table) {
            $table->dropColumn('duracao_meses');
        });
    }
};