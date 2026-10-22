<?php

use App\Models\Assiduidade;
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
function estagioComAssiduidade(): array
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
function estagioAssiduidadeDoCoordenador(): array
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

it('deixa o supervisor registar a assiduidade de um dia', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades", [
            'data' => now()->toDateString(),
            'estado' => 'presente',
            'hora_entrada' => '08:00',
            'hora_saida' => '17:00',
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Assiduidade registada com sucesso.');

    $assiduidade = $estagio->assiduidades()->first();

    expect($assiduidade)->not->toBeNull()
        ->and($assiduidade->estado)->toBe('presente')
        ->and((int) $assiduidade->id_supervisor)
        ->toBe((int) $supervisor->id_utilizador);
});

it('atualiza o registo quando o dia já tem assiduidade', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    $dados = [
        'data' => now()->toDateString(),
        'estado' => 'presente',
        'hora_entrada' => '08:00',
        'hora_saida' => '17:00',
    ];

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades", $dados)
        ->assertCreated();

    $this->actingAs($supervisor, 'sanctum')
        ->postJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades",
            array_merge($dados, ['estado' => 'atraso', 'hora_entrada' => '09:30'])
        )
        ->assertOk()
        ->assertJsonPath('message', 'Assiduidade atualizada com sucesso.');

    expect($estagio->assiduidades()->count())->toBe(1)
        ->and($estagio->assiduidades()->first()->estado)->toBe('atraso');
});

it('exige justificação numa falta justificada', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades", [
            'data' => now()->toDateString(),
            'estado' => 'falta_justificada',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('justificacao');
});

it('limpa as horas e guarda a justificação numa falta justificada', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades", [
            'data' => now()->toDateString(),
            'estado' => 'falta_justificada',
            'hora_entrada' => '08:00',
            'hora_saida' => '17:00',
            'justificacao' => 'Consulta médica.',
        ])
        ->assertCreated();

    $assiduidade = $estagio->assiduidades()->first();

    expect($assiduidade->estado)->toBe('falta_justificada')
        ->and($assiduidade->hora_entrada)->toBeNull()
        ->and($assiduidade->hora_saida)->toBeNull()
        ->and($assiduidade->justificacao)->toBe('Consulta médica.');
});

it('não regista assiduidade num estágio não ativo', function () {
    $supervisor = User::factory()->supervisor()->create();

    $estagio = Estagio::factory()->concluido()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades", [
            'data' => now()->toDateString(),
            'estado' => 'presente',
        ])
        ->assertStatus(422);
});

it('recusa uma data fora do período do estágio', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades", [
            'data' => $estagio->data_inicio->copy()->subMonth()->toDateString(),
            'estado' => 'presente',
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'A data indicada está fora do período do estágio.'
        );
});

it('não registra assiduidade num estágio não atribuído', function () {
    $supervisor = User::factory()->supervisor()->create();

    $estagio = Estagio::factory()->create();

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades", [
            'data' => now()->toDateString(),
            'estado' => 'presente',
        ])
        ->assertStatus(404);
});

it('lista a assiduidade com resumo e filtro por mês', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    Assiduidade::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'data' => '2026-03-10',
        'estado' => 'presente',
    ]);

    Assiduidade::factory()->falta()->create([
        'id_estagio' => $estagio->id_estagio,
        'data' => '2026-04-15',
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->getJson("/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades?mes=2026-03")
        ->assertOk()
        ->assertJsonCount(1, 'assiduidades')
        ->assertJsonPath('resumo.total', 2)
        ->assertJsonPath('resumo.presente', 1)
        ->assertJsonPath('resumo.falta', 1);
});

it('deixa o supervisor remover um registo de assiduidade', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    $assiduidade = Assiduidade::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->deleteJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades/{$assiduidade->id_assiduidade}"
        )
        ->assertOk()
        ->assertJsonPath('message', 'Registo de assiduidade removido com sucesso.');

    expect(Assiduidade::find($assiduidade->id_assiduidade))->toBeNull();
});

it('deixa o estagiário consultar a sua assiduidade', function () {
    [$estagio, $estagiario] = estagioComAssiduidade();

    Assiduidade::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/estagio/assiduidades')
        ->assertOk()
        ->assertJsonCount(1, 'assiduidades');
});

it('deixa o coordenador consultar a assiduidade da sua direção', function () {
    [$estagio, $coordenador] = estagioAssiduidadeDoCoordenador();

    Assiduidade::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->getJson("/api/coordenador/estagios/{$estagio->id_estagio}/assiduidades")
        ->assertOk()
        ->assertJsonCount(1, 'assiduidades');
});

it('deixa o estagiário justificar uma falta', function () {
    [$estagio, $estagiario] = estagioComAssiduidade();

    $assiduidade = Assiduidade::factory()->falta()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson(
            "/api/estagiario/estagio/assiduidades/{$assiduidade->id_assiduidade}/justificar",
            ['justificacao' => 'Consulta médica no hospital central.']
        )
        ->assertOk()
        ->assertJsonPath('message', 'Justificação submetida para validação.');

    $assiduidade->refresh();

    expect($assiduidade->justificacao_estado)->toBe('pendente')
        ->and($assiduidade->estado)->toBe('falta')
        ->and($assiduidade->justificacao)
        ->toBe('Consulta médica no hospital central.')
        ->and($assiduidade->justificacao_submetida_em)->not->toBeNull();
});

it('não deixa justificar uma presença', function () {
    [$estagio, $estagiario] = estagioComAssiduidade();

    $assiduidade = Assiduidade::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson(
            "/api/estagiario/estagio/assiduidades/{$assiduidade->id_assiduidade}/justificar",
            ['justificacao' => 'Texto da justificação.']
        )
        ->assertStatus(422)
        ->assertJsonPath('message', 'Esta falta não pode ser justificada.');
});

