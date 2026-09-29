<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis', function (Blueprint $table) {
            $table->id('id_perfil');
            $table->foreignId('id_utilizador')->unique()
                ->constrained('utilizadores', 'id_utilizador')->cascadeOnDelete();

            // Coordenador e supervisor
            $table->foreignId('id_departamento')->nullable()
                ->constrained('departamentos', 'id_departamento')->restrictOnDelete();

            // Estagiário
            $table->string('curso')->nullable();
            $table->string('universidade')->nullable();
            $table->date('nascimento')->nullable();
            $table->enum('sexo', ['Masculino', 'Feminino'])->nullable();
            $table->string('bi', 20)->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfis');
    }
};