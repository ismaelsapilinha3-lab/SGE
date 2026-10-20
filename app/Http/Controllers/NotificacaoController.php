<?php

namespace App\Http\Controllers;

use App\Models\Notificacao;
use Illuminate\Http\Request;

class NotificacaoController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Listar as notificações do utilizador autenticado
    |--------------------------------------------------------------------------
    |
    | A listagem é sempre limitada ao próprio utilizador; as notificações
    | ligadas apenas a uma candidatura (candidatos sem conta) não aparecem
    | aqui.
    |
    */

    public function index(Request $request)
    {
        $dados = $request->validate([
            'nao_lidas' => [
                'nullable',
                'boolean',
            ],
        ]);

        $query = $request->user()
            ->notificacoes()
            ->latest('id_notificacao');

        if (! empty($dados['nao_lidas'])) {
            $query->naoLidas();
        }

        return response()->json([
            'notificacoes' => $query->limit(50)->get(),

            'resumo' => [
                'nao_lidas' => $request->user()
                    ->notificacoes()
                    ->naoLidas()
                    ->count(),

                'total' => $request->user()
                    ->notificacoes()
                    ->count(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Marcar uma notificação como lida
    |--------------------------------------------------------------------------
    */

    public function marcarComoLida(
        Request $request,
        Notificacao $notificacao
    ) {

        $this->garantirDestinatario($request, $notificacao);

        $notificacao->marcarComoLida();

        return response()->json([
            'message' => 'Notificação marcada como lida.',

            'notificacao' => $notificacao->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Marcar todas as notificações como lidas
    |--------------------------------------------------------------------------
    */

    public function marcarTodasComoLidas(Request $request)
    {
        $atualizadas = $request->user()
            ->notificacoes()
            ->naoLidas()
            ->update(['lida_em' => now()]);

        return response()->json([
            'message' => 'Notificações marcadas como lidas.',

            'atualizadas' => $atualizadas,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Remover uma notificação
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        Notificacao $notificacao
    ) {

        $this->garantirDestinatario($request, $notificacao);

        $notificacao->delete();

        return response()->json([
            'message' => 'Notificação removida.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Garantir que a notificação pertence ao utilizador autenticado
    |--------------------------------------------------------------------------
    */

    private function garantirDestinatario(
        Request $request,
        Notificacao $notificacao
    ): void {
        if (
            (int) $notificacao->id_utilizador
            !== (int) $request->user()->id_utilizador
        ) {
            abort(response()->json([
                'message' => 'Notificação não encontrada.',
            ], 404));
        }
    }
}
