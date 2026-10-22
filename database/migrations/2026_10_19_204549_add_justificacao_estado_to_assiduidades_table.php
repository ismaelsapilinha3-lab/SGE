<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        | Fluxo de justificação de faltas: o estagiário submete uma
        | justificação sobre um dia marcado como falta e o supervisor
        | aceita ou recusa. O estado da justificação é independente do
        | estado de assiduidade, que só passa a `falta_justificada`
        | quando a justificação é aprovada.
        */

        Schema::table('assiduidades', function (Blueprint $table) {
            $table->enum('justificacao_estado', [
                'pendente',
                'aprovada',
                'recusada',
            ])->nullable();

            $table->text('motivo_recusa')->nullable();

            $table->foreignId('id_supervisor_justificacao')->nullable()
                ->constrained('utilizadores', 'id_utilizador')
                ->nullOnDelete();

            $table->timestamp('justificacao_submetida_em')->nullable();
            $table->timestamp('justificacao_decidida_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assiduidades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_supervisor_justificacao');

            $table->dropColumn([
                'justificacao_estado',
                'motivo_recusa',
                'justificacao_submetida_em',
                'justificacao_decidida_em',
            ]);
        });
    }
};
