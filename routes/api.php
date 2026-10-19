<?php

use App\Http\Controllers\Admin\UtilizadorAdminController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidaturaController;
use App\Http\Controllers\CoordenadorController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\DocumentoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autenticação
|--------------------------------------------------------------------------
*/

Route::post('/login', [
    AuthController::class,
    'login',
])->middleware('throttle:6,1');

/*
|--------------------------------------------------------------------------
| Rotas públicas
|--------------------------------------------------------------------------
*/

Route::get('/departamentos', [
    DepartamentoController::class,
    'index',
]);

Route::post('/candidaturas', [
    CandidaturaController::class,
    'store',
])->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| Rotas protegidas
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Utilizador autenticado
    |--------------------------------------------------------------------------
    */

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    /*
    |--------------------------------------------------------------------------
    | Candidatura do estagiário
    |--------------------------------------------------------------------------
    */

    Route::get('/estagiario/candidatura', [
        CandidaturaController::class,
        'minhaCandidatura',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Sessão
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [
        AuthController::class,
        'logout',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Documentos
    |--------------------------------------------------------------------------
    */

    Route::get('/documentos/{documento}', [
        DocumentoController::class,
        'download',
    ]);

    Route::post('/documentos/{documento}/substituir', [
        DocumentoController::class,
        'substituir',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Rotas exclusivas do administrador
    |--------------------------------------------------------------------------
    */

    Route::middleware('admin')
        ->prefix('admin')
        ->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Dashboard administrativo
            |--------------------------------------------------------------------------
            */

            Route::get('/dashboard', [
                AdminController::class,
                'dashboard',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Consulta de candidaturas
            |--------------------------------------------------------------------------
            */

            Route::get('/candidaturas', [
                AdminController::class,
                'candidaturas',
            ]);

            Route::get('/candidaturas/{id}', [
                AdminController::class,
                'show',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Encaminhamento de candidaturas
            |--------------------------------------------------------------------------
            |
            | O administrador encaminha a candidatura ao coordenador
            | associado à direção selecionada.
            |
            | O encaminhamento não altera o estado da candidatura.
            |
            */

            Route::patch('/candidaturas/{id}/encaminhar', [
                AdminController::class,
                'encaminhar',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Decisões da candidatura
            |--------------------------------------------------------------------------
            |
            | Estas rotas são mantidas temporariamente para compatibilidade.
            | O AdminController deve impedir que o administrador aprove
            | ou recuse candidaturas.
            |
            | A decisão final pertence ao coordenador responsável.
            |
            */

            Route::patch('/candidaturas/{id}/aprovar', [
                AdminController::class,
                'aprovar',
            ]);

            Route::patch('/candidaturas/{id}/recusar', [
                AdminController::class,
                'recusar',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Gestão de estagiários e estágios
            |--------------------------------------------------------------------------
            */

            Route::get('/estagiarios', [
                AdminController::class,
                'estagiarios',
            ]);

            Route::patch('/estagiarios/{idCandidatura}/estado', [
                AdminController::class,
                'atualizarEstadoEstagio',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Gestão de direções
            |--------------------------------------------------------------------------
            */

            Route::get('/departamentos', [
                DepartamentoController::class,
                'indexAdmin',
            ]);

            Route::post('/departamentos', [
                DepartamentoController::class,
                'store',
            ]);

            Route::patch('/departamentos/{id}', [
                DepartamentoController::class,
                'update',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Gestão de utilizadores
            |--------------------------------------------------------------------------
            */

            Route::get('/utilizadores', [
                UtilizadorAdminController::class,
                'index',
            ]);

            Route::post('/utilizadores', [
                UtilizadorAdminController::class,
                'store',
            ]);

            Route::get('/utilizadores/{id}', [
                UtilizadorAdminController::class,
                'show',
            ]);

            Route::put('/utilizadores/{id}', [
                UtilizadorAdminController::class,
                'update',
            ]);

            Route::patch('/utilizadores/{id}/estado', [
                UtilizadorAdminController::class,
                'alterarEstado',
            ]);

        });

    /*
    |--------------------------------------------------------------------------
    | Rotas exclusivas do coordenador
    |--------------------------------------------------------------------------
    */

    Route::middleware('coordenador')
        ->prefix('coordenador')
        ->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Dashboard do coordenador
            |--------------------------------------------------------------------------
            */

            Route::get('/dashboard', [
                CoordenadorController::class,
                'dashboard',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Candidaturas atribuídas ao coordenador
            |--------------------------------------------------------------------------
            */

            Route::get('/candidaturas', [
                CoordenadorController::class,
                'candidaturas',
            ]);

            Route::get('/candidaturas/{id}', [
                CoordenadorController::class,
                'show',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Decisão final do coordenador
            |--------------------------------------------------------------------------
            */

            Route::patch('/candidaturas/{id}/aprovar', [
                CoordenadorController::class,
                'aprovar',
            ]);

            Route::patch('/candidaturas/{id}/recusar', [
                CoordenadorController::class,
                'recusar',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Gestão de supervisores
            |--------------------------------------------------------------------------
            */

            Route::post('/supervisores', [
                CoordenadorController::class,
                'criarSupervisor',
            ]);

        });

});
