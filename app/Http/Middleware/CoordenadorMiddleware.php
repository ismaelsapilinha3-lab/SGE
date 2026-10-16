<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CoordenadorMiddleware
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $user = $request->user();

        // Confirmar se existe um utilizador autenticado e ativo.
        if (!$user || !$user->ativo) {
            return response()->json([
                'message' => 'Utilizador não autorizado.',
            ], 403);
        }

        // Permitir apenas utilizadores com o perfil de coordenador.
        if ($user->tipo_utilizador !== 'coordenador') {
            return response()->json([
                'message' => 'Acesso restrito ao coordenador.',
            ], 403);
        }

        // Confirmar se o coordenador está associado a uma direção.
        if (!$user->id_departamento) {
            return response()->json([
                'message' => 'O coordenador não está associado a uma direção.',
            ], 403);
        }

        return $next($request);
    }
}

