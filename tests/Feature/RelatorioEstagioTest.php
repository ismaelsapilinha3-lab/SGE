<?php

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Estagio;
use App\Models\Perfil;
use App\Models\Relatorio;
use App\Models\User;

/**
 * Estágio ativo com estagiário e supervisor atribuído.
 *
 * @return array{0: Estagio, 1: User, 2: User}
 */
function estagioComRelatorios(): array
{
    $estagiario = User::factory()->estagiario()->create();

    $estagio = Estagio::factory()->create([
        'id_estagiario' => $estagiario->id_utilizador,
    ]);

    $supervisor = User::factory()->supervisor()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    return [$estagio, $estagiario, $supervisor];
}

/**
 * Estágio cuja candidatura pertence à direção do coordenador devolvido.
 *
 * @return array{0: Estagio, 1: User}
 */
function estagioRelatorioDoCoordenador(): array
{
    $departamento = Departamento::factory()->create();

    $coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $coordenador->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $candidatura = Candidatura::factory()->aprovada()->create([
        'id_coordenador' => $coordenador->id_utilizador,
        'id_departamento_selecionado' => $departamento->id_departamento,
    ]);

    $estagio = Estagio::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
    ]);

    return [$estagio, $coordenador];
}

/**
 * @return array<string, string>
 */
function dadosRelatorio(): array
{
    return [
        'tipo' => 'mensal',
        'periodo_referencia' => '2026-03-01',
        'atividades' => 'Desenvolvimento de funcionalidades.',
        'resultados' => 'Conclusão do módulo de relatórios.',
        'constrangimentos' => 'Falta de documentação.',
        'objetivos' => 'Consolidar os conhecimentos adquiridos.',
    ];
}

it('deixa o estagiário criar um relatório', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', dadosRelatorio())
        ->assertCreated()
        ->assertJsonPath('message', 'Relatório criado com sucesso.');

    $relatorio = $estagio->relatorios()->first();

    expect($relatorio)->not->toBeNull()
        ->and($relatorio->tipo)->toBe('mensal')
        ->and($relatorio->submetido_em)->toBeNull();
});

it('não cria dois relatórios do mesmo tipo para o mesmo período', function () {
    [, $estagiario] = estagioComRelatorios();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', dadosRelatorio())
        ->assertCreated();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', dadosRelatorio())
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Já existe um relatório deste tipo para este período.'
        );
});

it('exige um tipo de relatório válido', function () {
    [, $estagiario] = estagioComRelatorios();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson(
            '/api/estagiario/estagio/relatorios',
            array_merge(dadosRelatorio(), ['tipo' => 'diario'])
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors('tipo');
});

it('não cria relatórios num estágio não ativo', function () {
    $estagiario = User::factory()->estagiario()->create();

    Estagio::factory()->concluido()->create([
        'id_estagiario' => $estagiario->id_utilizador,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', dadosRelatorio())
        ->assertStatus(422);
});

it('deixa o estagiário atualizar um rascunho', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->putJson(
            "/api/estagiario/estagio/relatorios/{$relatorio->id_relatorio}",
            array_merge(dadosRelatorio(), [
                'atividades' => 'Atividades atualizadas.',
            ])
        )
        ->assertOk()
        ->assertJsonPath('message', 'Relatório atualizado com sucesso.');

    expect($relatorio->fresh()->atividades)->toBe('Atividades atualizadas.');
});

it('deixa o estagiário submeter um relatório', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson(
            "/api/estagiario/estagio/relatorios/{$relatorio->id_relatorio}/submeter"
        )
        ->assertOk()
        ->assertJsonPath('message', 'Relatório submetido com sucesso.');

    expect($relatorio->fresh()->submetido_em)->not->toBeNull();
});

it('não altera um relatório já submetido', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->submetido()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->putJson(
            "/api/estagiario/estagio/relatorios/{$relatorio->id_relatorio}",
            dadosRelatorio()
        )
        ->assertStatus(422);
});

it('não deixa um estagiário alterar o relatório de outro', function () {
    [$estagio] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $outroEstagiario = User::factory()->estagiario()->create();

    Estagio::factory()->create([
        'id_estagiario' => $outroEstagiario->id_utilizador,
    ]);

    $this->actingAs($outroEstagiario, 'sanctum')
        ->putJson(
            "/api/estagiario/estagio/relatorios/{$relatorio->id_relatorio}",
            dadosRelatorio()
        )
        ->assertStatus(404);
});

it('mostra ao supervisor apenas os relatórios submetidos', function () {
    [$estagio, $estagiario, $supervisor] = estagioComRelatorios();

    Relatorio::factory()->submetido()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
    ]);

    Relatorio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'semanal',
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->getJson("/api/supervisor/estagios/{$estagio->id_estagio}/relatorios")
        ->assertOk()
        ->assertJsonCount(1, 'relatorios');
});

it('deixa o coordenador consultar os relatórios da sua direção', function () {
    [$estagio, $coordenador] = estagioRelatorioDoCoordenador();

    Relatorio::factory()->submetido()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->getJson("/api/coordenador/estagios/{$estagio->id_estagio}/relatorios")
        ->assertOk()
        ->assertJsonCount(1, 'relatorios');
});
