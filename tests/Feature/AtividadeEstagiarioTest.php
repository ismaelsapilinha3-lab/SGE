<?php

use App\Models\AtividadeEstagiario;
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
function estagioComAtividades(): array
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
function estagioAtividadeDoCoordenador(): array
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

it('deixa o estagiário registar uma atividade', function () {
    [$estagio, $estagiario] = estagioComAtividades();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/atividades', [
            'data' => now()->toDateString(),
            'titulo' => 'Configuração do ambiente',
            'descricao' => 'Instalação das dependências e configuração do repositório.',
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Atividade registada com sucesso.');

    $atividade = $estagio->atividades()->first();

    expect($atividade)->not->toBeNull()
        ->and($atividade->titulo)->toBe('Configuração do ambiente');
});

it('exige os campos obrigatórios da atividade', function () {
    [, $estagiario] = estagioComAtividades();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/atividades', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['data', 'titulo', 'descricao']);
});

it('recusa uma atividade fora do período do estágio', function () {
    [$estagio, $estagiario] = estagioComAtividades();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/atividades', [
            'data' => $estagio->data_fim_prevista->copy()->addMonth()->toDateString(),
            'titulo' => 'Tarefa futura',
            'descricao' => 'Não deve ser aceite.',
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'A data indicada está fora do período do estágio.'
        );
});

it('não deixa registar atividades num estágio não ativo', function () {
    $estagiario = User::factory()->estagiario()->create();

    Estagio::factory()->concluido()->create([
        'id_estagiario' => $estagiario->id_utilizador,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/atividades', [
            'data' => now()->toDateString(),
            'titulo' => 'Tarefa',
            'descricao' => 'Descrição da tarefa.',
        ])
        ->assertStatus(422);
});

it('deixa o estagiário atualizar uma atividade', function () {
    [$estagio, $estagiario] = estagioComAtividades();

    $atividade = AtividadeEstagiario::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->putJson("/api/estagiario/estagio/atividades/{$atividade->id_atividade}", [
            'data' => now()->toDateString(),
            'titulo' => 'Título revisto',
            'descricao' => 'Descrição revista da atividade.',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Atividade atualizada com sucesso.')
        ->assertJsonPath('atividade.titulo', 'Título revisto');
});

it('deixa o estagiário remover uma atividade', function () {
    [$estagio, $estagiario] = estagioComAtividades();

    $atividade = AtividadeEstagiario::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->deleteJson("/api/estagiario/estagio/atividades/{$atividade->id_atividade}")
        ->assertOk()
        ->assertJsonPath('message', 'Atividade removida com sucesso.');

    expect(AtividadeEstagiario::find($atividade->id_atividade))->toBeNull();
});

it('não deixa outro estagiário alterar a atividade', function () {
    [$estagio] = estagioComAtividades();

    $atividade = AtividadeEstagiario::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $outroEstagiario = User::factory()->estagiario()->create();

    Estagio::factory()->create([
        'id_estagiario' => $outroEstagiario->id_utilizador,
    ]);

    $this->actingAs($outroEstagiario, 'sanctum')
        ->putJson("/api/estagiario/estagio/atividades/{$atividade->id_atividade}", [
            'data' => now()->toDateString(),
            'titulo' => 'Título revisto',
            'descricao' => 'Descrição revista da atividade.',
        ])
        ->assertStatus(404);
});

it('deixa o estagiário listar as suas atividades', function () {
    [$estagio, $estagiario] = estagioComAtividades();

    AtividadeEstagiario::factory()->count(2)->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/estagio/atividades')
        ->assertOk()
        ->assertJsonCount(2, 'atividades');
});

it('deixa o supervisor consultar as atividades do estágio', function () {
    [$estagio, , $supervisor] = estagioComAtividades();

    AtividadeEstagiario::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->getJson("/api/supervisor/estagios/{$estagio->id_estagio}/atividades")
        ->assertOk()
        ->assertJsonCount(1, 'atividades');
});

it('deixa o coordenador consultar as atividades da sua direção', function () {
    [$estagio, $coordenador] = estagioAtividadeDoCoordenador();

    AtividadeEstagiario::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->getJson("/api/coordenador/estagios/{$estagio->id_estagio}/atividades")
        ->assertOk()
        ->assertJsonCount(1, 'atividades');
});
