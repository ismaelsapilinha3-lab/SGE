<?php

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Estagio;
use App\Models\Perfil;
use App\Models\User;

/**
 * Cria um supervisor ativo associado a uma direção.
 */
function criarSupervisorDoDepartamento(int $idDepartamento): User
{
    $supervisor = User::factory()->supervisor()->create();

    Perfil::create([
        'id_utilizador' => $supervisor->id_utilizador,
        'id_departamento' => $idDepartamento,
    ]);

    return $supervisor;
}

/**
 * Cria um estágio cuja candidatura foi decidida pelo coordenador indicado.
 */
function criarEstagioDoCoordenador(
    User $coordenador,
    int $idDepartamento
): Estagio {

    $candidatura = Candidatura::factory()->aprovada()->create([
        'id_coordenador' => $coordenador->id_utilizador,
        'id_departamento_selecionado' => $idDepartamento,
    ]);

    return Estagio::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
    ]);
}

beforeEach(function () {
    $this->departamento = Departamento::factory()->create();

    $this->coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $this->coordenador->id_utilizador,
        'id_departamento' => $this->departamento->id_departamento,
    ]);

    $this->actingAs($this->coordenador, 'sanctum');
});

it('atribui um supervisor da direção ao estágio', function () {
    $estagio = criarEstagioDoCoordenador(
        $this->coordenador,
        $this->departamento->id_departamento
    );

    $supervisor = criarSupervisorDoDepartamento(
        $this->departamento->id_departamento
    );

    $this->postJson(
        "/api/coordenador/estagios/{$estagio->id_estagio}/supervisores",
        [
            'id_supervisor' => $supervisor->id_utilizador,
        ]
    )->assertCreated()
        ->assertJsonPath('message', 'Supervisor atribuído com sucesso.');

    expect($estagio->supervisoresAtivos()->count())->toBe(1)
        ->and(
            $estagio->supervisoresAtivos->first()->id_coordenador
        )->toBe($this->coordenador->id_utilizador);
});

it('impede atribuir o mesmo supervisor duas vezes', function () {
    $estagio = criarEstagioDoCoordenador(
        $this->coordenador,
        $this->departamento->id_departamento
    );

    $supervisor = criarSupervisorDoDepartamento(
        $this->departamento->id_departamento
    );

    $rota = "/api/coordenador/estagios/{$estagio->id_estagio}/supervisores";

    $this->postJson($rota, [
        'id_supervisor' => $supervisor->id_utilizador,
    ])->assertCreated();

    $this->postJson($rota, [
        'id_supervisor' => $supervisor->id_utilizador,
    ])->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Este supervisor já está atribuído a este estágio.'
        );
});

it('recusa um supervisor de outra direção', function () {
    $estagio = criarEstagioDoCoordenador(
        $this->coordenador,
        $this->departamento->id_departamento
    );

    $outraDirecao = Departamento::factory()->create();

    $supervisor = criarSupervisorDoDepartamento(
        $outraDirecao->id_departamento
    );

    $this->postJson(
        "/api/coordenador/estagios/{$estagio->id_estagio}/supervisores",
        [
            'id_supervisor' => $supervisor->id_utilizador,
        ]
    )->assertStatus(422)
        ->assertJsonPath(
            'message',
            'O supervisor não pertence à direção do coordenador.'
        );
});

it('remove um supervisor sem apagar o histórico', function () {
    $estagio = criarEstagioDoCoordenador(
        $this->coordenador,
        $this->departamento->id_departamento
    );

    $supervisor = criarSupervisorDoDepartamento(
        $this->departamento->id_departamento
    );

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'id_coordenador' => $this->coordenador->id_utilizador,
        'atribuido_em' => now(),
    ]);

    $this->deleteJson(
        "/api/coordenador/estagios/{$estagio->id_estagio}"
        ."/supervisores/{$supervisor->id_utilizador}"
    )->assertOk()
        ->assertJsonPath('message', 'Supervisor removido do estágio.');

    expect($estagio->supervisoresAtivos()->count())->toBe(0)
        ->and($estagio->supervisores()->count())->toBe(1);
});

it('não atribui supervisores a um estágio terminado', function () {
    $candidatura = Candidatura::factory()->aprovada()->create([
        'id_coordenador' => $this->coordenador->id_utilizador,
        'id_departamento_selecionado' => $this->departamento->id_departamento,
    ]);

    $estagio = Estagio::factory()->concluido()->create([
        'id_candidatura' => $candidatura->id_candidatura,
    ]);

    $supervisor = criarSupervisorDoDepartamento(
        $this->departamento->id_departamento
    );

    $this->postJson(
        "/api/coordenador/estagios/{$estagio->id_estagio}/supervisores",
        [
            'id_supervisor' => $supervisor->id_utilizador,
        ]
    )->assertStatus(422);
});

it('não remove supervisores de um estágio terminado', function () {
    $candidatura = Candidatura::factory()->aprovada()->create([
        'id_coordenador' => $this->coordenador->id_utilizador,
        'id_departamento_selecionado' => $this->departamento->id_departamento,
    ]);

    $estagio = Estagio::factory()->concluido()->create([
        'id_candidatura' => $candidatura->id_candidatura,
    ]);

    $supervisor = criarSupervisorDoDepartamento(
        $this->departamento->id_departamento
    );

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'id_coordenador' => $this->coordenador->id_utilizador,
        'atribuido_em' => now(),
    ]);

    $this->deleteJson(
        "/api/coordenador/estagios/{$estagio->id_estagio}"
        ."/supervisores/{$supervisor->id_utilizador}"
    )->assertStatus(422);

    expect($estagio->supervisoresAtivos()->count())->toBe(1);
});

it('lista apenas os estágios da direção do coordenador', function () {
    criarEstagioDoCoordenador(
        $this->coordenador,
        $this->departamento->id_departamento
    );

    $outraDirecao = Departamento::factory()->create();

    $outroCoordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $outroCoordenador->id_utilizador,
        'id_departamento' => $outraDirecao->id_departamento,
    ]);

    criarEstagioDoCoordenador(
        $outroCoordenador,
        $outraDirecao->id_departamento
    );

    $this->getJson('/api/coordenador/estagios')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('esconde estágios de outra direção', function () {
    $outraDirecao = Departamento::factory()->create();

    $outroCoordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $outroCoordenador->id_utilizador,
        'id_departamento' => $outraDirecao->id_departamento,
    ]);

    $estagioAlheio = criarEstagioDoCoordenador(
        $outroCoordenador,
        $outraDirecao->id_departamento
    );

    $this->getJson(
        "/api/coordenador/estagios/{$estagioAlheio->id_estagio}/supervisores"
    )->assertStatus(404);
});

it('recusa o acesso de quem não é coordenador', function () {
    $estagiario = User::factory()->estagiario()->create();

    $this->actingAs($estagiario, 'sanctum');

    $this->getJson('/api/coordenador/estagios')->assertStatus(403);
});
