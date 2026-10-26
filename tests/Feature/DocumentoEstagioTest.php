<?php

use App\Models\AcessoFicheiro;
use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\DocumentoEstagio;
use App\Models\Estagio;
use App\Models\HistoricoEstado;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Estágio ativo com estagiário e supervisor atribuído.
 *
 * @return array{0: Estagio, 1: User, 2: User}
 */
function estagioComContrato(): array
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
function estagioDocumentoDoCoordenador(): array
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

it('deixa o estagiário carregar o contrato', function () {
    Storage::fake('local');

    [$estagio, $estagiario] = estagioComContrato();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/contrato', [
            'ficheiro' => UploadedFile::fake()
                ->create('contrato.pdf', 100, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Contrato carregado com sucesso.');

    $documento = $estagio->documentos()
        ->where('tipo_documento', 'contrato')
        ->first();

    expect($documento)->not->toBeNull()
        ->and($documento->versao)->toBe(1)
        ->and($documento->estado)->toBe('pendente')
        ->and((int) $documento->id_estagiario)
        ->toBe((int) $estagiario->id_utilizador);

    Storage::disk('local')->assertExists($documento->caminho_arquivo);
});

it('cria uma nova versão a cada envio do contrato', function () {
    Storage::fake('local');

    [$estagio, $estagiario] = estagioComContrato();

    foreach ([1, 2] as $ignorado) {
        $this->actingAs($estagiario, 'sanctum')
            ->postJson('/api/estagiario/estagio/contrato', [
                'ficheiro' => UploadedFile::fake()
                    ->create('contrato.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated();
    }

    $versoes = $estagio->documentos()
        ->where('tipo_documento', 'contrato')
        ->orderBy('versao')
        ->pluck('versao')
        ->all();

    expect($versoes)->toBe([1, 2]);
});

it('não carrega o contrato de um estágio não ativo', function () {
    Storage::fake('local');

    $estagiario = User::factory()->estagiario()->create();

    Estagio::factory()->suspenso()->create([
        'id_estagiario' => $estagiario->id_utilizador,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/contrato', [
            'ficheiro' => UploadedFile::fake()
                ->create('contrato.pdf', 100, 'application/pdf'),
        ])
        ->assertStatus(422);
});

it('rejeita um ficheiro de contrato com tipo inválido', function () {
    Storage::fake('local');

    [, $estagiario] = estagioComContrato();

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/contrato', [
            'ficheiro' => UploadedFile::fake()
                ->create('malware.exe', 10, 'application/x-msdownload'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ficheiro');
});

it('mostra ao estagiário o seu estágio com os documentos', function () {
    [$estagio, $estagiario] = estagioComContrato();

    DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'id_estagiario' => $estagiario->id_utilizador,
    ]);

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/estagio')
        ->assertOk()
        ->assertJsonPath('estagio.id_estagio', $estagio->id_estagio)
        ->assertJsonCount(1, 'estagio.documentos');
});

it('deixa o supervisor validar o contrato e regista o histórico', function () {
    [$estagio, , $supervisor] = estagioComContrato();

    $documento = DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/documentos/{$documento->id_documento_estagio}/validar"
        )
        ->assertOk()
        ->assertJsonPath('message', 'Documento validado com sucesso.');

    expect($documento->fresh()->estado)->toBe('validado')
        ->and(
            HistoricoEstado::where(
                'id_documento_estagio',
                $documento->id_documento_estagio
            )->count()
        )->toBe(1);
});

it('deixa o supervisor rejeitar o contrato com um motivo', function () {
    [$estagio, , $supervisor] = estagioComContrato();

    $documento = DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/documentos/{$documento->id_documento_estagio}/rejeitar",
            ['descricao' => 'O contrato está ilegível.']
        )
        ->assertOk()
        ->assertJsonPath('message', 'Documento rejeitado.');

    $documento->refresh();

    expect($documento->estado)->toBe('rejeitado')
        ->and($documento->descricao)->toBe('O contrato está ilegível.');
});

it('não valida um documento que pertence a outro estágio', function () {
    [$estagio, , $supervisor] = estagioComContrato();

    $outroEstagio = Estagio::factory()->create();

    $documento = DocumentoEstagio::factory()->create([
        'id_estagio' => $outroEstagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/documentos/{$documento->id_documento_estagio}/validar"
        )
        ->assertStatus(404);
});

it('não valida duas vezes o mesmo documento', function () {
    [$estagio, , $supervisor] = estagioComContrato();

    $documento = DocumentoEstagio::factory()->validado()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/documentos/{$documento->id_documento_estagio}/validar"
        )
        ->assertStatus(422);
});

it('não valida nem rejeita documentos de um estágio terminado', function () {
    [$estagio, , $supervisor] = estagioComContrato();

    $estagio->update(['estado' => 'concluido']);

    $documento = DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($supervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/documentos/{$documento->id_documento_estagio}/validar"
        )
        ->assertStatus(422);

    $this->actingAs($supervisor, 'sanctum')
        ->patchJson(
            "/api/supervisor/estagios/{$estagio->id_estagio}/documentos/{$documento->id_documento_estagio}/rejeitar",
            ['descricao' => 'O contrato está ilegível.']
        )
        ->assertStatus(422);

    expect($documento->fresh()->estado)->toBe('pendente');
});

it('deixa o supervisor descarregar o contrato do seu estágio', function () {
    Storage::fake('local');

    [$estagio, , $supervisor] = estagioComContrato();

    $documento = DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    Storage::disk('local')->put($documento->caminho_arquivo, 'contrato');

    $this->actingAs($supervisor, 'sanctum')
        ->get("/api/documentos-estagio/{$documento->id_documento_estagio}")
        ->assertOk();

    expect(AcessoFicheiro::query()
        ->where('id_documento_estagio', $documento->id_documento_estagio)
        ->where('id_utilizador', $supervisor->id_utilizador)
        ->exists())->toBeTrue();
});

it('não deixa um estagiário descarregar o contrato de outro', function () {
    Storage::fake('local');

    [$estagio] = estagioComContrato();

    $documento = DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
        'id_estagiario' => User::factory()->estagiario()->create()->id_utilizador,
    ]);

    Storage::disk('local')->put($documento->caminho_arquivo, 'contrato');

    $outroEstagiario = User::factory()->estagiario()->create();

    $this->actingAs($outroEstagiario, 'sanctum')
        ->get("/api/documentos-estagio/{$documento->id_documento_estagio}")
        ->assertStatus(403);

    expect(AcessoFicheiro::count())->toBe(0);
});

