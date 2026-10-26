<?php

use App\Models\AcessoFicheiro;
use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Estagio;
use App\Models\Perfil;
use App\Models\Relatorio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Estágio ativo com estagiário e supervisor atribuído.
 *
 * @return array{0: Estagio, 1: User, 2: User}
 */
function estagioComRelatorios(): array
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
function estagioRelatorioDoCoordenador(): array
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

/**
 * @return array<string, string>
 */
function dadosRelatorio(): array
{
    return [
        'tipo' => 'mensal',
        'periodo_referencia' => now()->startOfMonth()->toDateString(),
        'atividades' => 'Desenvolvimento de funcionalidades.',
        'resultados' => 'Conclusão do módulo de relatórios.',
        'constrangimentos' => 'Falta de documentação.',
        'objetivos' => 'Consolidar os conhecimentos adquiridos.',
    ];
}

it('deixa o estagiário criar um relatório', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', dadosRelatorio())
        ->assertCreated()
        ->assertJsonPath('message', 'Relatório criado com sucesso.');

    $relatorio = $estagio->relatorios()->first();

    expect($relatorio)->not->toBeNull()
        ->and($relatorio->tipo)->toBe('mensal')
        ->and($relatorio->submetido_em)->toBeNull()
        ->and($relatorio->mes_referencia?->toDateString())
        ->toBe(now()->startOfMonth()->toDateString())
        ->and($relatorio->prazo_envio?->toDateString())
        ->toBe(now()->startOfMonth()->addMonthNoOverflow()->day(5)->toDateString())
        ->and($relatorio->estado_avaliacao)->toBe(Relatorio::ESTADO_PENDENTE);
});

it('não cria dois relatórios do mesmo tipo para o mesmo período', function () {
    [, $estagiario] = estagioComRelatorios();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', dadosRelatorio())
        ->assertCreated();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', dadosRelatorio())
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Já existe um relatório deste tipo para este período.'
        );
});

it('exige um tipo de relatório válido', function () {
    [, $estagiario] = estagioComRelatorios();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson(
            '/api/estagiario/estagio/relatorios',
            array_merge(dadosRelatorio(), ['tipo' => 'diario'])
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors('tipo');
});

it('não cria relatórios num estágio cancelado', function () {
    $estagiario = User::factory()->estagiario()->create();

    Estagio::factory()->cancelado()->create([
        'id_estagiario' => $estagiario->id_utilizador,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', dadosRelatorio())
        ->assertStatus(422);
});

it('recusa relatórios fora do período do estágio', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $periodoAnterior = $estagio->data_inicio
        ->copy()
        ->subMonthNoOverflow()
        ->startOfMonth()
        ->toDateString();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', [
            ...dadosRelatorio(),
            'periodo_referencia' => $periodoAnterior,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('periodo_referencia');
});

it('recusa relatórios de um mês futuro', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', [
            ...dadosRelatorio(),
            'periodo_referencia' => now()
                ->startOfMonth()
                ->addMonthNoOverflow()
                ->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('periodo_referencia');
});

it('deixa o estagiário atualizar um rascunho', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->putJson(
            "/api/estagiario/estagio/relatorios/{$relatorio->id_relatorio}",
            array_merge(dadosRelatorio(), [
                'atividades' => 'Atividades atualizadas.',
            ])
        )
        ->assertOk()
        ->assertJsonPath('message', 'Relatório atualizado com sucesso.');

    expect($relatorio->fresh()->atividades)->toBe('Atividades atualizadas.');
});

it('deixa o estagiário submeter um relatório', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson(
            "/api/estagiario/estagio/relatorios/{$relatorio->id_relatorio}/submeter"
        )
        ->assertOk()
        ->assertJsonPath('message', 'Relatório submetido com sucesso.');

    expect($relatorio->fresh()->submetido_em)->not->toBeNull()
        ->and($relatorio->fresh()->enviado_em)->not->toBeNull();
});

