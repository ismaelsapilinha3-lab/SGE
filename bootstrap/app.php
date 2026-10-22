<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CoordenadorMiddleware;
use App\Http\Middleware\EstagiarioMiddleware;
use App\Http\Middleware\SupervisorMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        | API não tem página de login: pedidos sem sessão devolvem
        | 401 JSON em vez de redireccionar para uma rota inexistente.
        */
        $middleware->redirectGuestsTo(function (Request $request) {
            return $request->is('api/*') ? null : '/acesso';
        });

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'coordenador' => CoordenadorMiddleware::class,
            'supervisor' => SupervisorMiddleware::class,
            'estagiario' => EstagiarioMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->create();
