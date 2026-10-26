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
         * O plano pode ser criado em dois fluxos distintos: pelo supervisor
         * (texto sujeito a aprovação do coordenador) ou pelo administrador
         * (documento oficial carregado). A origem impede que um fluxo
         * substitua os planos do outro.
         */
        Schema::table('planos_estagio', function (Blueprint $table) {
            $table->string('origem', 20)
                ->default('supervisor')
                ->after('id_estagio');
        });

        DB::table('planos_estagio')
            ->where(function ($query) {
                $query->whereNotNull('caminho_arquivo')
                    ->orWhereNotNull('id_autor');
            })
            ->update(['origem' => 'administrador']);
    }

    public function down(): void
    {
        Schema::table('planos_estagio', function (Blueprint $table) {
            $table->dropColumn('origem');
        });
    }
};
