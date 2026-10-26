<?php

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Estagio;
use App\Models\Perfil;
use App\Models\User;

/**
 * Cria um coordenador ativo associado a uma direção.
 */
function criarCoordenadorDaDirecao(int $idDepartamento): User
{
    $coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $coordenador->id_utilizador,
        'id_departamento' => $idDepartamento,
    ]);

    return $coordenador;
}

describe('visibilidade dos estágios no painel do coordenador', function () {

    beforeEach(function () {
        $this->departamento = Departamento::factory()->create();

        $this->coordenador = criarCoordenadorDaDirecao(
            $this->departamento->id_departamento
        );

        $this->actingAs($this->coordenador, 'sanctum');
    });

    it('mostra ao coordenador um estágio da sua direção sem coordenador', function () {
        /*
         * Reproduz o caso reportado: o admin inicia o estágio a partir de uma
         * candidatura aprovada que nunca foi encaminhada, pelo que ficou sem
         * id_coordenador. O coordenador tem de a conseguir ver para lhe
         * atribuir supervisor.
         */
        $estagiario = User::factory()->estagiario()->create();

        $candidatura = Candidatura::factory()->aprovada()->create([
            'id_utilizador' => $estagiario->id_utilizador,
            'id_coordenador' => null,
            'id_departamento_selecionado' => $this->departamento->id_departamento,
        ]);

        $estagio = Estagio::factory()->create([
            'id_candidatura' => $candidatura->id_candidatura,
            'id_estagiario' => $estagiario->id_utilizador,
        ]);

        $this->getJson('/api/coordenador/estagios')
            ->assertOk()
            ->assertJsonFragment([
                'id_estagio' => $estagio->id_estagio,
            ]);

        /*
         * Ao ser listada, a candidatura órfã é assumida pelo coordenador.
         */
        expect(
            $candidatura->fresh()->id_coordenador
        )->toBe($this->coordenador->id_utilizador);
    });

    it('assume um estágio cujo coordenador está inativo', function () {
        $inativo = criarCoordenadorDaDirecao(
            $this->departamento->id_departamento
        );

        $inativo->update(['ativo' => false]);

        $estagiario = User::factory()->estagiario()->create();

        $candidatura = Candidatura::factory()->aprovada()->create([
            'id_utilizador' => $estagiario->id_utilizador,
            'id_coordenador' => $inativo->id_utilizador,
            'id_departamento_selecionado' => $this->departamento->id_departamento,
        ]);

        $estagio = Estagio::factory()->create([
            'id_candidatura' => $candidatura->id_candidatura,
            'id_estagiario' => $estagiario->id_utilizador,
        ]);

        $this->getJson('/api/coordenador/estagios')
            ->assertOk()
            ->assertJsonFragment([
                'id_estagio' => $estagio->id_estagio,
            ]);

        expect($candidatura->fresh()->id_coordenador)
            ->toBe($this->coordenador->id_utilizador);
    });

    it('assume um estágio cujo coordenador foi removido', function () {
        $removido = criarCoordenadorDaDirecao(
            $this->departamento->id_departamento
        );

        $removido->delete();

        $estagiario = User::factory()->estagiario()->create();

        $candidatura = Candidatura::factory()->aprovada()->create([
            'id_utilizador' => $estagiario->id_utilizador,
            'id_coordenador' => $removido->id_utilizador,
            'id_departamento_selecionado' => $this->departamento->id_departamento,
        ]);

        $estagio = Estagio::factory()->create([
            'id_candidatura' => $candidatura->id_candidatura,
            'id_estagiario' => $estagiario->id_utilizador,
        ]);

        $this->getJson('/api/coordenador/estagios')
            ->assertOk()
            ->assertJsonFragment([
                'id_estagio' => $estagio->id_estagio,
            ]);

        expect($candidatura->fresh()->id_coordenador)
            ->toBe($this->coordenador->id_utilizador);
    });

    it('não assume um estágio de outro coordenador ativo da mesma direção', function () {
        $outroCoordenador = criarCoordenadorDaDirecao(
            $this->departamento->id_departamento
        );

        $estagiario = User::factory()->estagiario()->create();

        $candidatura = Candidatura::factory()->aprovada()->create([
            'id_utilizador' => $estagiario->id_utilizador,
            'id_coordenador' => $outroCoordenador->id_utilizador,
            'id_departamento_selecionado' => $this->departamento->id_departamento,
        ]);

        $estagio = Estagio::factory()->create([
            'id_candidatura' => $candidatura->id_candidatura,
            'id_estagiario' => $estagiario->id_utilizador,
        ]);

        $this->getJson('/api/coordenador/estagios')
            ->assertOk()
            ->assertJsonMissing([
                'id_estagio' => $estagio->id_estagio,
            ]);

        expect($candidatura->fresh()->id_coordenador)
            ->toBe($outroCoordenador->id_utilizador);
    });

    it('não mostra estágios de outra direção', function () {
        $outroDepartamento = Departamento::factory()->create();

        $estagiario = User::factory()->estagiario()->create();

        $candidatura = Candidatura::factory()->aprovada()->create([
            'id_utilizador' => $estagiario->id_utilizador,
            'id_coordenador' => null,
            'id_departamento_selecionado' => $outroDepartamento->id_departamento,
        ]);

        $estagio = Estagio::factory()->create([
            'id_candidatura' => $candidatura->id_candidatura,
            'id_estagiario' => $estagiario->id_utilizador,
        ]);

        $this->getJson('/api/coordenador/estagios')
            ->assertOk()
            ->assertJsonMissing([
                'id_estagio' => $estagio->id_estagio,
            ]);

        expect(
            $candidatura->fresh()->id_coordenador
        )->toBeNull();
    });

    it('permite atribuir supervisor a um estágio sem coordenador', function () {
        $estagiario = User::factory()->estagiario()->create();

        $candidatura = Candidatura::factory()->aprovada()->create([
            'id_utilizador' => $estagiario->id_utilizador,
            'id_coordenador' => null,
            'id_departamento_selecionado' => $this->departamento->id_departamento,
        ]);

        $estagio = Estagio::factory()->create([
            'id_candidatura' => $candidatura->id_candidatura,
            'id_estagiario' => $estagiario->id_utilizador,
        ]);

        $supervisor = User::factory()->supervisor()->create();

        Perfil::create([
            'id_utilizador' => $supervisor->id_utilizador,
            'id_departamento' => $this->departamento->id_departamento,
        ]);

        $this->postJson(
            "/api/coordenador/estagios/{$estagio->id_estagio}/supervisores",
            ['id_supervisor' => $supervisor->id_utilizador]
        )->assertCreated();

        expect($estagio->supervisoresAtivos()->count())->toBe(1);
    });

});

describe('início de estágio atribui o coordenador da direção', function () {

    it('associa o coordenador ativo ao iniciar o estágio', function () {
        $departamento = Departamento::factory()->create();

        $coordenador = criarCoordenadorDaDirecao(
            $departamento->id_departamento
        );

        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin, 'sanctum');

        $estagiario = User::factory()->estagiario()->create();

        $candidatura = Candidatura::factory()->aprovada()->create([
            'id_utilizador' => $estagiario->id_utilizador,
            'id_coordenador' => null,
            'id_departamento_selecionado' => $departamento->id_departamento,
        ]);

        $this->patchJson(
            "/api/admin/estagiarios/{$candidatura->id_candidatura}/estado",
            [
                'estado' => 'ativo',
                'data_inicio' => '2026-02-02',
                'data_fim_prevista' => '2026-08-02',
            ]
        )->assertCreated();

        expect(
            $candidatura->fresh()->id_coordenador
        )->toBe($coordenador->id_utilizador);
    });

});
