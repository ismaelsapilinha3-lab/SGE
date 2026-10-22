<?php

use App\Models\Estagio;
use App\Models\User;

beforeEach(function () {
    $this->supervisor = User::factory()->supervisor()->create();
});

it('mostra apenas os estágios atribuídos ao supervisor', function () {
    $atribuido = Estagio::factory()->create();

    $atribuido->supervisores()->create([
        'id_supervisor' => $this->supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    Estagio::factory()->create();

    $resposta = $this->actingAs($this->supervisor, 'sanctum')
        ->getJson('/api/supervisor/dashboard')
        ->assertOk()
        ->assertJsonPath('resumo.estagios_ativos', 1)
        ->assertJsonCount(1, 'estagios');

    expect($resposta->json('estagios.0.id_estagio'))
        ->toBe($atribuido->id_estagio);
});

it('esconde os estágios cujo vínculo foi removido', function () {
    $estagio = Estagio::factory()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $this->supervisor->id_utilizador,
        'atribuido_em' => now(),
        'removido_em' => now(),
    ]);

    $this->actingAs($this->supervisor, 'sanctum')
        ->getJson('/api/supervisor/dashboard')
        ->assertOk()
        ->assertJsonCount(0, 'estagios');
});

it('não conta estágios suspensos como ativos', function () {
    $suspenso = Estagio::factory()->suspenso()->create();

    $suspenso->supervisores()->create([
        'id_supervisor' => $this->supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    $this->actingAs($this->supervisor, 'sanctum')
        ->getJson('/api/supervisor/dashboard')
        ->assertOk()
        ->assertJsonPath('resumo.estagios_ativos', 0)
        ->assertJsonCount(1, 'estagios');
});

it('recusa o acesso de quem não é supervisor', function () {
    $estagiario = User::factory()->estagiario()->create();

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/supervisor/dashboard')
        ->assertStatus(403);
});
