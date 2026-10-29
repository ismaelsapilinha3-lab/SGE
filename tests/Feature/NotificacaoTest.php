<?php

use App\Models\Assiduidade;
use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\DocumentoEstagio;
use App\Models\Estagio;
use App\Models\HistoricoEstado;
use App\Models\Notificacao;
use App\Models\Perfil;
use App\Models\PlanoEstagio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Estágio ativo com estagiário e supervisor atribuído.
 *
 * @return array{0: Estagio, 1: User, 2: User}
 */
function estagioAlvoDeNotificacoes(): array
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
 * @return array{0: Estagio, 1: User, 2: Departamento}
 */
function estagioNotificacaoDosCoordenadores(): array
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
function dadosPlanoNotificacao(): array
{
    return [
        'objetivos' => 'Aprender a desenvolver aplicações web.',
        'atividades_previstas' => 'Desenvolvimento de módulos e testes.',
        'cronograma' => 'Mês 1: formação; Meses 2-5: desenvolvimento.',
        'data_inicio' => '2026-01-05',
        'data_fim' => '2026-06-05',
    ];
}

/**
 * Indica se o utilizador tem uma notificação com o título indicado.
 */
function temNotificacao(User|int $utilizador, string $titulo): bool
{
    $id = $utilizador instanceof User
        ? $utilizador->id_utilizador
        : $utilizador;

    return Notificacao::query()
        ->where('id_utilizador', $id)
        ->where('titulo', $titulo)
        ->exists();
}

/**
 * Destino (URL) da notificação mais recente com o título indicado.
 */
function urlDaNotificacao(User|int $utilizador, string $titulo): ?string
{
    $id = $utilizador instanceof User
        ? $utilizador->id_utilizador
        : $utilizador;

    return Notificacao::query()
        ->where('id_utilizador', $id)
        ->where('titulo', $titulo)
        ->latest('id_notificacao')
        ->value('url');
}

it('lista as notificações do utilizador com o resumo', function () {
    $utilizador = User::factory()->estagiario()->create();

    Notificacao::factory()->count(2)->create([
        'id_utilizador' => $utilizador->id_utilizador,
    ]);

    Notificacao::factory()->lida()->create([
        'id_utilizador' => $utilizador->id_utilizador,
    ]);

    Notificacao::factory()->create();

    $this->actingAs($utilizador, 'sanctum')
        ->getJson('/api/notificacoes')
        ->assertOk()
        ->assertJsonCount(3, 'notificacoes')
        ->assertJsonPath('resumo.nao_lidas', 2)
        ->assertJsonPath('resumo.total', 3);
});

it('filtra apenas as notificações não lidas', function () {
    $utilizador = User::factory()->estagiario()->create();

    Notificacao::factory()->create([
        'id_utilizador' => $utilizador->id_utilizador,
    ]);

    Notificacao::factory()->lida()->create([
        'id_utilizador' => $utilizador->id_utilizador,
    ]);

    $this->actingAs($utilizador, 'sanctum')
        ->getJson('/api/notificacoes?nao_lidas=1')
        ->assertOk()
        ->assertJsonCount(1, 'notificacoes');
});

it('marca uma notificação como lida', function () {
    $utilizador = User::factory()->estagiario()->create();

    $notificacao = Notificacao::factory()->create([
        'id_utilizador' => $utilizador->id_utilizador,
    ]);

    $this->actingAs($utilizador, 'sanctum')
        ->patchJson("/api/notificacoes/{$notificacao->id_notificacao}/ler")
        ->assertOk()
        ->assertJsonPath('message', 'Notificação marcada como lida.');

    expect($notificacao->fresh()->lida_em)->not->toBeNull();
});

it('marca todas as notificações como lidas', function () {
    $utilizador = User::factory()->estagiario()->create();

    Notificacao::factory()->count(3)->create([
        'id_utilizador' => $utilizador->id_utilizador,
    ]);

    $this->actingAs($utilizador, 'sanctum')
        ->patchJson('/api/notificacoes/ler-todas')
        ->assertOk()
        ->assertJsonPath('atualizadas', 3);

    expect($utilizador->notificacoes()->naoLidas()->count())->toBe(0);
});

it('não deixa mexer nas notificações de outro utilizador', function () {
    $utilizador = User::factory()->estagiario()->create();

    $notificacao = Notificacao::factory()->create();

    $this->actingAs($utilizador, 'sanctum')
        ->patchJson("/api/notificacoes/{$notificacao->id_notificacao}/ler")
        ->assertStatus(404);

    expect($notificacao->fresh()->lida_em)->toBeNull();
});

