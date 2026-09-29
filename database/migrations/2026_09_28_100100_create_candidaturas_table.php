<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidaturas', function (Blueprint $table) {
            $table->id('id_candidatura');

            // Dados pessoais do candidato
            $table->string('nome');
            $table->string('email');
            $table->string('curso');
            $table->string('universidade');
            $table->date('nascimento');
            $table->enum('sexo', ['Masculino', 'Feminino']);
            $table->string('bi', 20);
            $table->string('contacto', 20);

            $table->foreignId('id_departamento_selecionado')
                ->constrained('departamentos', 'id_departamento')
                ->restrictOnDelete();

            // Estado e decisão
            $table->enum('estado', ['em_analise', 'aprovada', 'recusada', 'encerrada'])
                ->default('em_analise');
            $table->text('motivo_recusa')->nullable();
            $table->timestamp('data_decisao')->nullable();
            
                       // Coordenador que decidiu a candidatura
            $table->foreignId('id_coordenador')
                ->nullable()
                ->constrained('utilizadores', 'id_utilizador')
                ->nullOnDelete();

            $table->timestamp('encaminhada_em')->nullable(); // quando o administrador a enviou ao coordenador

            // Regra: só uma candidatura ativa por estudante (mesmo BI).
            // Fica preenchida só enquanto a candidatura está ativa; o índice
            // único ignora NULL, por isso candidaturas antigas não bloqueiam.
            $table->string('bi_ativo', 20)->nullable()
                ->storedAs("CASE WHEN estado IN ('em_analise','aprovada') THEN bi ELSE NULL END")
                ->unique();

            $table->timestamps();

            $table->index('estado');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidaturas');
    }

    public function departamento()
    {
    return $this->belongsTo(Departamento::class, 'id_departamento_selecionado', 'id_departamento');
    }
};