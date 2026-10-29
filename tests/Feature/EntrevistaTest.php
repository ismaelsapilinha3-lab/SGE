<?php

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Entrevista;
use App\Models\Notificacao;
use App\Models\Perfil;
use App\Models\User;

/**
 * Candidatura em análise com estagiário e coordenador da direção.
 *
 * @return array{0: Candidatura, 1: User, 2: User}
 */
function candidaturaParaEntrevista(): array
{
    $departamento = Departamento::factory()->create();

    $estagiario = User::factory()->estagiario()->create();

    $coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $coordenador->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $candidatura = Candidatura::factory()->create([
        'id_utilizador' => $estagiario->id_utilizador,
        'id_coordenador' => $coordenador->id_utilizador,
        'id_departamento_selecionado' => $departamento->id_departamento,
    ]);

    return [$candidatura, $estagiario, $coordenador];
}

/**
 * @return array{data_hora: string, id_supervisor_representante: int}
 */
function dadosEntrevista(User $supervisor): array
{
    return [
        'data_hora' => now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i:s'),
        'id_supervisor_representante' => $supervisor->id_utilizador,
    ];
}

it('permite ao administrador agendar uma entrevista', function () {
    [$candidatura, $estagiario] = candidaturaParaEntrevista();

    $administrador = User::factory()->administrador()->create();

    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($administrador, 'sanctum')
        ->postJson(
            "/api/admin/candidaturas/{$candidatura->id_candidatura}/entrevista",
            dadosEntrevista($supervisor)
        )
        ->assertCreated()
        ->assertJsonPath('entrevista.resultado', 'pendente')
        ->assertJsonPath(
            'entrevista.supervisor_representante.id_utilizador',
            $supervisor->id_utilizador
        );

    $this->assertDatabaseHas('entrevistas', [
        'id_candidatura' => $candidatura->id_candidatura,
        'id_administrador' => $administrador->id_utilizador,
        'id_supervisor_representante' => $supervisor->id_utilizador,
        'resultado' => 'pendente',
    ]);

    expect(
        Notificacao::where('id_utilizador', $estagiario->id_utilizador)
            ->where('titulo', 'Entrevista agendada')
            ->exists()
    )->toBeTrue();

    expect(
        Notificacao::where('id_utilizador', $supervisor->id_utilizador)
            ->where('titulo', 'Entrevista agendada')
            ->exists()
    )->toBeTrue();
});

it('recusa agendar entrevista para candidatura já decidida', function () {
    $administrador = User::factory()->administrador()->create();

    $supervisor = User::factory()->supervisor()->create();

    $candidatura = Candidatura::factory()->aprovada()->create();

    $this->actingAs($administrador, 'sanctum')
        ->postJson(
            "/api/admin/candidaturas/{$candidatura->id_candidatura}/entrevista",
            dadosEntrevista($supervisor)
        )
        ->assertStatus(422);
});

it('recusa um supervisor inativo como representante', function () {
    [$candidatura] = candidaturaParaEntrevista();

    $administrador = User::factory()->administrador()->create();

    $supervisor = User::factory()->supervisor()->inativo()->create();

    $this->actingAs($administrador, 'sanctum')
        ->postJson(
            "/api/admin/candidaturas/{$candidatura->id_candidatura}/entrevista",
            dadosEntrevista($supervisor)
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_supervisor_representante');
});

it('impede duplicar a entrevista da mesma candidatura', function () {
    [$candidatura] = candidaturaParaEntrevista();

    $administrador = User::factory()->administrador()->create();

    $supervisor = User::factory()->supervisor()->create();

    Entrevista::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
        'id_administrador' => $administrador->id_utilizador,
        'id_supervisor_representante' => $supervisor->id_utilizador,
    ]);

    $this->actingAs($administrador, 'sanctum')
        ->postJson(
            "/api/admin/candidaturas/{$candidatura->id_candidatura}/entrevista",
            dadosEntrevista($supervisor)
        )
        ->assertStatus(422);
});

it('impede um coordenador de agendar entrevistas', function () {
    [$candidatura, , $coordenador] = candidaturaParaEntrevista();

    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($coordenador, 'sanctum')
        ->postJson(
            "/api/admin/candidaturas/{$candidatura->id_candidatura}/entrevista",
            dadosEntrevista($supervisor)
        )
        ->assertStatus(403);
});

it('registra o resultado aprovado e avisa candidato e coordenador', function () {
    [$candidatura, $estagiario, $coordenador] = candidaturaParaEntrevista();

    $administrador = User::factory()->administrador()->create();

    $supervisor = User::factory()->supervisor()->create();

    $entrevista = Entrevista::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
        'id_administrador' => $administrador->id_utilizador,
        'id_supervisor_representante' => $supervisor->id_utilizador,
    ]);

    $this->actingAs($administrador, 'sanctum')
        ->patchJson(
            "/api/admin/entrevistas/{$entrevista->id_entrevista}/resultado",
            ['resultado' => 'aprovado']
        )
        ->assertOk()
        ->assertJsonPath('entrevista.resultado', 'aprovado')
        ->assertJsonPath('entrevista.motivo_reprovacao', null);

    expect(
        Notificacao::where('id_utilizador', $estagiario->id_utilizador)
            ->where('titulo', 'Entrevista aprovada')
            ->exists()
    )->toBeTrue();

    expect(
        Notificacao::where('id_utilizador', $coordenador->id_utilizador)
            ->where('titulo', 'Resultado de entrevista')
            ->exists()
    )->toBeTrue();
});

