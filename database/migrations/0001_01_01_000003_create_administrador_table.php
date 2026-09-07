<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administradores', function (Blueprint $table) {
            $table->id('id_administrador');
            $table->foreignId('id_utilizador')
                ->unique()
                ->constrained('utilizadores', 'id_utilizador')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administradores');
    }
};