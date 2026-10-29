<?php

use App\Models\Estagio;
use App\Models\Notificacao;
use App\Models\Relatorio;
use App\Models\User;

/**
 * Relatório mensal de um estágio ativo.
 *
 * @param  array<string, mixed>  $atributos
 * @return array{0: Relatorio, 1: Estagio, 2: User}
 */
function relatorioParaAdministracao(array $atributos = []): array
{
    $estagiario = User::factory()->estagiario()->create([
        'nome' => 'Ana Silva',
        'email' => fake()->unique()->safeEmail(),
    ]);

    $estagio = Estagio::factory()->create([
        'id_estagiario' => $estagiario->id_utilizador,
    ]);

    $relatorio = Relatorio::factory()->create(array_merge([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
        'mes_referencia' => '2026-09-01',
        'periodo_referencia' => '2026-09-01',
        'prazo_envio' => '2026-09-05',
        'enviado_em' => now(),
    ], $atributos));

    return [$relatorio, $estagio, $estagiario];
}

it('lista os relatórios e filtra por estado', function () {
    $administrador = User::factory()->administrador()->create();

    [$aprovado] = relatorioParaAdministracao([
        'tipo' => 'mensal',
        'estado_avaliacao' => Relatorio::ESTADO_APROVADO,
    ]);

    [$pendente] = relatorioParaAdministracao([
        'tipo' => 'semanal',
        'estado_avaliacao' => Relatorio::ESTADO_PENDENTE,
    ]);

    $this->actingAs($administrador, 'sanctum')
        ->getJson('/api/admin/relatorios?estado=aprovado')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id_relatorio', $aprovado->id_relatorio)
        ->assertJsonPath('data.0.estado_avaliacao', Relatorio::ESTADO_APROVADO);

    expect($pendente->id_relatorio)->not->toBe($aprovado->id_relatorio);
});

it('pesquisa relatórios pelo nome do estagiário', function () {
    $administrador = User::factory()->administrador()->create();

    relatorioParaAdministracao();

    $outroEstagiario = User::factory()->estagiario()->create([
        'nome' => 'Bruno Costa',
        'email' => 'bruno.costa@example.com',
    ]);

    Relatorio::factory()->create([
        'id_estagio' => Estagio::factory()->create([
            'id_estagiario' => $outroEstagiario->id_utilizador,
        ])->id_estagio,
        'tipo' => 'semanal',
    ]);

    $this->actingAs($administrador, 'sanctum')
        ->getJson('/api/admin/relatorios?pesquisa=Ana')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.estagio.estagiario.nome', 'Ana Silva');
});

it('lista apenas os relatórios em atraso ainda não entregues', function () {
    $administrador = User::factory()->administrador()->create();

    [$emAtraso] = relatorioParaAdministracao([
        'enviado_em' => null,
        'entrega_confirmada_em' => null,
    ]);

    [$entregue] = relatorioParaAdministracao([
        'enviado_em' => now(),
    ]);

    $this->actingAs($administrador, 'sanctum')
        ->getJson('/api/admin/relatorios?estado=em_atraso')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id_relatorio', $emAtraso->id_relatorio);

    $this->actingAs($administrador, 'sanctum')
        ->getJson('/api/admin/relatorios/atraso')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id_relatorio', $emAtraso->id_relatorio);

    expect($entregue->entregue())->toBeTrue();
});

it('aprova um relatório', function () {
    $administrador = User::factory()->administrador()->create();

    [$relatorio] = relatorioParaAdministracao();

    $this->actingAs($administrador, 'sanctum')
        ->patchJson(
            "/api/admin/relatorios/{$relatorio->id_relatorio}/avaliacao",
            ['estado_avaliacao' => 'aprovado']
        )
        ->assertOk()
        ->assertJsonPath('data.estado_avaliacao', 'aprovado');

    expect($relatorio->fresh()->estado_avaliacao)
        ->toBe(Relatorio::ESTADO_APROVADO);
});

it('não avalia um relatório que ainda não foi entregue', function () {
    $administrador = User::factory()->administrador()->create();

    [$relatorio] = relatorioParaAdministracao([
        'enviado_em' => null,
        'entrega_confirmada_em' => null,
    ]);

    $this->actingAs($administrador, 'sanctum')
        ->patchJson(
            "/api/admin/relatorios/{$relatorio->id_relatorio}/avaliacao",
            ['estado_avaliacao' => 'aprovado']
        )
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Só é possível avaliar relatórios já entregues pelo estagiário.'
        );

    expect($relatorio->fresh()->estado_avaliacao)
        ->toBe(Relatorio::ESTADO_PENDENTE);
});

it('exige justificação para recusar e notifica o estagiário', function () {
    $administrador = User::factory()->administrador()->create();

    [$relatorio, , $estagiario] = relatorioParaAdministracao();

    $this->actingAs($administrador, 'sanctum')
        ->patchJson(
            "/api/admin/relatorios/{$relatorio->id_relatorio}/avaliacao",
            ['estado_avaliacao' => 'rejeitado']
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors('comentario_admin');

    $this->actingAs($administrador, 'sanctum')
        ->patchJson(
            "/api/admin/relatorios/{$relatorio->id_relatorio}/avaliacao",
            [
                'estado_avaliacao' => 'rejeitado',
                'comentario_admin' => 'Faltam os resultados do mês.',
            ]
        )
        ->assertOk()
        ->assertJsonPath('data.estado_avaliacao', 'rejeitado')
        ->assertJsonPath(
            'data.comentario_admin',
            'Faltam os resultados do mês.'
        );

    expect($relatorio->fresh()->estado_avaliacao)
        ->toBe(Relatorio::ESTADO_REJEITADO)
        ->and(
            Notificacao::query()
                ->where('id_utilizador', $estagiario->id_utilizador)
                ->where('titulo', 'Relatório recusado')
                ->exists()
        )->toBeTrue()
        ->and(
            Notificacao::query()
                ->where('id_utilizador', $estagiario->id_utilizador)
                ->where('titulo', 'Relatório recusado')
                ->latest('id_notificacao')
                ->value('url')
        )->toBe('/area-reservada?separador=relatorios');
});

it('não deixa um não administrador consultar os relatórios', function () {
    $estagiario = User::factory()->estagiario()->create();

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/admin/relatorios')
        ->assertStatus(403);
});