it('remove uma notificação do próprio utilizador', function () {
    $utilizador = User::factory()->estagiario()->create();

    $notificacao = Notificacao::factory()->create([
        'id_utilizador' => $utilizador->id_utilizador,
    ]);

    $this->actingAs($utilizador, 'sanctum')
        ->deleteJson("/api/notificacoes/{$notificacao->id_notificacao}")
        ->assertOk();

    expect(Notificacao::find($notificacao->id_notificacao))->toBeNull();
});

it('exige autenticação para aceder às notificações', function () {
    $this->getJson('/api/notificacoes')->assertStatus(401);
});

it('notifica o supervisor e o estagiário quando um supervisor é atribuído', function () {
    [$estagio, $coordenador, $departamento] = estagioNotificacaoDosCoordenadores();

    $novoSupervisor = User::factory()->supervisor()->create();

    Perfil::create([
        'id_utilizador' => $novoSupervisor->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->postJson(
            "/api/coordenador/estagios/{$estagio->id_estagio}/supervisores",
            ['id_supervisor' => $novoSupervisor->id_utilizador]
        )
        ->assertCreated();

    expect(temNotificacao($novoSupervisor, 'Novo estágio atribuído'))
        ->toBeTrue()
        ->and(temNotificacao($estagio->id_estagiario, 'Supervisor atribuído'))
        ->toBeTrue()
        ->and(urlDaNotificacao($novoSupervisor, 'Novo estágio atribuído'))
        ->toBe("/supervisor?estagio={$estagio->id_estagio}&aba=plano")
        ->and(urlDaNotificacao($estagio->id_estagiario, 'Supervisor atribuído'))
        ->toBe('/area-reservada?separador=geral');
});

it('notifica o estagiário quando o estágio é iniciado', function () {
    $admin = User::factory()->administrador()->create();

    $estagiario = User::factory()->estagiario()->create();

    $candidatura = Candidatura::factory()->aprovada()->create([
        'id_utilizador' => $estagiario->id_utilizador,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson(
            "/api/admin/estagiarios/{$candidatura->id_candidatura}/estado",
            [
                'estado' => 'ativo',
                'data_inicio' => '2026-01-05',
                'data_fim_prevista' => '2026-07-05',
            ]
        )
        ->assertCreated();

    expect(temNotificacao($estagiario, 'Estágio iniciado'))->toBeTrue()
        ->and(urlDaNotificacao($estagiario, 'Estágio iniciado'))
        ->toBe('/area-reservada?separador=geral');
});

it('notifica o estagiário e o supervisor quando o estado do estágio muda', function () {
    [$estagio, , $supervisor] = estagioAlvoDeNotificacoes();

    $admin = User::factory()->administrador()->create();

    $this->actingAs($admin, 'sanctum')
        ->patchJson(
            "/api/admin/estagios/{$estagio->id_candidatura}/estado",
            [
                'estado' => 'suspenso',
                'motivo_suspensao' => 'Motivos pessoais do estagiário.',
            ]
        )
        ->assertOk();

    expect(temNotificacao($estagio->id_estagiario, 'Estado do estágio atualizado'))
        ->toBeTrue()
        ->and(temNotificacao($supervisor, 'Estado do estágio atualizado'))
        ->toBeTrue()
        ->and(urlDaNotificacao($estagio->id_estagiario, 'Estado do estágio atualizado'))
        ->toBe('/area-reservada?separador=geral')
        ->and(urlDaNotificacao($supervisor, 'Estado do estágio atualizado'))
        ->toBe("/supervisor?estagio={$estagio->id_estagio}");
});

it('notifica o coordenador quando o plano é submetido', function () {
    [$estagio, $coordenador] = estagioNotificacaoDosCoordenadores();

    $supervisor = User::factory()->supervisor()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->putJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/plano",
            dadosPlanoNotificacao()
        )
        ->assertCreated();

    $notificacao = Notificacao::query()
        ->where('id_utilizador', $coordenador->id_utilizador)
        ->where('titulo', 'Plano de estágio submetido')
        ->latest('id_notificacao')
        ->first();

    expect($notificacao)->not->toBeNull()
        ->and($notificacao->url)->toBe(
            "/coordenador?estagio={$estagio->id_estagio}&aba=plano"
        );

    $this->actingAs($coordenador, 'sanctum')
        ->getJson('/api/notificacoes')
        ->assertOk()
        ->assertJsonPath(
            'notificacoes.0.url',
            "/coordenador?estagio={$estagio->id_estagio}&aba=plano"
        );
});

it('notifica os supervisores quando o plano é aprovado', function () {
    [$estagio, $coordenador] = estagioNotificacaoDosCoordenadores();

    $supervisor = User::factory()->supervisor()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    PlanoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'estado' => 'submetido',
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/estagios/{$estagio->id_estagio}/plano/aprovar"
        )
        ->assertOk();

    expect(temNotificacao($supervisor, 'Plano de estágio aprovado'))
        ->toBeTrue()
        ->and(urlDaNotificacao($supervisor, 'Plano de estágio aprovado'))
        ->toBe("/supervisor?estagio={$estagio->id_estagio}&aba=plano");
});

it('notifica os supervisores quando o plano é recusado', function () {
    [$estagio, $coordenador] = estagioNotificacaoDosCoordenadores();

    $supervisor = User::factory()->supervisor()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    PlanoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'estado' => 'submetido',
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/estagios/{$estagio->id_estagio}/plano/recusar",
            ['motivo' => 'Os objetivos não estão suficientemente claros.']
        )
        ->assertOk();

    expect(temNotificacao($supervisor, 'Plano de estágio recusado'))->toBeTrue()
        ->and(urlDaNotificacao($supervisor, 'Plano de estágio recusado'))
        ->toBe("/supervisor?estagio={$estagio->id_estagio}&aba=plano");
});

it('notifica os supervisores quando o estagiário submete o contrato', function () {
    Storage::fake('local');

    [$estagio, $estagiario, $supervisor] = estagioAlvoDeNotificacoes();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/contrato', [
            'ficheiro' => UploadedFile::fake()
                ->create('contrato.pdf', 100, 'application/pdf'),
        ])
        ->assertCreated();

    expect(temNotificacao($supervisor, 'Contrato submetido'))->toBeTrue()
        ->and(urlDaNotificacao($supervisor, 'Contrato submetido'))
        ->toBe("/supervisor?estagio={$estagio->id_estagio}&aba=contrato");
});

