<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilizadores', function (Blueprint $table) {
            $table->id('id_utilizador');
            $table->string('nome');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // CORRIGIDO: foto guardada como caminho de ficheiro (string), nao como BLOB na base de dados
            $table->string('foto_path')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilizadores');
    }
};