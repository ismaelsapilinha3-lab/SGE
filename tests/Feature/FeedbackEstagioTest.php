<?php

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Estagio;
use App\Models\FeedbackEstagio;
use App\Models\Perfil;
use App\Models\User;

/**
 * Estágio ativo com estagiário e supervisor atribuído.
 *
 * @return array{0: Estagio, 1: User, 2: User}
 */
function estagioComFeedback(): array
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
function estagioFeedbackDoCoordenador(): array
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

it('deixa o estagiário dar feedback sobre o estágio', function () {
    [$estagio, $estagiario] = estagioComFeedback();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/feedback', [
            'nota_geral' => 8,
            'comentario' => 'Estágio muito enriquecedor.',
            'sugestoes' => 'Mais acompanhamento na fase inicial.',
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Feedback registado com sucesso.');

    $feedback = $estagio->feedback()->first();

    expect($feedback)->not->toBeNull()
        ->and((int) $feedback->nota_geral)->toBe(8)
        ->and($feedback->comentario)->toBe('Estágio muito enriquecedor.')
        ->and($feedback->sugestoes)->toBe('Mais acompanhamento na fase inicial.');
});

it('atualiza o feedback quando já existe', function () {
    [$estagio, $estagiario] = estagioComFeedback();

    FeedbackEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'nota_geral' => 5,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/feedback', [
            'nota_geral' => 9,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Feedback atualizado com sucesso.');

    expect($estagio->feedback()->count())->toBe(1)
        ->and((int) $estagio->feedback()->first()->nota_geral)->toBe(9);
});

it('valida a nota geral do feedback', function () {
    [, $estagiario] = estagioComFeedback();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/feedback', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nota_geral');

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/feedback', ['nota_geral' => 11])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nota_geral');
});

it('devolve feedback nulo quando ainda não existe', function () {
    [, $estagiario] = estagioComFeedback();

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/estagio/feedback')
        ->assertOk()
        ->assertJsonPath('feedback', null);
});

it('deixa dar feedback a um estágio já concluído', function () {
    $estagiario = User::factory()->estagiario()->create();

    Estagio::factory()->concluido()->create([
        'id_estagiario' => $estagiario->id_utilizador,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/feedback', ['nota_geral' => 7])
        ->assertCreated();
});

it('recusa feedback num estágio cancelado', function () {
    $estagiario = User::factory()->estagiario()->create();

    Estagio::factory()->cancelado()->create([
        'id_estagiario' => $estagiario->id_utilizador,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/feedback', ['nota_geral' => 7])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Não é possível avaliar um estágio cancelado.'
        );
});

it('deixa o supervisor consultar o feedback do estágio', function () {
    [$estagio, , $supervisor] = estagioComFeedback();

    FeedbackEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'nota_geral' => 6,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->getJson("/api/supervisor/estagios/{$estagio->id_estagio}/feedback")
        ->assertOk()
        ->assertJsonPath('feedback.nota_geral', 6);
});

it('deixa o coordenador consultar o feedback da sua direção', function () {
    [$estagio, $coordenador] = estagioFeedbackDoCoordenador();

    FeedbackEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'nota_geral' => 6,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->getJson("/api/coordenador/estagios/{$estagio->id_estagio}/feedback")
        ->assertOk()
        ->assertJsonPath('feedback.nota_geral', 6);
});
