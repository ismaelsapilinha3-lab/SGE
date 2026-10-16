
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estagios', function (Blueprint $table) {
            $table->text('motivo_suspensao')->nullable();
        });

        // Permite suspender um estágio sem apagar os estados existentes.
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE estagios MODIFY estado ENUM('ativo', 'suspenso', 'concluido', 'cancelado') NOT NULL DEFAULT 'ativo'"
        );
    }

    public function down(): void
    {
        // Reverte suspensões antes de remover o estado suspenso.
        \Illuminate\Support\Facades\DB::statement(
            "UPDATE estagios SET estado = 'ativo' WHERE estado = 'suspenso'"
        );

        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE estagios MODIFY estado ENUM('ativo', 'concluido', 'cancelado') NOT NULL DEFAULT 'ativo'"
        );

        Schema::table('estagios', function (Blueprint $table) {
            $table->dropColumn('motivo_suspensao');
        });
    }
};
