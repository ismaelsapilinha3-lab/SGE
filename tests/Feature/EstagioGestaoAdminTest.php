<?php

use App\Models\Candidatura;
use App\Models\Estagio;
use App\Models\HistoricoEstado;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->administrador()->create();

    $this->actingAs($this->admin, 'sanctum');
});

it('inicia um estágio para uma candidatura aprovada', function () {
    $estagiario = User::factory()->estagiario()->create();

    $candidatura = Candidatura::factory()->aprovada()->create([
        'id_utilizador' => $estagiario->id_utilizador,
    ]);

    $this->patchJson(
        "/api/admin/estagiarios/{$candidatura->id_candidatura}/estado",
        [
            'estado' => 'ativo',
            'data_inicio' => '2026-01-05',
            'data_fim_prevista' => '2026-07-05',
        ]
    )->assertCreated()
        ->assertJsonPath('message', 'Estágio iniciado com sucesso.');

    $estagio = Estagio::where(
        'id_candidatura',
        $candidatura->id_candidatura
    )->first();

    expect($estagio)->not->toBeNull()
        ->and($estagio->estado)->toBe('ativo')
        ->and($estagio->id_estagiario)->toBe($estagiario->id_utilizador)
        ->and(
            HistoricoEstado::where('id_estagio', $estagio->id_estagio)->count()
        )->toBe(1);
});

it('exige que um novo estágio comece no estado ativo', function () {
    $estagiario = User::factory()->estagiario()->create();

    $candidatura = Candidatura::factory()->aprovada()->create([
        'id_utilizador' => $estagiario->id_utilizador,
    ]);

    $this->patchJson(
        "/api/admin/estagiarios/{$candidatura->id_candidatura}/estado",
        [
            'estado' => 'concluido',
            'data_inicio' => '2026-01-05',
            'data_fim_prevista' => '2026-07-05',
        ]
    )->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Um novo estágio tem de começar no estado ativo.'
        );
});

it('não inicia um estágio de uma candidatura não aprovada', function () {
    $estagiario = User::factory()->estagiario()->create();

    $candidatura = Candidatura::factory()->create([
        'id_utilizador' => $estagiario->id_utilizador,
    ]);

    $this->patchJson(
        "/api/admin/estagiarios/{$candidatura->id_candidatura}/estado",
        [
            'estado' => 'ativo',
            'data_inicio' => '2026-01-05',
            'data_fim_prevista' => '2026-07-05',
        ]
    )->assertStatus(422);
});

it('exige que a data de fim prevista seja posterior à de início', function () {
    $estagiario = User::factory()->estagiario()->create();

    $candidatura = Candidatura::factory()->aprovada()->create([
        'id_utilizador' => $estagiario->id_utilizador,
    ]);

    $this->patchJson(
        "/api/admin/estagiarios/{$candidatura->id_candidatura}/estado",
        [
            'estado' => 'ativo',
            'data_inicio' => '2026-01-05',
            'data_fim_prevista' => '2026-01-05',
        ]
    )->assertStatus(422);

    expect(
        Estagio::where('id_candidatura', $candidatura->id_candidatura)->exists()
    )->toBeFalse();
});

it('suspende e retoma um estágio registando o histórico', function () {
    $estagio = Estagio::factory()->create();

    $rota = "/api/admin/estagios/{$estagio->id_candidatura}/estado";

    $this->patchJson($rota, [
        'estado' => 'suspenso',
        'motivo_suspensao' => 'Motivos pessoais.',
    ])->assertOk();

    $estagio->refresh();

    expect($estagio->estado)->toBe('suspenso')
        ->and($estagio->motivo_suspensao)->toBe('Motivos pessoais.');

    $this->patchJson($rota, [
        'estado' => 'ativo',
    ])->assertOk();

    $estagio->refresh();

    expect($estagio->estado)->toBe('ativo')
        ->and($estagio->motivo_suspensao)->toBeNull()
        ->and(
            HistoricoEstado::where('id_estagio', $estagio->id_estagio)->count()
        )->toBe(2);
});

it('impede reabrir um estágio concluído', function () {
    $estagio = Estagio::factory()->create();

    $rota = "/api/admin/estagiarios/{$estagio->id_candidatura}/estado";

    $this->patchJson($rota, [
        'estado' => 'concluido',
    ])->assertOk();

    $this->patchJson($rota, [
        'estado' => 'ativo',
    ])->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Este estágio já terminou e não pode ser alterado.'
        );
});

it('rejeita transições de estado não permitidas', function () {
    $estagio = Estagio::factory()->suspenso()->create();

    $this->patchJson(
        "/api/admin/estagiarios/{$estagio->id_candidatura}/estado",
        [
            'estado' => 'concluido',
        ]
    )->assertStatus(422);
});

it('lista os estágios com o estagiário e os supervisores ativos', function () {
    $estagio = Estagio::factory()->create();

    $supervisor = User::factory()->supervisor()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    $this->getJson('/api/admin/estagios')
        ->assertOk()
        ->assertJsonPath('data.0.id_estagio', $estagio->id_estagio)
        ->assertJsonPath(
            'data.0.supervisores_ativos.0.supervisor.id_utilizador',
            $supervisor->id_utilizador
        );
});

it('recusa o acesso de quem não é administrador', function () {
    $estagiario = User::factory()->estagiario()->create();

    $this->actingAs($estagiario, 'sanctum');

    $this->getJson('/api/admin/estagios')->assertStatus(403);
});

it('exige autenticação', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/admin/estagios')->assertStatus(401);
});
