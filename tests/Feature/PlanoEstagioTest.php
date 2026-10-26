<?php

use App\Models\AcessoFicheiro;
use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Estagio;
use App\Models\HistoricoEstado;
use App\Models\Notificacao;
use App\Models\Perfil;
use App\Models\PlanoEstagio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('regista o acesso ao ficheiro do plano de estágio', function () {
    Storage::fake('local');

    $estagiario = User::factory()->estagiario()->create();

    $estagio = Estagio::factory()->create([
        'id_estagiario' => $estagiario->id_utilizador,
    ]);

    $plano = PlanoEstagio::factory()->doAdministrador()->create([
        'id_estagio' => $estagio->id_estagio,
        'nome_arquivo' => 'plano-estagio.pdf',
        'caminho_arquivo' => 'planos_estagio/plano-estagio.pdf',
    ]);

    Storage::disk('local')->put($plano->caminho_arquivo, 'plano');

    $this->actingAs($estagiario, 'sanctum')
        ->get("/api/estagiario/planos/{$plano->id_plano_estagio}/ficheiro")
        ->assertOk();

    expect(AcessoFicheiro::query()
        ->where('id_plano_estagio', $plano->id_plano_estagio)
        ->where('id_utilizador', $estagiario->id_utilizador)
        ->exists())->toBeTrue();
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

it('deixa o coordenador consultar o plano preso a um coordenador inativo', function () {
    [$estagio, $coordenador] = planoEstagioDoCoordenador();

    $inativo = User::factory()->coordenador()->create([
        'ativo' => false,
    ]);

    Perfil::create([
        'id_utilizador' => $inativo->id_utilizador,
        'id_departamento' => $coordenador->perfil?->id_departamento,
    ]);

    $estagio->candidatura->update([
        'id_coordenador' => $inativo->id_utilizador,
    ]);

    $plano = PlanoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->getJson("/api/coordenador/estagios/{$estagio->id_estagio}/plano")
        ->assertOk()
        ->assertJsonPath('plano.id_plano_estagio', $plano->id_plano_estagio);
});

it('recusa o acesso de quem não é supervisor', function () {
    $estagiario = User::factory()->estagiario()->create();

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/supervisor/estagios')
        ->assertStatus(403);
});

it('mantém o plano do supervisor quando o administrador carrega o documento oficial', function () {
    [$estagio, $supervisor] = planoEstagioDoSupervisor();

    $planoSupervisor = PlanoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'id_supervisor' => $supervisor->id_utilizador,
        'estado' => 'submetido',
    ]);

    Storage::fake('local');

    $administrador = User::factory()->administrador()->create();

    $this->actingAs($administrador, 'sanctum')
        ->postJson('/api/admin/planos-estagio', [
            'id_estagio' => $estagio->id_estagio,
            'ficheiro' => UploadedFile::fake()->create(
                'plano-oficial.pdf',
                100,
                'application/pdf'
            ),
        ])
        ->assertCreated();

    expect($planoSupervisor->fresh()->estado)->toBe('submetido')
        ->and($estagio->planoVigente()?->id_plano_estagio)
        ->toBe($planoSupervisor->id_plano_estagio)
        ->and($estagio->planoDocumentoVigente()?->origem)
        ->toBe(PlanoEstagio::ORIGEM_ADMINISTRADOR)
        ->and($estagio->planos()->count())->toBe(2);
});

it('não substitui o documento oficial quando o supervisor submete o plano', function () {
    [$estagio, $supervisor] = planoEstagioDoSupervisor();

    $documento = PlanoEstagio::factory()->doAdministrador()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->putJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/plano",
            dadosPlanoValido()
        )
        ->assertCreated();

    $planoSupervisor = $estagio->planoVigente();

    expect($documento->fresh()->estado)->toBe('aprovado')
        ->and($planoSupervisor)->not->toBeNull()
        ->and($planoSupervisor->origem)->toBe(PlanoEstagio::ORIGEM_SUPERVISOR)
        ->and($estagio->planoDocumentoVigente()?->id_plano_estagio)
        ->toBe($documento->id_plano_estagio)
        ->and($estagio->planos()->count())->toBe(2);
});

it('mostra ao administrador o plano de atividades aprovado pelo coordenador', function () {
    [$estagio] = planoEstagioDoSupervisor();

    $plano = PlanoEstagio::factory()->aprovado()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $administrador = User::factory()->administrador()->create();

    $this->actingAs($administrador, 'sanctum')
        ->getJson("/api/admin/estagios/{$estagio->id_estagio}")
        ->assertOk()
        ->assertJsonPath(
            'plano_aprovado.id_plano_estagio',
            $plano->id_plano_estagio
        );
});

it('atribui e notifica o coordenador quando o plano é submetido sem coordenador', function () {
    $departamento = Departamento::factory()->create();

    $coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $coordenador->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $candidatura = Candidatura::factory()->aprovada()->create([
        'id_coordenador' => null,
        'id_departamento_selecionado' => $departamento->id_departamento,
    ]);

    $estagio = Estagio::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
    ]);

    $supervisor = User::factory()->supervisor()->create();

    $estagio->supervisores()->create([
        'id_supervisor' => $supervisor->id_utilizador,
        'atribuido_em' => now(),
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->putJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/plano",
            dadosPlanoValido()
        )
        ->assertCreated();

    expect($candidatura->fresh()->id_coordenador)
        ->toBe($coordenador->id_utilizador)
        ->and(
            Notificacao::query()
                ->where('id_utilizador', $coordenador->id_utilizador)
                ->where('titulo', 'Plano de estágio submetido')
                ->exists()
        )->toBeTrue();
});

it('atribui a candidatura órfã ao coordenador que decide o plano', function () {
    $departamento = Departamento::factory()->create();

    $coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $coordenador->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $candidatura = Candidatura::factory()->aprovada()->create([
        'id_coordenador' => null,
        'id_departamento_selecionado' => $departamento->id_departamento,
    ]);

    $estagio = Estagio::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
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

    expect($candidatura->fresh()->id_coordenador)
        ->toBe($coordenador->id_utilizador);
});