it('não expõe o caminho interno dos documentos', function () {
    [$estagio, $estagiario] = estagioComContrato();

    $documento = DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $resposta = $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/estagio')
        ->assertOk()
        ->assertJsonMissingPath('estagio.documentos.0.caminho_arquivo');

    expect($resposta->json('estagio.documentos.0'))
        ->toHaveKey('url')
        ->and($resposta->json('estagio.documentos.0.url'))
        ->toBe("/api/documentos-estagio/{$documento->id_documento_estagio}");
});

it('não expõe o caminho interno na listagem do coordenador', function () {
    [$estagio, $coordenador] = estagioDocumentoDoCoordenador();

    DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->getJson("/api/coordenador/estagios/{$estagio->id_estagio}/documentos")
        ->assertOk()
        ->assertJsonMissingPath('documentos.0.caminho_arquivo')
        ->assertJsonPath('documentos.0.nome_arquivo', 'contrato.pdf');
});

it('deixa o coordenador consultar os documentos da sua direção', function () {
    [$estagio, $coordenador] = estagioDocumentoDoCoordenador();

    DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    $this->actingAs($coordenador, 'sanctum')
        ->getJson("/api/coordenador/estagios/{$estagio->id_estagio}/documentos")
        ->assertOk()
        ->assertJsonCount(1, 'documentos');
});

it('deixa o coordenador descarregar documentos de candidatura ainda sem coordenador', function () {
    Storage::fake('local');

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

    $documento = DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    Storage::disk('local')->put($documento->caminho_arquivo, 'contrato');

    $this->actingAs($coordenador, 'sanctum')
        ->get("/api/documentos-estagio/{$documento->id_documento_estagio}")
        ->assertOk();
});

it('deixa o coordenador descarregar documentos de candidatura presa a coordenador inativo', function () {
    Storage::fake('local');

    $departamento = Departamento::factory()->create();

    $coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $coordenador->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $inativo = User::factory()->coordenador()->create([
        'ativo' => false,
    ]);

    Perfil::create([
        'id_utilizador' => $inativo->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $candidatura = Candidatura::factory()->aprovada()->create([
        'id_coordenador' => $inativo->id_utilizador,
        'id_departamento_selecionado' => $departamento->id_departamento,
    ]);

    $estagio = Estagio::factory()->create([
        'id_candidatura' => $candidatura->id_candidatura,
    ]);

    $documento = DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    Storage::disk('local')->put($documento->caminho_arquivo, 'contrato');

    $this->actingAs($coordenador, 'sanctum')
        ->get("/api/documentos-estagio/{$documento->id_documento_estagio}")
        ->assertOk();
});

it('não deixa um coordenador descarregar documentos de outra direção', function () {
    Storage::fake('local');

    $outroDepartamento = Departamento::factory()->create();

    $coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $coordenador->id_utilizador,
        'id_departamento' => $outroDepartamento->id_departamento,
    ]);

    [$estagio] = estagioDocumentoDoCoordenador();

    $documento = DocumentoEstagio::factory()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    Storage::disk('local')->put($documento->caminho_arquivo, 'contrato');

    $this->actingAs($coordenador, 'sanctum')
        ->get("/api/documentos-estagio/{$documento->id_documento_estagio}")
        ->assertStatus(403);
});
