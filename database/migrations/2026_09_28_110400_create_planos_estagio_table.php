<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos_estagio', function (Blueprint $table) {
            $table->id('id_plano_estagio');
            $table->foreignId('id_estagio')
                ->constrained('estagios', 'id_estagio')->restrictOnDelete();
            $table->foreignId('id_supervisor')
                ->constrained('utilizadores', 'id_utilizador')->restrictOnDelete(); // autor

            $table->text('objetivos');
            $table->text('atividades_previstas');
            $table->text('cronograma');
            $table->date('data_inicio');
            $table->date('data_fim');

            // 'substituido' = plano antigo, quando é criado um novo durante o estágio
            $table->enum('estado', ['submetido', 'aprovado', 'recusado', 'substituido'])->default('submetido');
            $table->text('motivo_recusa')->nullable();
            $table->foreignId('id_coordenador')->nullable()
                ->constrained('utilizadores', 'id_utilizador')->nullOnDelete();
            $table->timestamp('data_decisao')->nullable();

            $table->timestamps();
        });

        // Cópia do conteúdo anterior sempre que o plano é editado e reenviado
        Schema::create('planos_estagio_historico', function (Blueprint $table) {
            $table->id('id_plano_historico');
            $table->foreignId('id_plano_estagio')
                ->constrained('planos_estagio', 'id_plano_estagio')->cascadeOnDelete();
            $table->text('objetivos');
            $table->text('atividades_previstas');
            $table->text('cronograma');
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->string('estado', 30);
            $table->text('motivo_recusa')->nullable();
            $table->foreignId('alterado_por')->nullable()
                ->constrained('utilizadores', 'id_utilizador')->nullOnDelete();
            $table->timestamp('alterado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planos_estagio_historico');
        Schema::dropIfExists('planos_estagio');
    }
};