it('notifica o estagiário quando o contrato é rejeitado', function () {
    [$estagio, , $supervisor] = estagioAlvoDeNotificacoes();

    $documento = DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/documentos/{$documento->id_documento_estagio}/rejeitar",
            ['descricao' => 'O contrato está ilegível.']
        )
        ->assertOk();

    expect(temNotificacao($estagio->id_estagiario, 'Contrato rejeitado'))
        ->toBeTrue()
        ->and(urlDaNotificacao($estagio->id_estagiario, 'Contrato rejeitado'))
        ->toBe('/area-reservada?separador=documentos');
});

it('notifica os supervisores quando o estagiário justifica uma falta', function () {
    [$estagio, $estagiario, $supervisor] = estagioAlvoDeNotificacoes();

    $assiduidade = Assiduidade::factory()->falta()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson(
            "/api/estagiario/estagio/assiduidades/{$assiduidade->id_assiduidade}/justificar",
            ['justificacao' => 'Consulta médica no hospital.']
        )
        ->assertOk();

    expect(temNotificacao($supervisor, 'Falta justificada'))->toBeTrue()
        ->and(urlDaNotificacao($supervisor, 'Falta justificada'))
        ->toBe("/supervisor?estagio={$estagio->id_estagio}&aba=assiduidade");
});

it('notifica o estagiário quando a justificação é aprovada', function () {
    [$estagio, , $supervisor] = estagioAlvoDeNotificacoes();

    $assiduidade = Assiduidade::factory()
        ->justificacaoPendente()
        ->create([
            'id_estagio' => $estagio->id_estagio,
        ]);

    $this->actingAs($supervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/assiduidades/{$assiduidade->id_assiduidade}/justificacao/aprovar"
        )
        ->assertOk();

    expect(temNotificacao($estagio->id_estagiario, 'Justificação aprovada'))
        ->toBeTrue()
        ->and(urlDaNotificacao($estagio->id_estagiario, 'Justificação aprovada'))
        ->toBe('/area-reservada?separador=geral');
});

