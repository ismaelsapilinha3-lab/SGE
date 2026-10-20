<?php

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Estagio;
use App\Models\HistoricoEstado;
use App\Models\Perfil;
use App\Models\PlanoEstagio;
use App\Models\User;

/**
 * Estágio ativo atribuído a um supervisor.
 *
 * @return array{0: Estagio, 1: User}
 */
function planoEstagioDoSupervisor(): array
{
    $supervisor = User::factory()->supervisor()->create();

    $estagio = Estagio::factory()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    return [$estagio, $supervisor];
}

/**
 * Estágio cuja candidatura pertence a uma direção com coordenador.
 *
 * @return array{0: Estagio, 1: User, 2: Departamento}
 */
function planoEstagioDoCoordenador(): array
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

    return [$estagio, $coordenador, $departamento];
}

/**
 * @return array<string, string>
 */
function dadosPlanoValido(): array
{
    return [
        'objetivos' => 'Aprender a desenvolver aplicações web.',
        'atividades_previstas' => 'Desenvolvimento de módulos e testes.',
        'cronograma' => 'Mês 1: formação; Meses 2-5: desenvolvimento.',
        'data_inicio' => '2026-01-05',
        'data_fim' => '2026-06-05',
    ];
}

it('submete um plano de estágio num estágio atribuído', function () {
    [$estagio, $supervisor] = planoEstagioDoSupervisor();

    $this->actingAs($supervisor, 'sanctum')
        ->putJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/plano",
            dadosPlanoValido()
        )
        ->assertCreated()
        ->assertJsonPath(
            'message',
            'Plano de estágio submetido com sucesso.'
        );

    $plano = $estagio->planoVigente();

    expect($plano)->not->toBeNull()
        ->and($plano->estado)->toBe('submetido')
        ->and($plano->id_supervisor)->toBe($supervisor->id_utilizador);
});

it('não submete o plano de um estágio não atribuído', function () {
    $supervisor = User::factory()->supervisor()->create();

    $estagio = Estagio::factory()->create();

    $this->actingAs($supervisor, 'sanctum')
        ->putJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/plano",
            dadosPlanoValido()
        )
        ->assertStatus(404);
});

it('não submete o plano de um estágio não ativo', function () {
    $supervisor = User::factory()->supervisor()->create();

    $estagio = Estagio::factory()->suspenso()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->putJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/plano",
            dadosPlanoValido()
        )
        ->assertStatus(422);
});

it('exige que a data de fim não seja anterior à de início', function () {
    [$estagio, $supervisor] = planoEstagioDoSupervisor();

    $this->actingAs($supervisor, 'sanctum')
        ->putJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/plano",
            array_merge(dadosPlanoValido(), [
                'data_inicio' => '2026-06-05',
                'data_fim' => '2026-01-05',
            ])
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors('data_fim');
});

it('devolve o plano e o histórico ao supervisor', function () {
    [$estagio, $supervisor] = planoEstagioDoSupervisor();

    $plano = PlanoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'id_supervisor' => $supervisor->id_utilizador,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->getJson("/api/supervisor/estagios/{$estagio->id_estagio}/plano")
        ->assertOk()
        ->assertJsonPath('plano.id_plano_estagio', $plano->id_plano_estagio);
});

it('aprova o plano submetido e regista o histórico de estados', function () {
    [$estagio, $coordenador] = planoEstagioDoCoordenador();

    $plano = PlanoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'estado' => 'submetido',
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/estagios/{$estagio->id_estagio}/plano/aprovar"
        )
        ->assertOk()
        ->assertJsonPath(
            'message',
            'Plano de estágio aprovado com sucesso.'
        );

    $plano->refresh();

    expect($plano->estado)->toBe('aprovado')
        ->and($plano->id_coordenador)->toBe($coordenador->id_utilizador)
        ->and($plano->data_decisao)->not->toBeNull()
        ->and(
            HistoricoEstado::where(
                'id_plano_estagio',
                $plano->id_plano_estagio
            )->count()
        )->toBe(1);
});

