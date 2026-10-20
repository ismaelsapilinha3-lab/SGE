<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite não tem CONCAT(); usa-se o operador || com cast explícito.
        $vinculoAtivo = DB::getDriverName() === 'sqlite'
            ? "CASE WHEN removido_em IS NULL THEN CAST(id_estagio AS TEXT) || '-' || CAST(id_supervisor AS TEXT) ELSE NULL END"
            : "CASE WHEN removido_em IS NULL THEN CONCAT(id_estagio, '-', id_supervisor) ELSE NULL END";

        Schema::create('estagio_supervisores', function (Blueprint $table) use ($vinculoAtivo) {
            $table->id('id_estagio_supervisor');
            $table->foreignId('id_estagio')
                ->constrained('estagios', 'id_estagio')->restrictOnDelete();
            $table->foreignId('id_supervisor')
                ->constrained('utilizadores', 'id_utilizador')->restrictOnDelete();
            $table->foreignId('id_coordenador')->nullable()
                ->constrained('utilizadores', 'id_utilizador')->nullOnDelete(); // quem atribuiu

            $table->dateTime('atribuido_em');
            $table->dateTime('removido_em')->nullable();

            // Regra: o mesmo supervisor não pode estar ativo duas vezes no mesmo estágio
            $table->string('vinculo_ativo', 50)->nullable()
                ->storedAs($vinculoAtivo)
                ->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estagio_supervisores');
    }
};
