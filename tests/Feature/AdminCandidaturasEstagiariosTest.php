<?php

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Estagio;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->administrador()->create();

    $this->actingAs($this->admin, 'sanctum');
});

it('mostra apenas candidaturas por decidir na area de candidaturas', function () {
    $emAnalise = Candidatura::factory()->create();

    $recusada = Candidatura::factory()->recusada()->create();

    $aprovada = Candidatura::factory()->aprovada()->create();

    $resposta = $this->getJson('/api/admin/candidaturas')
        ->assertOk();

    $ids = collect($resposta->json('data'))
        ->pluck('id_candidatura');

    expect($ids)
        ->toContain($emAnalise->id_candidatura)
        ->toContain($recusada->id_candidatura)
        ->not->toContain($aprovada->id_candidatura);
});

it('permite filtrar candidaturas aprovadas quando pedido explicitamente', function () {
    Candidatura::factory()->create();

    $aprovada = Candidatura::factory()->aprovada()->create();

    $this->getJson('/api/admin/candidaturas?estado=aprovada')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id_candidatura',
            $aprovada->id_candidatura
        );
});

it('aceita o filtro por candidaturas encerradas', function () {
    Candidatura::factory()->create();

    $encerrada = Candidatura::factory()->create([
        'estado' => 'encerrada',
    ]);

    $this->getJson('/api/admin/candidaturas?estado=encerrada')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id_candidatura',
            $encerrada->id_candidatura
        );
});

it('mostra candidaturas aceites na area de estagiarios', function () {
    $aprovada = Candidatura::factory()->aprovada()->create();

    $comEstagio = Estagio::factory()->create();

    $resposta = $this->getJson('/api/admin/estagiarios')
        ->assertOk();

    $ids = collect($resposta->json('data'))
        ->pluck('id_candidatura');

    expect($ids)
        ->toContain($aprovada->id_candidatura)
        ->toContain($comEstagio->id_candidatura);
});

it('nao mostra candidaturas em analise na area de estagiarios', function () {
    $emAnalise = Candidatura::factory()->create();

    $resposta = $this->getJson('/api/admin/estagiarios')
        ->assertOk();

    $ids = collect($resposta->json('data'))
        ->pluck('id_candidatura');

    expect($ids)->not->toContain($emAnalise->id_candidatura);
});

it('permite ao administrador recusar com justificacao', function () {
    $candidatura = Candidatura::factory()->create();

    $this->patchJson(
        "/api/admin/candidaturas/{$candidatura->id_candidatura}/recusar",
        ['motivo' => 'Documentos incompletos e fora do prazo.']
    )
        ->assertOk()
        ->assertJsonPath('candidatura.estado', 'recusada');

    $candidatura->refresh();

    expect($candidatura->estado)->toBe('recusada')
        ->and($candidatura->motivo_recusa)
        ->toBe('Documentos incompletos e fora do prazo.')
        ->and($candidatura->data_decisao)->not->toBeNull();

    $this->assertDatabaseHas('historico_estados', [
        'id_candidatura' => $candidatura->id_candidatura,
        'estado_novo' => 'recusada',
    ]);
});

it('exige justificacao para o administrador recusar', function () {
    $candidatura = Candidatura::factory()->create();

    $this->patchJson(
        "/api/admin/candidaturas/{$candidatura->id_candidatura}/recusar",
        ['motivo' => '']
    )->assertUnprocessable();

    expect($candidatura->fresh()->estado)->toBe('em_analise');
});

it('permite ao administrador alterar a direcao e a duracao', function () {
    $departamentoOriginal = Departamento::factory()->create();

    $novoDepartamento = Departamento::factory()->create();

    $candidatura = Candidatura::factory()->create([
        'id_departamento_selecionado' => $departamentoOriginal->id_departamento,
        'duracao_meses' => null,
    ]);

    $this->patchJson(
        "/api/admin/candidaturas/{$candidatura->id_candidatura}",
        [
            'id_departamento' => $novoDepartamento->id_departamento,
            'duracao_meses' => 9,
        ]
    )
        ->assertOk()
        ->assertJsonPath(
            'candidatura.id_departamento_selecionado',
            $novoDepartamento->id_departamento
        )
        ->assertJsonPath('candidatura.duracao_meses', 9);
});

it('nao permite alterar candidaturas ja aprovadas', function () {
    $candidatura = Candidatura::factory()->aprovada()->create();

    $this->patchJson(
        "/api/admin/candidaturas/{$candidatura->id_candidatura}",
        ['duracao_meses' => 12]
    )->assertUnprocessable();
});

it('prorroga o estagio e recalcula a duracao', function () {
    $estagio = Estagio::factory()->create([
        'data_inicio' => '2026-01-15',
        'data_fim_prevista' => '2026-07-15',
        'duracao_meses' => 6,
        'estado' => 'ativo',
    ]);

    $this->patchJson(
        "/api/admin/estagiarios/{$estagio->id_candidatura}/prorrogar",
        [
            'meses' => 3,
            'motivo' => 'Necessidade de concluir as atividades pendentes.',
        ]
    )
        ->assertOk()
        ->assertJsonPath('data.duracao_meses', 9);

    expect($estagio->fresh()->data_fim_prevista->toDateString())
        ->toBe('2026-10-15');

    $this->assertDatabaseHas('prorrogacoes_estagio', [
        'id_estagio' => $estagio->id_estagio,
        'meses' => 3,
        'duracao_anterior' => 6,
        'duracao_nova' => 9,
    ]);
});

it('nao prorroga um estagio concluido', function () {
    $estagio = Estagio::factory()->concluido()->create();

    $this->patchJson(
        "/api/admin/estagiarios/{$estagio->id_candidatura}/prorrogar",
        [
            'meses' => 3,
            'motivo' => 'Pedido de prorrogacao apos o fim do estagio.',
        ]
    )->assertUnprocessable();
});

it('inicia o estagio com a duracao prevista na candidatura', function () {
    $estagiario = User::factory()->estagiario()->create();

    $candidatura = Candidatura::factory()->aprovada()->create([
        'id_utilizador' => $estagiario->id_utilizador,
        'duracao_meses' => 9,
    ]);

    $this->patchJson(
        "/api/admin/estagiarios/{$candidatura->id_candidatura}/estado",
        [
            'estado' => 'ativo',
            'data_inicio' => '2026-01-15',
        ]
    )
        ->assertCreated()
        ->assertJsonPath('data.duracao_meses', 9);

    expect(
        Estagio::where('id_candidatura', $candidatura->id_candidatura)
            ->firstOrFail()
            ->data_fim_prevista
            ->toDateString()
    )->toBe('2026-10-15');
});
