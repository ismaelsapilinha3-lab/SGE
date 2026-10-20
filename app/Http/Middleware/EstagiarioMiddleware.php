<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstagiarioMiddleware
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $user = $request->user();

        if (! $user || ! $user->ativo) {
            return response()->json([
                'message' => 'Utilizador não autorizado.',
            ], 403);
        }

        if ($user->tipo_utilizador !== 'estagiario') {
            return response()->json([
                'message' => 'Acesso restrito ao estagiário.',
            ], 403);
        }

        return $next($request);
    }
}
