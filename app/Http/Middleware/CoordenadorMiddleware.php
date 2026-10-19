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

        if (! $user || ! $user->ativo) {
            return response()->json([
                'message' => 'Utilizador não autorizado.',
            ], 403);
        }

        if ($user->tipo_utilizador !== 'coordenador') {
            return response()->json([
                'message' => 'Acesso restrito ao coordenador.',
            ], 403);
        }

        // A direção está guardada no perfil do coordenador.
        $idDepartamento = $user->perfil?->id_departamento;

        if (! $idDepartamento) {
            return response()->json([
                'message' => 'O coordenador não está associado a uma direção.',
            ], 403);
        }

        return $next($request);
    }
}
