<?php

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Perfil;
use App\Models\User;

/**
 * Candidatura em análise com estagiário e coordenador da direção.
 *
 * @return array{0: Candidatura, 1: User, 2: User, 3: Departamento}
 */
function candidaturaParaAprovacao(): array
{
    $departamento = Departamento::factory()->create();

    $estagiario = User::factory()->estagiario()->create();

    $coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $coordenador->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $candidatura = Candidatura::factory()->create([
        'id_utilizador' => $estagiario->id_utilizador,
        'id_coordenador' => $coordenador->id_utilizador,
        'id_departamento_selecionado' => $departamento->id_departamento,
    ]);

    return [$candidatura, $estagiario, $coordenador, $departamento];
}

it('associa a direcao da candidatura ao perfil do estagiario ao aprovar', function () {
    [$candidatura, $estagiario, $coordenador, $departamento] =
        candidaturaParaAprovacao();

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/candidaturas/{$candidatura->id_candidatura}/aprovar"
        )
        ->assertOk();

    $this->assertDatabaseHas('perfis', [
        'id_utilizador' => $estagiario->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);
});

it('preserva os restantes dados do perfil ao associar a direcao', function () {
    [$candidatura, $estagiario, $coordenador, $departamento] =
        candidaturaParaAprovacao();

    Perfil::create([
        'id_utilizador' => $estagiario->id_utilizador,
        'curso' => 'Engenharia Informática',
        'universidade' => 'Universidade Agostinho Neto',
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/candidaturas/{$candidatura->id_candidatura}/aprovar"
        )
        ->assertOk();

    $perfil = Perfil::query()
        ->where('id_utilizador', $estagiario->id_utilizador)
        ->firstOrFail();

    expect($perfil->id_departamento)->toBe($departamento->id_departamento)
        ->and($perfil->curso)->toBe('Engenharia Informática')
        ->and($perfil->universidade)->toBe('Universidade Agostinho Neto');
});

it('mantem a aprovacao quando a candidatura nao tem utilizador associado', function () {
    $departamento = Departamento::factory()->create();

    $coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $coordenador->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $candidatura = Candidatura::factory()->create([
        'id_utilizador' => null,
        'id_coordenador' => $coordenador->id_utilizador,
        'id_departamento_selecionado' => $departamento->id_departamento,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/candidaturas/{$candidatura->id_candidatura}/aprovar"
        )
        ->assertOk();

    expect($candidatura->fresh()->estado)->toBe('aprovada');
});
