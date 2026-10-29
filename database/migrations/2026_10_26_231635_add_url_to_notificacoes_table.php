<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificacoes', function (Blueprint $table) {
            $table->string('url')->nullable();
        });

        /*
         * As notificações já existentes não tinham destino. Para as submissões
         * de plano, reconstrói-se o link a partir do identificador do estágio
         * presente na mensagem.
         */
        DB::table('notificacoes')
            ->whereNull('url')
            ->where('titulo', 'Plano de estágio submetido')
            ->orderBy('id_notificacao')
            ->get(['id_notificacao', 'mensagem'])
            ->each(function (object $notificacao): void {
                if (
                    preg_match(
                        '/est[áa]gio\s*#(\d+)/iu',
                        $notificacao->mensagem,
                        $correspondencia
                    ) !== 1
                ) {
                    return;
                }

                DB::table('notificacoes')
                    ->where('id_notificacao', $notificacao->id_notificacao)
                    ->update([
                        'url' => "/coordenador?estagio={$correspondencia[1]}&aba=plano",
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('notificacoes', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
