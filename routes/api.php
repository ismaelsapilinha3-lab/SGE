<?php

use App\Http\Controllers\CandidaturaController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\DocumentoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/departamentos', [DepartamentoController::class, 'index']);

Route::post('/candidaturas', [CandidaturaController::class, 'store'])
    ->middleware('throttle:5,1');

Route::post('/documentos', [DocumentoController::class, 'store']);

Route::get('/documentos/{documento}', [DocumentoController::class, 'download'])
    ->middleware('auth:sanctum');