it('notifica o estagiário quando é registada uma avaliação', function () {
    [$estagio, , $supervisor] = estagioAlvoDeNotificacoes();

    $this->actingAs($supervisor, 'sanctum')
        ->postJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/avaliacoes",
            [
                'tipo' => 'mensal',
                'periodo_referencia' => '2026-10-01',
                'nota' => 16,
            ]
        )
        ->assertCreated();

    expect(temNotificacao($estagio->id_estagiario, 'Nova avaliação'))->toBeTrue()
        ->and(urlDaNotificacao($estagio->id_estagiario, 'Nova avaliação'))
        ->toBe('/area-reservada?separador=geral');
});

it('notifica os supervisores quando o estagiário dá feedback', function () {
    [$estagio, $estagiario, $supervisor] = estagioAlvoDeNotificacoes();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/feedback', [
            'nota_geral' => 8,
        ])
        ->assertCreated();

    expect(temNotificacao($supervisor, 'Feedback do estagiário'))->toBeTrue()
        ->and(urlDaNotificacao($supervisor, 'Feedback do estagiário'))
        ->toBe("/supervisor?estagio={$estagio->id_estagio}&aba=feedback");
});

it('notifica o candidato quando a candidatura é aprovada', function () {
    $departamento = Departamento::factory()->create();

    $coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $coordenador->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $candidato = User::factory()->estagiario()->create();

    $candidatura = Candidatura::factory()->create([
        'estado' => 'em_analise',
        'id_utilizador' => $candidato->id_utilizador,
        'id_coordenador' => $coordenador->id_utilizador,
        'id_departamento_selecionado' => $departamento->id_departamento,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/candidaturas/{$candidatura->id_candidatura}/aprovar"
        )
        ->assertOk();

    $notificacao = Notificacao::query()
        ->where('id_candidatura', $candidatura->id_candidatura)
        ->where('titulo', 'Candidatura aprovada')
        ->first();

    expect($notificacao)->not->toBeNull()
        ->and((int) $notificacao->id_utilizador)
        ->toBe((int) $candidato->id_utilizador)
        ->and($notificacao->url)->toBe('/estagiario');
});

it('notifica o candidato quando a candidatura é recusada', function () {
    $departamento = Departamento::factory()->create();

    $coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $coordenador->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $candidato = User::factory()->estagiario()->create();

    $candidatura = Candidatura::factory()->create([
        'estado' => 'em_analise',
        'id_utilizador' => $candidato->id_utilizador,
        'id_coordenador' => $coordenador->id_utilizador,
        'id_departamento_selecionado' => $departamento->id_departamento,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->patchJson(
            "/api/coordenador/candidaturas/{$candidatura->id_candidatura}/recusar",
            ['motivo' => 'O perfil não corresponde à vaga.']
        )
        ->assertOk();

    $notificacao = Notificacao::query()
        ->where('id_candidatura', $candidatura->id_candidatura)
        ->where('titulo', 'Candidatura recusada')
        ->first();

    expect($notificacao)->not->toBeNull()
        ->and((int) $notificacao->id_utilizador)
        ->toBe((int) $candidato->id_utilizador)
        ->and($notificacao->url)->toBe('/estagiario');
});

it('notifica o estagiário e os supervisores quando o comando conclui o estágio', function () {
    [$estagio, , $supervisor] = estagioAlvoDeNotificacoes();

    $fimPrevisto = now()->subDay()->toDateString();

    $estagio->update([
        'data_fim_prevista' => $fimPrevisto,
    ]);

    $this->artisan('app:atualizar-estagios-concluidos')->assertSuccessful();

    $estagio->refresh();

    expect($estagio->estado)->toBe('concluido')
        ->and($estagio->data_fim_real->toDateString())->toBe($fimPrevisto)
        ->and(temNotificacao($estagio->id_estagiario, 'Estágio concluído'))
        ->toBeTrue()
        ->and(temNotificacao($supervisor, 'Estágio concluído'))
        ->toBeTrue()
        ->and(urlDaNotificacao($estagio->id_estagiario, 'Estágio concluído'))
        ->toBe('/area-reservada?separador=geral')
        ->and(urlDaNotificacao($supervisor, 'Estágio concluído'))
        ->toBe("/supervisor?estagio={$estagio->id_estagio}");

    expect(
        HistoricoEstado::where('id_estagio', $estagio->id_estagio)
            ->where('estado_novo', 'concluido')
            ->count()
    )->toBe(1);
});