it('não altera um relatório já submetido', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->submetido()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->putJson(
            "/api/estagiario/estagio/relatorios/{$relatorio->id_relatorio}",
            dadosRelatorio()
        )
        ->assertStatus(422);
});

it('não deixa um estagiário alterar o relatório de outro', function () {
    [$estagio] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $outroEstagiario = User::factory()->estagiario()->create();

    Estagio::factory()->create([
        'id_estagiario' => $outroEstagiario->id_utilizador,
    ]);

    $this->actingAs($outroEstagiario, 'sanctum')
        ->putJson(
            "/api/estagiario/estagio/relatorios/{$relatorio->id_relatorio}",
            dadosRelatorio()
        )
        ->assertStatus(404);
});

it('mostra ao supervisor apenas os relatórios submetidos', function () {
    [$estagio, $estagiario, $supervisor] = estagioComRelatorios();

    Relatorio::factory()->submetido()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
    ]);

    Relatorio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'semanal',
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->getJson("/api/supervisor/estagios/{$estagio->id_estagio}/relatorios")
        ->assertOk()
        ->assertJsonCount(1, 'relatorios');
});

it('deixa o coordenador consultar os relatórios da sua direção', function () {
    [$estagio, $coordenador] = estagioRelatorioDoCoordenador();

    Relatorio::factory()->submetido()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->getJson("/api/coordenador/estagios/{$estagio->id_estagio}/relatorios")
        ->assertOk()
        ->assertJsonCount(1, 'relatorios');
});

it('deixa o estagiário corrigir e submeter novamente um relatório recusado', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->submetido()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
        'periodo_referencia' => now()->startOfMonth()->toDateString(),
        'mes_referencia' => now()->startOfMonth()->toDateString(),
        'estado_avaliacao' => Relatorio::ESTADO_REJEITADO,
        'comentario_admin' => 'Faltam os resultados do mês.',
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->putJson(
            "/api/estagiario/estagio/relatorios/{$relatorio->id_relatorio}",
            array_merge(dadosRelatorio(), [
                'atividades' => 'Atividades corrigidas.',
            ])
        )
        ->assertOk()
        ->assertJsonPath('message', 'Relatório atualizado com sucesso.');

    $relatorio->refresh();

    expect($relatorio->atividades)->toBe('Atividades corrigidas.')
        ->and($relatorio->submetido_em)->toBeNull()
        ->and($relatorio->enviado_em)->toBeNull()
        ->and($relatorio->comentario_admin)->toBeNull()
        ->and($relatorio->estado_avaliacao)->toBe(Relatorio::ESTADO_PENDENTE);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson(
            "/api/estagiario/estagio/relatorios/{$relatorio->id_relatorio}/submeter"
        )
        ->assertOk()
        ->assertJsonPath('message', 'Relatório submetido com sucesso.');

    expect($relatorio->fresh()->submetido_em)->not->toBeNull()
        ->and($relatorio->fresh()->enviado_em)->not->toBeNull()
        ->and($estagio->relatorios()->count())->toBe(1);
});