it('recusa o plano submetido com um motivo', function () {
    [$estagio, $coordenador] = planoEstagioDoCoordenador();

    $plano = PlanoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'estado' => 'submetido',
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/estagios/{$estagio->id_estagio}/plano/recusar",
            ['motivo' => 'Os objetivos estão pouco claros.']
        )
        ->assertOk()
        ->assertJsonPath('message', 'Plano de estágio recusado.');

    $plano->refresh();

    expect($plano->estado)->toBe('recusado')
        ->and($plano->motivo_recusa)->toBe('Os objetivos estão pouco claros.');
});

it('não decide o plano de um estágio terminado', function () {
    [$estagio, $coordenador] = planoEstagioDoCoordenador();

    $plano = PlanoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'estado' => 'submetido',
    ]);

    $estagio->update(['estado' => 'concluido']);

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/estagios/{$estagio->id_estagio}/plano/aprovar"
        )
        ->assertStatus(422);

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/estagios/{$estagio->id_estagio}/plano/recusar",
            ['motivo' => 'Os objetivos estão pouco claros.']
        )
        ->assertStatus(422);

    expect($plano->fresh()->estado)->toBe('submetido');
});

it('não decide quando não há plano submetido', function () {
    [$estagio, $coordenador] = planoEstagioDoCoordenador();

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/estagios/{$estagio->id_estagio}/plano/aprovar"
        )
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Não há plano de estágio submetido para aprovar.'
        );
});

it('guarda o histórico quando o supervisor reenvia um plano recusado', function () {
    [$estagio, $supervisor] = planoEstagioDoSupervisor();

    $plano = PlanoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'id_supervisor' => $supervisor->id_utilizador,
        'estado' => 'recusado',
        'motivo_recusa' => 'Os objetivos estão pouco claros.',
        'objetivos' => 'Versão antiga dos objetivos.',
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->putJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/plano",
            dadosPlanoValido()
        )
        ->assertOk();

    $plano->refresh();

    expect($plano->estado)->toBe('submetido')
        ->and($plano->motivo_recusa)->toBeNull()
        ->and($plano->objetivos)->toBe('Aprender a desenvolver aplicações web.')
        ->and($plano->id_coordenador)->toBeNull()
        ->and($plano->historico()->count())->toBe(1)
        ->and($plano->historico()->first()->objetivos)
        ->toBe('Versão antiga dos objetivos.');
});

it('cria um novo plano quando o anterior já foi aprovado', function () {
    [$estagio, $supervisor] = planoEstagioDoSupervisor();

    $antigo = PlanoEstagio::factory()->aprovado()->create([
        'id_estagio' => $estagio->id_estagio,
        'id_supervisor' => $supervisor->id_utilizador,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->putJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/plano",
            dadosPlanoValido()
        )
        ->assertCreated();

    expect($antigo->fresh()->estado)->toBe('substituido')
        ->and($estagio->planos()->count())->toBe(2);

    $novo = $estagio->planoVigente();

    expect($novo->id_plano_estagio)->not->toBe($antigo->id_plano_estagio)
        ->and($novo->estado)->toBe('submetido');
});

it('esconde o plano de um estágio de outra direção', function () {
    [$estagio] = planoEstagioDoCoordenador();

    $outraDirecao = Departamento::factory()->create();

    $outroCoordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $outroCoordenador->id_utilizador,
        'id_departamento' => $outraDirecao->id_departamento,
    ]);

    $this->actingAs($outroCoordenador, 'sanctum')
        ->getJson("/api/coordenador/estagios/{$estagio->id_estagio}/plano")
        ->assertStatus(404);
});

it('recusa o acesso de quem não é supervisor', function () {
    $estagiario = User::factory()->estagiario()->create();

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/supervisor/estagios')
        ->assertStatus(403);
});
