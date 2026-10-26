<?php

use App\Models\AcessoFicheiro;
use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Documento;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda um documento de candidatura no disco local para ser consultado
 * durante os testes de autorização.
 */
function criarDocumentoDaCandidatura(Candidatura $candidatura): Documento
{
    $caminho = "documentos/{$candidatura->id_candidatura}/bi.pdf";

    Storage::disk('local')->put($caminho, 'conteudo');

    return Documento::create([
        'id_candidatura' => $candidatura->id_candidatura,
        'tipo_documento' => 'bi',
        'versao' => 1,
        'nome_arquivo' => 'bi.pdf',
        'caminho_arquivo' => $caminho,
        'data_upload' => now(),
        'estado' => 'pendente',
    ]);
}

beforeEach(function () {
    Storage::fake('local');

    $this->departamento = Departamento::factory()->create();

    $this->coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $this->coordenador->id_utilizador,
        'id_departamento' => $this->departamento->id_departamento,
    ]);
});

it('deixa o coordenador ver documentos da candidatura atribuída', function () {
    $candidatura = Candidatura::factory()->create([
        'id_coordenador' => $this->coordenador->id_utilizador,
        'id_departamento_selecionado' => $this->departamento->id_departamento,
    ]);

    $documento = criarDocumentoDaCandidatura($candidatura);

    $this->actingAs($this->coordenador, 'sanctum');

    $this->get("/api/documentos/{$documento->id_documento}")
        ->assertOk();

    expect(AcessoFicheiro::query()
        ->where('id_documento', $documento->id_documento)
        ->where('id_utilizador', $this->coordenador->id_utilizador)
        ->exists())->toBeTrue();
});

it('esconde do coordenador documentos de outra direção', function () {
    $outraDirecao = Departamento::factory()->create();

    $outroCoordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $outroCoordenador->id_utilizador,
        'id_departamento' => $outraDirecao->id_departamento,
    ]);

    $candidatura = Candidatura::factory()->create([
        'id_coordenador' => $outroCoordenador->id_utilizador,
        'id_departamento_selecionado' => $outraDirecao->id_departamento,
    ]);

    $documento = criarDocumentoDaCandidatura($candidatura);

    $this->actingAs($this->coordenador, 'sanctum');

    $this->get("/api/documentos/{$documento->id_documento}")
        ->assertStatus(403);

    expect(AcessoFicheiro::count())->toBe(0);
});

it('deixa o coordenador ver documentos de candidaturas ainda sem coordenador', function () {
    $candidatura = Candidatura::factory()->create([
        'id_coordenador' => null,
        'id_departamento_selecionado' => $this->departamento->id_departamento,
    ]);

    $documento = criarDocumentoDaCandidatura($candidatura);

    $this->actingAs($this->coordenador, 'sanctum');

    $this->get("/api/documentos/{$documento->id_documento}")
        ->assertOk();
});

it('deixa o coordenador ver documentos de candidatura presa a coordenador inativo', function () {
    $inativo = User::factory()->coordenador()->create([
        'ativo' => false,
    ]);

    Perfil::create([
        'id_utilizador' => $inativo->id_utilizador,
        'id_departamento' => $this->departamento->id_departamento,
    ]);

    $candidatura = Candidatura::factory()->create([
        'id_coordenador' => $inativo->id_utilizador,
        'id_departamento_selecionado' => $this->departamento->id_departamento,
    ]);

    $documento = criarDocumentoDaCandidatura($candidatura);

    $this->actingAs($this->coordenador, 'sanctum');

    $this->get("/api/documentos/{$documento->id_documento}")
        ->assertOk();
});

it('lista apenas os supervisores da direção do coordenador', function () {
    $daDirecao = User::factory()->supervisor()->create();

    Perfil::create([
        'id_utilizador' => $daDirecao->id_utilizador,
        'id_departamento' => $this->departamento->id_departamento,
    ]);

    $outraDirecao = Departamento::factory()->create();

    $deOutra = User::factory()->supervisor()->create();

    Perfil::create([
        'id_utilizador' => $deOutra->id_utilizador,
        'id_departamento' => $outraDirecao->id_departamento,
    ]);

    $this->actingAs($this->coordenador, 'sanctum');

    $this->getJson('/api/coordenador/supervisores')
        ->assertOk()
        ->assertJsonCount(1, 'supervisores')
        ->assertJsonPath(
            'supervisores.0.id_utilizador',
            $daDirecao->id_utilizador
        );
});

it('nega a listagem de supervisores a quem não é coordenador', function () {
    $estagiario = User::factory()->estagiario()->create();

    $this->actingAs($estagiario, 'sanctum');

    $this->getJson('/api/coordenador/supervisores')->assertStatus(403);
});