it('aceita o relatório do último mês depois de o estágio ser concluído', function () {
    Carbon::setTestNow('2026-10-26 10:00:00');

    $estagiario = User::factory()->estagiario()->create();

    $estagio = Estagio::factory()->concluido()->create([
        'id_estagiario' => $estagiario->id_utilizador,
        'data_inicio' => '2026-08-01',
        'data_fim_prevista' => '2026-10-25',
        'data_fim_real' => '2026-10-25',
        'dia_limite_relatorio' => 5,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', dadosRelatorio())
        ->assertCreated();

    expect($estagio->relatorios()->count())->toBe(1);
});

it('fecha a entrega do relatório quando o prazo passa depois da conclusão', function () {
    Carbon::setTestNow('2026-11-06 10:00:00');

    $estagiario = User::factory()->estagiario()->create();

    $estagio = Estagio::factory()->concluido()->create([
        'id_estagiario' => $estagiario->id_utilizador,
        'data_inicio' => '2026-08-01',
        'data_fim_prevista' => '2026-10-25',
        'data_fim_real' => '2026-10-25',
        'dia_limite_relatorio' => 5,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', [
            ...dadosRelatorio(),
            'periodo_referencia' => '2026-10-01',
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Só é possível criar relatórios de um estágio ativo ou concluído dentro do prazo.'
        );

    expect($estagio->relatorios()->count())->toBe(0);
});

it('permite reenviar em texto um relatório recusado', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->submetido()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
        'periodo_referencia' => now()->startOfMonth()->toDateString(),
        'mes_referencia' => now()->startOfMonth()->toDateString(),
        'estado_avaliacao' => Relatorio::ESTADO_REJEITADO,
        'comentario_admin' => 'Corrigir os objetivos.',
        'enviado_em' => now(),
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/relatorios', [
            'tipo' => 'mensal',
            'mes_referencia' => now()->startOfMonth()->toDateString(),
            'atividades' => 'Atividades revistas e completadas.',
            'resultados' => 'Resultados corrigidos.',
            'constrangimentos' => null,
            'objetivos' => null,
        ])
        ->assertCreated()
        ->assertJsonPath('data.id_relatorio', $relatorio->id_relatorio)
        ->assertJsonPath('data.estado_avaliacao', Relatorio::ESTADO_PENDENTE);

    $relatorio->refresh();

    expect($estagio->relatorios()->count())->toBe(1)
        ->and($relatorio->atividades)->toBe('Atividades revistas e completadas.')
        ->and($relatorio->comentario_admin)->toBeNull()
        ->and($relatorio->submetido_em)->not->toBeNull()
        ->and($relatorio->enviado_em)->not->toBeNull();
});

it('permite entregar em texto um relatório mensal marcado em atraso', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
        'periodo_referencia' => now()->startOfMonth()->toDateString(),
        'mes_referencia' => now()->startOfMonth()->toDateString(),
        'prazo_envio' => now()->subDay()->toDateString(),
        'submetido_em' => null,
        'enviado_em' => null,
        'entrega_confirmada_em' => null,
        'estado_avaliacao' => Relatorio::ESTADO_EM_ATRASO,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/relatorios', [
            'tipo' => 'mensal',
            'mes_referencia' => now()->startOfMonth()->toDateString(),
            'atividades' => 'Atividades entregues fora do prazo.',
            'resultados' => 'Resultados entregues fora do prazo.',
            'constrangimentos' => null,
            'objetivos' => null,
        ])
        ->assertCreated()
        ->assertJsonPath('data.id_relatorio', $relatorio->id_relatorio)
        ->assertJsonPath('data.estado_avaliacao', Relatorio::ESTADO_PENDENTE);

    $relatorio->refresh();

    expect($estagio->relatorios()->count())->toBe(1)
        ->and($relatorio->submetido_em)->not->toBeNull()
        ->and($relatorio->enviado_em)->not->toBeNull()
        ->and($relatorio->atividades)->toBe('Atividades entregues fora do prazo.');
});

it('substitui o ficheiro de um relatório recusado', function () {
    Storage::fake('local');

    [$estagio, $estagiario] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->submetido()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
        'periodo_referencia' => now()->startOfMonth()->toDateString(),
        'mes_referencia' => now()->startOfMonth()->toDateString(),
        'estado_avaliacao' => Relatorio::ESTADO_REJEITADO,
        'comentario_admin' => 'Ficheiro incompleto.',
        'enviado_em' => now(),
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/relatorios/ficheiro', [
            'tipo' => 'mensal',
            'mes_referencia' => now()->startOfMonth()->toDateString(),
            'relatorio_id' => $relatorio->id_relatorio,
            'ficheiro' => UploadedFile::fake()->create(
                'relatorio.pdf',
                100,
                'application/pdf'
            ),
        ])
        ->assertCreated()
        ->assertJsonPath('data.id_relatorio', $relatorio->id_relatorio)
        ->assertJsonPath('data.estado_avaliacao', Relatorio::ESTADO_PENDENTE);

    $relatorio->refresh();

    expect($relatorio->comentario_admin)->toBeNull()
        ->and($relatorio->submetido_em)->not->toBeNull()
        ->and($relatorio->enviado_em)->not->toBeNull()
        ->and($relatorio->ficheiro_path)->not->toBeNull();

    Storage::disk('local')->assertExists($relatorio->ficheiro_path);
});

