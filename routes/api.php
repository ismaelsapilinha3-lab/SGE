<?php

use App\Http\Controllers\Admin\EstagioAdminController;
use App\Http\Controllers\Admin\UtilizadorAdminController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidaturaController;
use App\Http\Controllers\CoordenadorController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\DocumentoEstagioController;
use App\Http\Controllers\EstagiarioController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\SupervisorController;
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

Route::get('/candidaturas/limite', [
    CandidaturaController::class,
    'limite',
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
    | Perfil do utilizador autenticado
    |--------------------------------------------------------------------------
    |
    | Cada utilizador consulta e atualiza a sua própria foto de perfil.
    |
    */

    Route::get('/perfil', [
        PerfilController::class,
        'mostrar',
    ]);

    Route::post('/perfil/foto', [
        PerfilController::class,
        'atualizarFoto',
    ]);

    Route::delete('/perfil/foto', [
        PerfilController::class,
        'removerFoto',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Notificações do utilizador autenticado
    |--------------------------------------------------------------------------
    */

    Route::get('/notificacoes', [
        NotificacaoController::class,
        'index',
    ]);

    Route::patch('/notificacoes/ler-todas', [
        NotificacaoController::class,
        'marcarTodasComoLidas',
    ]);

    Route::patch('/notificacoes/{notificacao}/ler', [
        NotificacaoController::class,
        'marcarComoLida',
    ]);

    Route::delete('/notificacoes/{notificacao}', [
        NotificacaoController::class,
        'destroy',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Rotas exclusivas do estagiário
    |--------------------------------------------------------------------------
    |
    | Estas rotas exigem perfil de estagiário, para além da autenticação.
    |
    */

    Route::middleware('estagiario')
        ->prefix('estagiario')
        ->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Candidatura do estagiário
            |--------------------------------------------------------------------------
            */

            Route::get('/candidatura', [
                CandidaturaController::class,
                'minhaCandidatura',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Estágio do estagiário
            |--------------------------------------------------------------------------
            |
            | O estágio é resolvido a partir do utilizador autenticado, pelo que
            | não é preciso indicar o identificador do estágio.
            |
            */

            Route::get('/estagio', [
                EstagiarioController::class,
                'estagio',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Contrato de estágio
            |--------------------------------------------------------------------------
            */

            Route::post('/estagio/contrato', [
                EstagiarioController::class,
                'submeterContrato',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Relatórios de estágio do estagiário
            |--------------------------------------------------------------------------
            */

            Route::get('/estagio/relatorios', [
                EstagiarioController::class,
                'relatorios',
            ]);

            Route::post('/estagio/relatorios', [
                EstagiarioController::class,
                'criarRelatorio',
            ]);

            Route::put('/estagio/relatorios/{relatorio}', [
                EstagiarioController::class,
                'atualizarRelatorio',
            ]);

            Route::post('/estagio/relatorios/{relatorio}/submeter', [
                EstagiarioController::class,
                'submeterRelatorio',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Assiduidade do estagiário
            |--------------------------------------------------------------------------
            */

            Route::get('/estagio/assiduidades', [
                EstagiarioController::class,
                'assiduidades',
            ]);

            Route::post('/estagio/assiduidades/{assiduidade}/justificar', [
                EstagiarioController::class,
                'justificarFalta',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Atividades do estagiário
            |--------------------------------------------------------------------------
            */

            Route::get('/estagio/atividades', [
                EstagiarioController::class,
                'atividades',
            ]);

            Route::post('/estagio/atividades', [
                EstagiarioController::class,
                'criarAtividade',
            ]);

            Route::put('/estagio/atividades/{atividade}', [
                EstagiarioController::class,
                'atualizarAtividade',
            ]);

            Route::delete('/estagio/atividades/{atividade}', [
                EstagiarioController::class,
                'removerAtividade',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Avaliações e feedback do estagiário
            |--------------------------------------------------------------------------
            */

            Route::get('/estagio/avaliacoes', [
                EstagiarioController::class,
                'avaliacoes',
            ]);

            Route::get('/estagio/feedback', [
                EstagiarioController::class,
                'feedback',
            ]);

            Route::post('/estagio/feedback', [
                EstagiarioController::class,
                'guardarFeedback',
            ]);
        });

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
    | Documentos de estágio (contrato)
    |--------------------------------------------------------------------------
    */

    Route::get('/documentos-estagio/{documento}', [
        DocumentoEstagioController::class,
        'download',
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

            Route::get('/estagios', [
                EstagioAdminController::class,
                'index',
            ]);

            Route::get('/estagios/{id}', [
                EstagioAdminController::class,
                'show',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Iniciar / alterar o estado de um estágio
            |--------------------------------------------------------------------------
            |
            | A rota indexada por candidatura é a que o painel usa. A versão
            | sob /estagios é o caminho canónico; ambas partilham a mesma ação.
            |
            */

            Route::patch('/estagios/{idCandidatura}/estado', [
                EstagioAdminController::class,
                'atualizarEstado',
            ]);

            Route::patch('/estagiarios/{idCandidatura}/estado', [
                EstagioAdminController::class,
                'atualizarEstado',
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

            Route::get('/supervisores', [
                CoordenadorController::class,
                'listarSupervisores',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Estágios e atribuição de supervisores
            |--------------------------------------------------------------------------
            */

            Route::get('/estagios', [
                CoordenadorController::class,
                'estagios',
            ]);

            Route::get('/estagios/{id}/supervisores', [
                CoordenadorController::class,
                'supervisores',
            ]);

            Route::post('/estagios/{id}/supervisores', [
                CoordenadorController::class,
                'atribuirSupervisor',
            ]);

            Route::delete(
                '/estagios/{id}/supervisores/{idSupervisor}',
                [
                    CoordenadorController::class,
                    'removerSupervisor',
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Plano de estágio
            |--------------------------------------------------------------------------
            */

            Route::get('/estagios/{id}/plano', [
                CoordenadorController::class,
                'mostrarPlano',
            ]);

            Route::patch('/estagios/{id}/plano/aprovar', [
                CoordenadorController::class,
                'aprovarPlano',
            ]);

            Route::patch('/estagios/{id}/plano/recusar', [
                CoordenadorController::class,
                'recusarPlano',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Documentos e relatórios do estágio
            |--------------------------------------------------------------------------
            */

            Route::get('/estagios/{id}/documentos', [
                CoordenadorController::class,
                'documentos',
            ]);

            Route::get('/estagios/{id}/relatorios', [
                CoordenadorController::class,
                'relatorios',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Assiduidade e atividades do estágio
            |--------------------------------------------------------------------------
            */

            Route::get('/estagios/{id}/assiduidades', [
                CoordenadorController::class,
                'assiduidades',
            ]);

            Route::get('/estagios/{id}/atividades', [
                CoordenadorController::class,
                'atividades',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Avaliações e feedback do estágio
            |--------------------------------------------------------------------------
            */

            Route::get('/estagios/{id}/avaliacoes', [
                CoordenadorController::class,
                'avaliacoes',
            ]);

            Route::get('/estagios/{id}/feedback', [
                CoordenadorController::class,
                'feedback',
            ]);

        });

    /*
    |--------------------------------------------------------------------------
    | Rotas exclusivas do supervisor
    |--------------------------------------------------------------------------
    */

    Route::middleware('supervisor')
        ->prefix('supervisor')
        ->group(function () {

            Route::get('/dashboard', [
                SupervisorController::class,
                'dashboard',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Estágios atribuídos e plano de estágio
            |--------------------------------------------------------------------------
            */

            Route::get('/estagios', [
                SupervisorController::class,
                'estagios',
            ]);

            Route::get('/estagios/{id}/plano', [
                SupervisorController::class,
                'mostrarPlano',
            ]);

            Route::put('/estagios/{id}/plano', [
                SupervisorController::class,
                'submeterPlano',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Documentos e relatórios do estágio
            |--------------------------------------------------------------------------
            */

            Route::get('/estagios/{id}/documentos', [
                SupervisorController::class,
                'documentos',
            ]);

            Route::patch('/estagios/{id}/documentos/{documento}/validar', [
                SupervisorController::class,
                'validarDocumento',
            ]);

            Route::patch('/estagios/{id}/documentos/{documento}/rejeitar', [
                SupervisorController::class,
                'rejeitarDocumento',
            ]);

            Route::get('/estagios/{id}/relatorios', [
                SupervisorController::class,
                'relatorios',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Assiduidade e atividades do estágio
            |--------------------------------------------------------------------------
            */

            Route::get('/estagios/{id}/assiduidades', [
                SupervisorController::class,
                'assiduidades',
            ]);

            Route::post('/estagios/{id}/assiduidades', [
                SupervisorController::class,
                'registarAssiduidade',
            ]);

            Route::delete('/estagios/{id}/assiduidades/{assiduidade}', [
                SupervisorController::class,
                'removerAssiduidade',
            ]);

            Route::patch(
                '/estagios/{id}/assiduidades/{assiduidade}/justificacao/aprovar',
                [
                    SupervisorController::class,
                    'aprovarJustificacao',
                ]
            );

            Route::patch(
                '/estagios/{id}/assiduidades/{assiduidade}/justificacao/recusar',
                [
                    SupervisorController::class,
                    'recusarJustificacao',
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Avaliações e feedback do estágio
            |--------------------------------------------------------------------------
            */

            Route::get('/estagios/{id}/avaliacoes', [
                SupervisorController::class,
                'avaliacoes',
            ]);

            Route::post('/estagios/{id}/avaliacoes', [
                SupervisorController::class,
                'registarAvaliacao',
            ]);

            Route::delete('/estagios/{id}/avaliacoes/{avaliacao}', [
                SupervisorController::class,
                'removerAvaliacao',
            ]);

            Route::get('/estagios/{id}/feedback', [
                SupervisorController::class,
                'feedback',
            ]);

            Route::get('/estagios/{id}/atividades', [
                SupervisorController::class,
                'atividades',
            ]);

        });

});