it('exige motivo ao reprovar a entrevista', function () {
    [$candidatura] = candidaturaParaEntrevista();

    $administrador = User::factory()->administrador()->create();

    $supervisor = User::factory()->supervisor()->create();

    $entrevista = Entrevista::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
        'id_administrador' => $administrador->id_utilizador,
        'id_supervisor_representante' => $supervisor->id_utilizador,
    ]);

    $this->actingAs($administrador, 'sanctum')
        ->patchJson(
            "/api/admin/entrevistas/{$entrevista->id_entrevista}/resultado",
            ['resultado' => 'reprovado']
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors('motivo_reprovacao');

    $this->actingAs($administrador, 'sanctum')
        ->patchJson(
            "/api/admin/entrevistas/{$entrevista->id_entrevista}/resultado",
            [
                'resultado' => 'reprovado',
                'motivo_reprovacao' => 'Falta de conhecimentos técnicos.',
            ]
        )
        ->assertOk()
        ->assertJsonPath('entrevista.resultado', 'reprovado');

    expect($entrevista->fresh()->motivo_reprovacao)
        ->toBe('Falta de conhecimentos técnicos.');
});

it('não permite alterar um resultado já registado', function () {
    [$candidatura] = candidaturaParaEntrevista();

    $administrador = User::factory()->administrador()->create();

    $entrevista = Entrevista::factory()->aprovada()->create([
        'id_candidatura' => $candidatura->id_candidatura,
        'id_administrador' => $administrador->id_utilizador,
    ]);

    $this->actingAs($administrador, 'sanctum')
        ->patchJson(
            "/api/admin/entrevistas/{$entrevista->id_entrevista}/resultado",
            ['resultado' => 'reprovado', 'motivo_reprovacao' => 'Outro motivo.']
        )
        ->assertStatus(422);
});

it('reagenda uma entrevista pendente e avisa o candidato', function () {
    [$candidatura, $estagiario] = candidaturaParaEntrevista();

    $administrador = User::factory()->administrador()->create();

    $supervisor = User::factory()->supervisor()->create();

    $entrevista = Entrevista::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
        'id_administrador' => $administrador->id_utilizador,
        'id_supervisor_representante' => $supervisor->id_utilizador,
    ]);

    $novaData = now()->addDays(5)->setTime(14, 30);

    $this->actingAs($administrador, 'sanctum')
        ->putJson(
            "/api/admin/entrevistas/{$entrevista->id_entrevista}",
            [
                'data_hora' => $novaData->format('Y-m-d H:i:s'),
                'id_supervisor_representante' => $supervisor->id_utilizador,
            ]
        )
        ->assertOk();

    expect($entrevista->fresh()->data_hora->format('Y-m-d H:i'))
        ->toBe($novaData->format('Y-m-d H:i'));

    expect(
        Notificacao::where('id_utilizador', $estagiario->id_utilizador)
            ->where('titulo', 'Entrevista reagendada')
            ->exists()
    )->toBeTrue();
});

it('mostra a entrevista ao candidato na sua candidatura', function () {
    [$candidatura, $estagiario] = candidaturaParaEntrevista();

    Entrevista::factory()->aprovada()->create([
        'id_candidatura' => $candidatura->id_candidatura,
    ]);

    $resposta = $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/candidatura')
        ->assertOk()
        ->assertJsonPath('entrevista.resultado', 'aprovado');

    expect($resposta->json('entrevista.supervisor_representante.nome'))
        ->toBeString();
});

it('mostra a entrevista ao coordenador nos detalhes da candidatura', function () {
    [$candidatura, , $coordenador] = candidaturaParaEntrevista();

    Entrevista::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->getJson("/api/coordenador/candidaturas/{$candidatura->id_candidatura}")
        ->assertOk()
        ->assertJsonPath('entrevista.resultado', 'pendente');
});

it('mostra ao supervisor as entrevistas em que representa a direção', function () {
    [$candidatura] = candidaturaParaEntrevista();

    $supervisor = User::factory()->supervisor()->create();

    Entrevista::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
        'id_supervisor_representante' => $supervisor->id_utilizador,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->getJson('/api/supervisor/dashboard')
        ->assertOk()
        ->assertJsonPath('resumo.entrevistas_pendentes', 1)
        ->assertJsonPath('entrevistas.0.nome_candidato', $candidatura->nome)
        ->assertJsonPath('entrevistas.0.resultado', 'pendente');
});

it('impede um estagiário de consultar a entrevista pela rota administrativa', function () {
    [$candidatura, $estagiario] = candidaturaParaEntrevista();

    $entrevista = Entrevista::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->getJson(
            "/api/admin/candidaturas/{$candidatura->id_candidatura}/entrevista"
        )
        ->assertStatus(403);

    expect($entrevista->id_entrevista)->toBeInt();
});

it('impede o coordenador de decidir enquanto a entrevista estiver pendente', function () {
    [$candidatura, , $coordenador] = candidaturaParaEntrevista();

    Entrevista::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/candidaturas/{$candidatura->id_candidatura}/aprovar"
        )
        ->assertStatus(422);

    expect($candidatura->fresh()->estado)->toBe('em_analise');
});

it('permite ao coordenador decidir depois de a entrevista estar avaliada', function () {
    [$candidatura, , $coordenador] = candidaturaParaEntrevista();

    Entrevista::factory()->aprovada()->create([
        'id_candidatura' => $candidatura->id_candidatura,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/candidaturas/{$candidatura->id_candidatura}/aprovar"
        )
        ->assertOk();

    expect($candidatura->fresh()->estado)->toBe('aprovada');
});
