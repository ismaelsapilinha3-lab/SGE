<?php

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\User;
use App\Services\LimiteCandidaturasTrimestrais;
use Illuminate\Support\Facades\Storage;

/**
 * O programa admite no máximo 100 candidaturas por trimestre civil,
 * e o limite é verificado no servidor.
 */
it('informa a disponibilidade do trimestre corrente', function () {
    Candidatura::factory()->count(3)->create();

    $this->getJson('/api/candidaturas/limite')
        ->assertOk()
        ->assertJsonPath(
            'limite',
            LimiteCandidaturasTrimestrais::MAXIMO_POR_TRIMESTRE
        )
        ->assertJsonPath('submetidas', 3)
        ->assertJsonPath(
            'vagas_restantes',
            LimiteCandidaturasTrimestrais::MAXIMO_POR_TRIMESTRE - 3
        )
        ->assertJsonPath('limite_atingido', false);
});

it('marca o limite como atingido quando já existem 100 candidaturas no trimestre', function () {
    Candidatura::factory()
        ->count(LimiteCandidaturasTrimestrais::MAXIMO_POR_TRIMESTRE)
        ->create();

    $this->getJson('/api/candidaturas/limite')
        ->assertOk()
        ->assertJsonPath('vagas_restantes', 0)
        ->assertJsonPath('limite_atingido', true);
});

it('ignora candidaturas de trimestres anteriores', function () {
    Candidatura::factory()
        ->count(LimiteCandidaturasTrimestrais::MAXIMO_POR_TRIMESTRE)
        ->create([
            'created_at' => now()->subMonths(4),
            'updated_at' => now()->subMonths(4),
        ]);

    $this->getJson('/api/candidaturas/limite')
        ->assertOk()
        ->assertJsonPath('submetidas', 0)
        ->assertJsonPath('limite_atingido', false);
});

it('recusa novas candidaturas quando o trimestre está cheio', function () {
    Storage::fake('local');

    Candidatura::factory()
        ->count(LimiteCandidaturasTrimestrais::MAXIMO_POR_TRIMESTRE)
        ->create();

    $departamento = Departamento::factory()->create();

    $this->postJson('/api/candidaturas', [
        'nome' => 'Candidato Tardio',
        'email' => 'tardio@exemplo.test',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'curso' => 'Engenharia Informática',
        'universidade' => 'Universidade Teste',
        'nascimento' => '2000-01-01',
        'sexo' => 'Masculino',
        'bi' => '000000000AA000',
        'contacto' => '923000000',
        'id_departamento_selecionado' => $departamento->id_departamento,
    ])
        ->assertStatus(422)
        ->assertJsonPath('vagas_restantes', 0)
        ->assertJsonPath('limite_atingido', true);

    expect(
        User::where('email', 'tardio@exemplo.test')->exists()
    )->toBeFalse();
});

it('conta corretamente as vagas restantes do trimestre', function () {
    $servico = new LimiteCandidaturasTrimestrais;

    expect($servico->vagasRestantes())->toBe(100);

    Candidatura::factory()->count(40)->create();

    expect($servico->vagasRestantes())->toBe(60)
        ->and($servico->totalNoTrimestre())->toBe(40)
        ->and($servico->limiteAtingido())->toBeFalse();
});
