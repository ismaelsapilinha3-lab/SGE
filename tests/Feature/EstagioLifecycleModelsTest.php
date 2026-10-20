<?php

use App\Models\Assiduidade;
use App\Models\AtividadeEstagiario;
use App\Models\Avaliacao;
use App\Models\Candidatura;
use App\Models\DocumentoEstagio;
use App\Models\Entrevista;
use App\Models\Estagio;
use App\Models\FeedbackEstagio;
use App\Models\Notificacao;
use App\Models\PlanoEstagio;
use App\Models\Relatorio;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('persiste um estágio e resolve as relações principais', function () {
    $estagio = Estagio::factory()->create();

    expect($estagio->candidatura)->toBeInstanceOf(Candidatura::class)
        ->and($estagio->estagiario)->toBeInstanceOf(User::class)
        ->and($estagio->estaAtivo())->toBeTrue();
});

it('associa e conta supervisores ativos de um estágio', function () {
    $estagio = Estagio::factory()->create();
    $supervisor = User::factory()->supervisor()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    expect($estagio->supervisoresAtivos()->count())->toBe(1)
        ->and($estagio->supervisoresAtivos->first()->supervisor->is($supervisor))
        ->toBeTrue();
});

it('impede duas assiduidades no mesmo dia para o mesmo estágio', function () {
    $estagio = Estagio::factory()->create();

    Assiduidade::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'data' => '2026-05-04',
    ]);

    Assiduidade::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'data' => '2026-05-04',
    ]);
})->throws(UniqueConstraintViolationException::class);

it('guarda o histórico do plano antes de o reenviar', function () {
    $plano = PlanoEstagio::factory()->recusado()->create();
    $coordenador = User::factory()->coordenador()->create();

    $plano->registarHistorico($coordenador->id_utilizador);

    $historico = $plano->historico()->first();

    expect($plano->historico()->count())->toBe(1)
        ->and($historico->estado)->toBe('recusado')
        ->and($historico->alterado_por)->toBe($coordenador->id_utilizador)
        ->and($historico->objetivos)->toBe($plano->objetivos);
});

it('marca uma notificação como lida', function () {
    $notificacao = Notificacao::factory()->create();

    expect($notificacao->estaLida())->toBeFalse();

    $notificacao->marcarComoLida();

    expect($notificacao->fresh()->estaLida())->toBeTrue()
        ->and(Notificacao::naoLidas()->count())->toBe(0);
});

it('cria um feedback único por estágio', function () {
    $estagio = Estagio::factory()->create();

    FeedbackEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    expect($estagio->feedback()->count())->toBe(1);
});

it('persiste registos de todas as tabelas de acompanhamento', function () {
    $estagio = Estagio::factory()->create();

    Assiduidade::factory()->create(['id_estagio' => $estagio->id_estagio]);
    AtividadeEstagiario::factory()->create(['id_estagio' => $estagio->id_estagio]);
    Avaliacao::factory()->create(['id_estagio' => $estagio->id_estagio]);
    Relatorio::factory()->create(['id_estagio' => $estagio->id_estagio]);
    PlanoEstagio::factory()->create(['id_estagio' => $estagio->id_estagio]);
    DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'id_estagiario' => $estagio->id_estagiario,
        'versao' => 1,
    ]);
    DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'id_estagiario' => $estagio->id_estagiario,
        'versao' => 2,
    ]);
    Entrevista::factory()->create();

    expect($estagio->assiduidades()->count())->toBe(1)
        ->and($estagio->atividades()->count())->toBe(1)
        ->and($estagio->avaliacoes()->count())->toBe(1)
        ->and($estagio->relatorios()->count())->toBe(1)
        ->and($estagio->planos()->count())->toBe(1)
        ->and($estagio->documentos()->count())->toBe(2);
});

it('considera um relatório submetido quando tem data de submissão', function () {
    $relatorio = Relatorio::factory()->create();

    expect($relatorio->estaSubmetido())->toBeFalse();

    $relatorio->update(['submetido_em' => now()]);

    expect($relatorio->fresh()->estaSubmetido())->toBeTrue();
});