it('carrega o ficheiro para o relatório do tipo e mês indicados', function () {
    Storage::fake('local');

    [$estagio, $estagiario] = estagioComRelatorios();

    $alvo = Relatorio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
        'periodo_referencia' => now()->startOfMonth()->toDateString(),
        'mes_referencia' => now()->startOfMonth()->toDateString(),
    ]);

    $outro = Relatorio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'semanal',
        'periodo_referencia' => now()->startOfMonth()->toDateString(),
        'mes_referencia' => now()->startOfMonth()->toDateString(),
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/relatorios/ficheiro', [
            'tipo' => 'mensal',
            'mes_referencia' => now()->startOfMonth()->toDateString(),
            'ficheiro' => UploadedFile::fake()->create(
                'relatorio.pdf',
                100,
                'application/pdf'
            ),
        ])
        ->assertCreated()
        ->assertJsonPath('data.id_relatorio', $alvo->id_relatorio);

    expect($alvo->fresh()->ficheiro_path)->not->toBeNull()
        ->and($outro->fresh()->ficheiro_path)->toBeNull();
});

it('regista o acesso ao ficheiro do relatório', function () {
    Storage::fake('local');

    [$estagio, $estagiario] = estagioComRelatorios();

    $relatorio = Relatorio::factory()->submetido()->create([
        'id_estagio' => $estagio->id_estagio,
        'ficheiro_path' => 'relatorios/relatorio.pdf',
        'ficheiro_nome' => 'relatorio.pdf',
    ]);

    Storage::disk('local')->put($relatorio->ficheiro_path, 'conteudo');

    $this->actingAs($estagiario, 'sanctum')
        ->get("/api/estagiario/relatorios/{$relatorio->id_relatorio}/ficheiro")
        ->assertOk();

    expect(AcessoFicheiro::query()
        ->where('id_relatorio', $relatorio->id_relatorio)
        ->where('id_utilizador', $estagiario->id_utilizador)
        ->exists())->toBeTrue();
});

it('recusa relatórios de um mês futuro nas APIs novas', function () {
    Storage::fake('local');

    [$estagio, $estagiario] = estagioComRelatorios();

    $mesFuturo = now()
        ->startOfMonth()
        ->addMonthNoOverflow()
        ->toDateString();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/relatorios', [
            'tipo' => 'mensal',
            'mes_referencia' => $mesFuturo,
            'atividades' => 'Atividades de um mês futuro.',
            'resultados' => 'Resultados de um mês futuro.',
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Não é possível enviar relatórios de um mês futuro.'
        );

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/relatorios/ficheiro', [
            'tipo' => 'mensal',
            'mes_referencia' => $mesFuturo,
            'ficheiro' => UploadedFile::fake()->create(
                'relatorio.pdf',
                100,
                'application/pdf'
            ),
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Não é possível enviar relatórios de um mês futuro.'
        );

    expect($estagio->relatorios()->count())->toBe(0);
});

it('não permite reenviar um relatório aprovado', function () {
    [$estagio, $estagiario] = estagioComRelatorios();

    Relatorio::factory()->submetido()->create([
        'id_estagio' => $estagio->id_estagio,
        'tipo' => 'mensal',
        'periodo_referencia' => now()->startOfMonth()->toDateString(),
        'mes_referencia' => now()->startOfMonth()->toDateString(),
        'estado_avaliacao' => Relatorio::ESTADO_APROVADO,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/relatorios', [
            'tipo' => 'mensal',
            'mes_referencia' => now()->startOfMonth()->toDateString(),
            'atividades' => 'Nova tentativa de entrega.',
            'resultados' => 'Nova tentativa de entrega.',
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Este relatório já foi aprovado pelo administrador.'
        );
});
