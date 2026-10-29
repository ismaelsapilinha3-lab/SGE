<?php

use App\Models\Estagio;
use App\Models\HistoricoEstado;
use App\Models\Notificacao;
use App\Models\Relatorio;
use App\Models\User;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('conclui estágios cuja data prevista já passou', function () {
    Carbon::setTestNow('2026-10-26 10:00:00');

    $estagiario = User::factory()->estagiario()->create();

    $estagio = Estagio::factory()->create([
        'id_estagiario' => $estagiario->id_utilizador,
        'estado' => 'ativo',
        'data_fim_prevista' => '2026-10-25',
    ]);

    $supervisor = User::factory()->supervisor()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    $this->artisan('app:atualizar-estagios-concluidos')
        ->expectsOutput('Verificação concluída. Estágios atualizados: 1.')
        ->assertSuccessful();

    $estagio->refresh();

    expect($estagio->estado)->toBe('concluido')
        ->and($estagio->data_fim_real?->toDateString())->toBe('2026-10-25')
        ->and(
            HistoricoEstado::query()
                ->where('id_estagio', $estagio->id_estagio)
                ->where('estado_novo', 'concluido')
                ->count()
        )->toBe(1)
        ->and(
            Notificacao::query()
                ->where('id_utilizador', $estagiario->id_utilizador)
                ->where('titulo', 'Estágio concluído')
                ->exists()
        )->toBeTrue()
        ->and(
            Notificacao::query()
                ->where('id_utilizador', $supervisor->id_utilizador)
                ->where('titulo', 'Estágio concluído')
                ->exists()
        )->toBeTrue();
});

it('não volta a concluir nem a notificar um estágio já concluído', function () {
    Carbon::setTestNow('2026-10-26 10:00:00');

    $estagio = Estagio::factory()->concluido()->create([
        'data_fim_prevista' => '2026-10-25',
    ]);

    $this->artisan('app:atualizar-estagios-concluidos')
        ->expectsOutput('Verificação concluída. Estágios atualizados: 0.')
        ->assertSuccessful();

    expect($estagio->fresh()->estado)->toBe('concluido')
        ->and(
            Notificacao::query()
                ->where('titulo', 'Estágio concluído')
                ->count()
        )->toBe(0);
});

it('mantém ativos os estágios que ainda não terminaram', function () {
    Carbon::setTestNow('2026-10-26 10:00:00');

    $estagio = Estagio::factory()->create([
        'estado' => 'ativo',
        'data_fim_prevista' => '2026-10-27',
    ]);

    $this->artisan('app:atualizar-estagios-concluidos')
        ->assertSuccessful();

    expect($estagio->fresh()->estado)->toBe('ativo');
});

it('só conclui no dia seguinte ao último dia previsto', function () {
    Carbon::setTestNow('2026-10-26 23:59:00');

    $estagio = Estagio::factory()->create([
        'estado' => 'ativo',
        'data_fim_prevista' => '2026-10-26',
    ]);

    $this->artisan('app:atualizar-estagios-concluidos')
        ->expectsOutput('Verificação concluída. Estágios atualizados: 0.')
        ->assertSuccessful();

    expect($estagio->fresh()->estado)->toBe('ativo');

    Carbon::setTestNow('2026-10-27 00:05:00');

    $this->artisan('app:atualizar-estagios-concluidos')
        ->expectsOutput('Verificação concluída. Estágios atualizados: 1.')
        ->assertSuccessful();

    expect($estagio->fresh()->estado)->toBe('concluido')
        ->and($estagio->fresh()->data_fim_real?->toDateString())
        ->toBe('2026-10-26');
});

it('regista os meses em falta e avisa o administrador uma única vez', function () {
    Carbon::setTestNow('2026-10-26 10:00:00');

    $administrador = User::factory()->administrador()->create();

    $estagio = Estagio::factory()->create([
        'estado' => 'ativo',
        'data_inicio' => '2026-08-01',
        'data_fim_prevista' => '2027-01-31',
        'dia_limite_relatorio' => 5,
    ]);

    $this->artisan('app:alertar-relatorios-em-atraso')
        ->assertSuccessful();

    $relatorios = Relatorio::query()
        ->where('id_estagio', $estagio->id_estagio)
        ->orderBy('mes_referencia')
        ->get();

    /*
     * Agosto e setembro têm prazo já vencido (5 de setembro e 5 de
     * outubro). Outubro só vence a 5 de novembro.
     */
    expect($relatorios)->toHaveCount(2)
        ->and($relatorios->pluck('mes_referencia')->map->toDateString()->all())
        ->toBe(['2026-08-01', '2026-09-01'])
        ->and($relatorios->pluck('estado_avaliacao')->unique()->all())
        ->toBe([Relatorio::ESTADO_EM_ATRASO])
        ->and(
            Notificacao::query()
                ->where('id_utilizador', $administrador->id_utilizador)
                ->where('titulo', 'Relatório em atraso')
                ->count()
        )->toBe(2);

    $this->artisan('app:alertar-relatorios-em-atraso')
        ->assertSuccessful();

    expect(
        Relatorio::query()
            ->where('id_estagio', $estagio->id_estagio)
            ->count()
    )->toBe(2)
        ->and(
            Notificacao::query()
                ->where('id_utilizador', $administrador->id_utilizador)
                ->where('titulo', 'Relatório em atraso')
                ->count()
        )->toBe(2);

    expect(
        Notificacao::query()
            ->where('id_utilizador', $administrador->id_utilizador)
            ->where('titulo', 'Relatório em atraso')
            ->latest('id_notificacao')
            ->value('url')
    )->toBe('/admin?secao=relatorios');
});

it('marca em atraso um relatório existente que passou do prazo', function () {
    Carbon::setTestNow('2026-10-26 10:00:00');

    $administrador = User::factory()->administrador()->create();

    [$relatorio] = relatorioEmAtrasoExistente();

    $this->artisan('app:alertar-relatorios-em-atraso')
        ->assertSuccessful();

    expect($relatorio->fresh()->estado_avaliacao)
        ->toBe(Relatorio::ESTADO_EM_ATRASO)
        ->and($relatorio->fresh()->alerta_atraso_enviado_em)->not->toBeNull()
        ->and(
            Notificacao::query()
                ->where('id_utilizador', $administrador->id_utilizador)
                ->where('titulo', 'Relatório em atraso')
                ->exists()
        )->toBeTrue();
});

/**
 * Relatório mensal não entregue cujo prazo já passou.
 *
 * @return array{0: Relatorio, 1: Estagio}
 */
function relatorioEmAtrasoExistente(): array
{
    $estagio = Estagio::factory()->create([
        'estado' => 'ativo',
        'data_inicio' => '2026-10-01',
        'data_fim_prevista' => '2027-01-31',
        'dia_limite_relatorio' => 5,
    ]);

    $relatorio = Relatorio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'semanal',
        'mes_referencia' => '2026-10-01',
        'periodo_referencia' => '2026-10-01',
        'prazo_envio' => '2026-10-10',
        'enviado_em' => null,
        'entrega_confirmada_em' => null,
    ]);

    return [$relatorio, $estagio];
}
