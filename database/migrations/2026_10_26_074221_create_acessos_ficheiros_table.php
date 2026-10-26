<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acessos_ficheiros', function (Blueprint $table) {
            $table->id('id_acesso');

            $table->foreignId('id_utilizador')
                ->constrained('utilizadores', 'id_utilizador')
                ->restrictOnDelete();

            $table->foreignId('id_documento')->nullable()
                ->constrained('documentos', 'id_documento')
                ->restrictOnDelete();

            $table->foreignId('id_documento_estagio')->nullable()
                ->constrained('documentos_estagio', 'id_documento_estagio')
                ->restrictOnDelete();

            $table->foreignId('id_relatorio')->nullable()
                ->constrained('relatorios', 'id_relatorio')
                ->restrictOnDelete();

            $table->foreignId('id_plano_estagio')->nullable()
                ->constrained('planos_estagio', 'id_plano_estagio')
                ->restrictOnDelete();

            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['id_utilizador', 'created_at'],
                'acessos_ficheiros_utilizador_data_index'
            );
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE acessos_ficheiros
                ADD CONSTRAINT chk_acesso_um_ficheiro CHECK (
                    (id_documento IS NOT NULL)
                    + (id_documento_estagio IS NOT NULL)
                    + (id_relatorio IS NOT NULL)
                    + (id_plano_estagio IS NOT NULL) = 1
                )');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('acessos_ficheiros');
    }
};
