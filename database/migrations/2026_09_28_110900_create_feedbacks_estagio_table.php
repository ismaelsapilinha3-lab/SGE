<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedbacks_estagio', function (Blueprint $table) {
            $table->id('id_feedback');
            $table->foreignId('id_estagio')->unique()
                ->constrained('estagios', 'id_estagio')->restrictOnDelete();
            $table->unsignedTinyInteger('nota_geral'); // 1 a 5
            $table->text('comentario')->nullable();
            $table->text('sugestoes')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE feedbacks_estagio ADD CONSTRAINT chk_feedback_nota CHECK (nota_geral BETWEEN 0 AND 10)');
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks_estagio');
    }
};