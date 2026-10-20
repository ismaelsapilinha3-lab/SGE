<?php

use App\Models\Avaliacao;
use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Estagio;
use App\Models\Perfil;
use App\Models\User;

/**
 * Estágio ativo com estagiário e supervisor atribuído.
 *
 * @return array{0: Estagio, 1: User, 2: User}
 */
function estagioComAvaliacoes(): array
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
function estagioAvaliacaoDoCoordenador(): array
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

it('deixa o supervisor registar uma avaliação', function () {
    [$estagio, , $supervisor] = estagioComAvaliacoes();

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/avaliacoes", [
            'tipo' => 'mensal',
            'periodo_referencia' => '2026-10-01',
            'nota' => 16.5,
            'nota_atividades' => 17,
            'nota_assiduidade' => 16,
            'nota_pontualidade' => 18,
            'comentarios' => 'Bom progresso ao longo do mês.',
            'pontos_fortes' => 'Proatividade.',
            'areas_melhoria' => 'Gestão de tempo.',
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Avaliação registada com sucesso.');

    $avaliacao = $estagio->avaliacoes()->first();

    expect($avaliacao)->not->toBeNull()
        ->and((float) $avaliacao->nota)->toBe(16.5)
        ->and($avaliacao->pontos_fortes)->toBe('Proatividade.')
        ->and((int) $avaliacao->id_supervisor)
        ->toBe((int) $supervisor->id_utilizador);
});

it('atualiza a avaliação do mesmo tipo e período', function () {
    [$estagio, , $supervisor] = estagioComAvaliacoes();

    $dados = [
        'tipo' => 'mensal',
        'periodo_referencia' => '2026-10-01',
        'nota' => 15,
    ];

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/avaliacoes", $dados)
        ->assertCreated();

    $this->actingAs($supervisor, 'sanctum')
        ->postJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/avaliacoes",
            array_merge($dados, ['nota' => 18])
        )
        ->assertOk()
        ->assertJsonPath('message', 'Avaliação atualizada com sucesso.');

    expect($estagio->avaliacoes()->count())->toBe(1)
        ->and((float) $estagio->avaliacoes()->first()->nota)->toBe(18.0);
});

it('não aceita pontos fortes numa avaliação semanal', function () {
    [$estagio, , $supervisor] = estagioComAvaliacoes();

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/avaliacoes", [
            'tipo' => 'semanal',
            'periodo_referencia' => '2026-10-05',
            'nota' => 16,
            'pontos_fortes' => 'Não aplicável a uma semana.',
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Os pontos fortes e as áreas de melhoria só se aplicam às avaliações mensal e final.'
        );
});

it('valida a nota da avaliação', function () {
    [$estagio, , $supervisor] = estagioComAvaliacoes();

    $url = "/api/supervisor/estagios/{$estagio->id_estagio}/avaliacoes";

    $this->actingAs($supervisor, 'sanctum')
        ->postJson($url, [
            'tipo' => 'mensal',
            'periodo_referencia' => '2026-10-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nota');

    $this->actingAs($supervisor, 'sanctum')
        ->postJson($url, [
            'tipo' => 'mensal',
            'periodo_referencia' => '2026-10-01',
            'nota' => 25,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nota');
});

it('lista as avaliações com resumo e filtro por tipo', function () {
    [$estagio, , $supervisor] = estagioComAvaliacoes();

    Avaliacao::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
        'periodo_referencia' => '2026-09-01',
        'nota' => 14,
    ]);

    Avaliacao::factory()->final()->create([
        'id_estagio' => $estagio->id_estagio,
        'periodo_referencia' => '2026-10-01',
        'nota' => 19,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->getJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/avaliacoes?tipo=final"
        )
        ->assertOk()
        ->assertJsonCount(1, 'avaliacoes')
        ->assertJsonPath('resumo.total', 2)
        ->assertJsonPath('resumo.final', 1)
        ->assertJsonPath('resumo.media_nota', 16.5);
});

it('deixa o supervisor remover uma avaliação', function () {
    [$estagio, , $supervisor] = estagioComAvaliacoes();

    $avaliacao = Avaliacao::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->deleteJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/avaliacoes/{$avaliacao->id_avaliacao}"
        )
        ->assertOk()
        ->assertJsonPath('message', 'Avaliação removida com sucesso.');

    expect(Avaliacao::find($avaliacao->id_avaliacao))->toBeNull();
});

it('não deixa avaliar um estágio não atribuído', function () {
    $supervisor = User::factory()->supervisor()->create();

    $estagio = Estagio::factory()->create();

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/avaliacoes", [
            'tipo' => 'mensal',
            'periodo_referencia' => '2026-10-01',
            'nota' => 16,
        ])
        ->assertStatus(404);
});

it('deixa avaliar um estágio já concluído', function () {
    $supervisor = User::factory()->supervisor()->create();

    $estagio = Estagio::factory()->concluido()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/avaliacoes", [
            'tipo' => 'final',
            'periodo_referencia' => now()->toDateString(),
            'nota' => 17.5,
            'pontos_fortes' => 'Boa autonomia.',
            'areas_melhoria' => 'Documentação.',
        ])
        ->assertCreated();
});

it('recusa avaliar um estágio cancelado', function () {
    $supervisor = User::factory()->supervisor()->create();

    $estagio = Estagio::factory()->cancelado()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/avaliacoes", [
            'tipo' => 'final',
            'periodo_referencia' => now()->toDateString(),
            'nota' => 17.5,
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Não é possível avaliar um estágio cancelado.'
        );
});

it('deixa o estagiário consultar as suas avaliações', function () {
    [$estagio, $estagiario] = estagioComAvaliacoes();

    Avaliacao::factory()->count(2)->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/estagio/avaliacoes')
        ->assertOk()
        ->assertJsonCount(2, 'avaliacoes');
});

it('deixa o coordenador consultar as avaliações da sua direção', function () {
    [$estagio, $coordenador] = estagioAvaliacaoDoCoordenador();

    Avaliacao::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->getJson("/api/coordenador/estagios/{$estagio->id_estagio}/avaliacoes")
        ->assertOk()
        ->assertJsonCount(1, 'avaliacoes');
});
