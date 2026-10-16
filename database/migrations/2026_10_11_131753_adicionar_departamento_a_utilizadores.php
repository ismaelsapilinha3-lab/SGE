
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utilizadores', function (Blueprint $table) {
            $table->foreignId('id_departamento')
                ->nullable()
                ->after('tipo_utilizador')
                ->constrained(
                    'departamentos',
                    'id_departamento'
                )
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('utilizadores', function (Blueprint $table) {
            $table->dropForeign(
                ['id_departamento']
            );

            $table->dropColumn('id_departamento');
        });
    }
};

