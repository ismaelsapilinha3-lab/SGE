<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_estados', function (Blueprint $table) {
            $table->id('id_historico');

            // Sem polimorfismo: uma FK real por entidade, e só uma preenchida
            $table->foreignId('id_candidatura')->nullable()
                ->constrained('candidaturas', 'id_candidatura')->restrictOnDelete();
            $table->foreignId('id_estagio')->nullable()
                ->constrained('estagios', 'id_estagio')->restrictOnDelete();
            $table->foreignId('id_documento')->nullable()
                ->constrained('documentos', 'id_documento')->restrictOnDelete();
            $table->foreignId('id_plano_estagio')->nullable()
                ->constrained('planos_estagio', 'id_plano_estagio')->restrictOnDelete();

            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_novo', 30);
            $table->text('motivo')->nullable();

            // Nulo quando a ação vem do portal público (sem conta)
            $table->foreignId('id_utilizador')->nullable()
                ->constrained('utilizadores', 'id_utilizador')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent(); // registo imutável
        });

        DB::statement('ALTER TABLE historico_estados ADD CONSTRAINT chk_historico_uma_entidade CHECK (
            (id_candidatura IS NOT NULL) + (id_estagio IS NOT NULL)
            + (id_documento IS NOT NULL) + (id_plano_estagio IS NOT NULL) = 1
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_estados');
    }
};