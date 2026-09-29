<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estagio_supervisores', function (Blueprint $table) {
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
                ->storedAs("CASE WHEN removido_em IS NULL THEN CONCAT(id_estagio, '-', id_supervisor) ELSE NULL END")
                ->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estagio_supervisores');
    }
};