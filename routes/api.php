<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidaturaController;
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
    'login'
]);

/*
|--------------------------------------------------------------------------
| Rotas públicas
|--------------------------------------------------------------------------
*/

Route::get('/departamentos', [
    DepartamentoController::class,
    'index'
]);

Route::post('/candidaturas', [
    CandidaturaController::class,
    'store'
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
    | Candidatura do estagiário autenticado
    |--------------------------------------------------------------------------
    */

    Route::get('/estagiario/candidatura', [
        CandidaturaController::class,
        'minhaCandidatura'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Sessão
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [
        AuthController::class,
        'logout'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Documentos
    |--------------------------------------------------------------------------
    */

    Route::post('/documentos', [
        DocumentoController::class,
        'store'
    ]);

    Route::get('/documentos/{documento}', [
        DocumentoController::class,
        'download'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Rotas exclusivas do administrador
    |--------------------------------------------------------------------------
    */

    Route::middleware('admin')->prefix('admin')->group(function () {

        Route::get('/dashboard', [
            AdminController::class,
            'dashboard'
        ]);

        Route::get('/candidaturas', [
            AdminController::class,
            'candidaturas'
        ]);

        Route::get('/candidaturas/{id}', [
            AdminController::class,
            'show'
        ]);

        Route::patch('/candidaturas/{id}/aprovar', [
            AdminController::class,
            'aprovar'
        ]);

        Route::patch('/candidaturas/{id}/recusar', [
            AdminController::class,
            'recusar'
        ]);
    });
});

