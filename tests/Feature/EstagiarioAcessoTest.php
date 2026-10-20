<?php

use App\Models\Candidatura;
use App\Models\User;

/**
 * As rotas /api/estagiario/* exigem perfil de estagiário,
 * para além da autenticação por Sanctum.
 */
it('exige autenticação nas rotas do estagiário', function () {
    $this->getJson('/api/estagiario/estagio')->assertStatus(401);
});

it('permite ao estagiário autenticado aceder às suas rotas', function () {
    $estagiario = User::factory()->estagiario()->create();

    Candidatura::factory()->create([
        'id_utilizador' => $estagiario->id_utilizador,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/candidatura')
        ->assertOk();
});

it('impede outros perfis de aceder às rotas do estagiário', function (string $perfil) {
    $utilizador = User::factory()->{$perfil}()->create();

    $this->actingAs($utilizador, 'sanctum')
        ->getJson('/api/estagiario/estagio')
        ->assertStatus(403)
        ->assertJsonPath('message', 'Acesso restrito ao estagiário.');
})->with([
    'administrador',
    'coordenador',
    'supervisor',
]);

it('impede um estagiário inativo de aceder às suas rotas', function () {
    $estagiario = User::factory()->estagiario()->inativo()->create();

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/estagio')
        ->assertStatus(403)
        ->assertJsonPath('message', 'Utilizador não autorizado.');
});
