<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * O plano de estágio passa a ser o documento oficial
         * (PDF ou Word) carregado pelo administrador. Deixa de
         * depender do supervisor e do texto estruturado.
         */
        Schema::table('planos_estagio', function (Blueprint $table) {
            $table->foreignId('id_supervisor')->nullable()->change();

            $table->text('objetivos')->nullable()->change();
            $table->text('atividades_previstas')->nullable()->change();
            $table->text('cronograma')->nullable()->change();

            $table->string('nome_arquivo')->nullable()->after('id_supervisor');
            $table->string('caminho_arquivo')->nullable()->after('nome_arquivo');
            $table->string('formato', 10)->nullable()->after('caminho_arquivo');
            $table->unsignedInteger('tamanho')->nullable()->after('formato');

            // Quem carregou o documento (administrador).
            $table->foreignId('id_autor')->nullable()->after('tamanho')
                ->constrained('utilizadores', 'id_utilizador')->nullOnDelete();

            $table->unsignedSmallInteger('versao')->default(1)->after('id_autor');
            $table->timestamp('data_upload')->nullable()->after('versao');
        });
    }

    public function down(): void
    {
        Schema::table('planos_estagio', function (Blueprint $table) {
            $table->dropForeign(['id_autor']);
            $table->dropColumn([
                'nome_arquivo',
                'caminho_arquivo',
                'formato',
                'tamanho',
                'id_autor',
                'versao',
                'data_upload',
            ]);
        });
    }
};