it('não aceita uma segunda justificação enquanto pendente', function () {
    [$estagio, $estagiario] = estagioComAssiduidade();

    $assiduidade = Assiduidade::factory()->justificacaoPendente()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson(
            "/api/estagiario/estagio/assiduidades/{$assiduidade->id_assiduidade}/justificar",
            ['justificacao' => 'Outra justificação diferente.']
        )
        ->assertStatus(422);
});

it('deixa resubmeter a justificação depois de uma recusa', function () {
    [$estagio, $estagiario] = estagioComAssiduidade();

    $assiduidade = Assiduidade::factory()->justificacaoRecusada()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson(
            "/api/estagiario/estagio/assiduidades/{$assiduidade->id_assiduidade}/justificar",
            ['justificacao' => 'Justificação corrigida e completa.']
        )
        ->assertOk();

    $assiduidade->refresh();

    expect($assiduidade->justificacao_estado)->toBe('pendente')
        ->and($assiduidade->motivo_recusa)->toBeNull()
        ->and($assiduidade->justificacao_decidida_em)->toBeNull();
});

it('não deixa justificar a falta de outro estagiário', function () {
    [$estagio] = estagioComAssiduidade();

    $assiduidade = Assiduidade::factory()->falta()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $outroEstagiario = User::factory()->estagiario()->create();

    Estagio::factory()->create([
        'id_estagiario' => $outroEstagiario->id_utilizador,
    ]);

    $this->actingAs($outroEstagiario, 'sanctum')
        ->postJson(
            "/api/estagiario/estagio/assiduidades/{$assiduidade->id_assiduidade}/justificar",
            ['justificacao' => 'Texto da justificação.']
        )
        ->assertStatus(404);
});

it('não justifica faltas de um estágio não ativo', function () {
    $estagiario = User::factory()->estagiario()->create();

    $estagio = Estagio::factory()->concluido()->create([
        'id_estagiario' => $estagiario->id_utilizador,
    ]);

    $assiduidade = Assiduidade::factory()->falta()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson(
            "/api/estagiario/estagio/assiduidades/{$assiduidade->id_assiduidade}/justificar",
            ['justificacao' => 'Texto da justificação.']
        )
        ->assertStatus(422);
});

it('deixa o supervisor aprovar a justificação', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    $assiduidade = Assiduidade::factory()->justificacaoPendente()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades/"
            ."{$assiduidade->id_assiduidade}/justificacao/aprovar"
        )
        ->assertOk()
        ->assertJsonPath('message', 'Justificação aprovada com sucesso.');

    $assiduidade->refresh();

    expect($assiduidade->estado)->toBe('falta_justificada')
        ->and($assiduidade->justificacao_estado)->toBe('aprovada')
        ->and((int) $assiduidade->id_supervisor_justificacao)
        ->toBe((int) $supervisor->id_utilizador)
        ->and($assiduidade->justificacao_decidida_em)->not->toBeNull();
});

it('deixa o supervisor recusar a justificação com motivo', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    $assiduidade = Assiduidade::factory()->justificacaoPendente()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades/"
            ."{$assiduidade->id_assiduidade}/justificacao/recusar",
            ['motivo' => 'Justificação sem comprovativo.']
        )
        ->assertOk()
        ->assertJsonPath('message', 'Justificação recusada.');

    $assiduidade->refresh();

    expect($assiduidade->estado)->toBe('falta')
        ->and($assiduidade->justificacao_estado)->toBe('recusada')
        ->and($assiduidade->motivo_recusa)
        ->toBe('Justificação sem comprovativo.');
});

it('exige motivo para recusar a justificação', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    $assiduidade = Assiduidade::factory()->justificacaoPendente()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades/"
            ."{$assiduidade->id_assiduidade}/justificacao/recusar",
            []
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors('motivo');
});

it('não aprova quando não há justificação pendente', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    $assiduidade = Assiduidade::factory()->falta()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades/"
            ."{$assiduidade->id_assiduidade}/justificacao/aprovar"
        )
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Não há justificação pendente para aprovar.'
        );
});

it('não deixa decidir justificação de um estágio não atribuído', function () {
    [$estagio] = estagioComAssiduidade();

    $assiduidade = Assiduidade::factory()->justificacaoPendente()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $outroSupervisor = User::factory()->supervisor()->create();

    $this->actingAs($outroSupervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades/"
            ."{$assiduidade->id_assiduidade}/justificacao/aprovar"
        )
        ->assertStatus(404);
});

it('conta e filtra as justificações pendentes', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    Assiduidade::factory()->justificacaoPendente()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    Assiduidade::factory()->faltaJustificada()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->getJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades"
            .'?justificacao_estado=pendente'
        )
        ->assertOk()
        ->assertJsonCount(1, 'assiduidades')
        ->assertJsonPath('resumo.justificacoes_pendentes', 1);
});

it('reinicia a justificação quando o supervisor volta a lançar o dia', function () {
    [$estagio, , $supervisor] = estagioComAssiduidade();

    $assiduidade = Assiduidade::factory()->justificacaoPendente()->create([
        'id_estagio' => $estagio->id_estagio,
        'data' => now()->toDateString(),
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->postJson("/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades", [
            'data' => now()->toDateString(),
            'estado' => 'falta',
        ])
        ->assertOk();

    $assiduidade->refresh();

    expect($assiduidade->justificacao_estado)->toBeNull()
        ->and($assiduidade->justificacao)->toBeNull();
});
