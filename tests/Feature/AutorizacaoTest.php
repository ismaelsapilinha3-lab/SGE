<?php

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Documento;
use App\Models\Estagio;
use App\Models\Perfil;
use App\Models\PlanoEstagio;
use App\Models\Relatorio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Candidatura com um documento guardado no disco local.
 */
function candidaturaComDocumentoParaAutorizacao(
    ?Departamento $departamento = null,
    ?User $coordenador = null,
    ?User $estagiario = null
): array {
    $departamento ??= Departamento::factory()->create();

    $estagiario ??= User::factory()->estagiario()->create();

    $candidatura = Candidatura::factory()->create([
        'id_utilizador' => $estagiario->id_utilizador,
        'id_departamento_selecionado' => $departamento->id_departamento,
        'id_coordenador' => $coordenador?->id_utilizador,
    ]);

    $caminho = "documentos/{$candidatura->id_candidatura}/bi.pdf";

    Storage::disk('local')->put($caminho, 'conteudo');

    $documento = Documento::create([
        'id_candidatura' => $candidatura->id_candidatura,
        'tipo_documento' => 'bi',
        'versao' => 1,
        'nome_arquivo' => 'bi.pdf',
        'caminho_arquivo' => $caminho,
        'data_upload' => now(),
        'estado' => 'pendente',
    ]);

    return [$candidatura, $documento, $estagiario];
}

/**
 * Estágio com relatório e/ou plano oficial guardados no disco local.
 */
function estagioComFicheirosParaAutorizacao(): array
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

    $relatorio = Relatorio::factory()->submetido()->create([
        'id_estagio' => $estagio->id_estagio,
        'ficheiro_path' => "relatorios/{$estagio->id_estagio}.pdf",
        'ficheiro_nome' => 'relatorio.pdf',
    ]);

    Storage::disk('local')->put($relatorio->ficheiro_path, 'conteudo');

    $plano = PlanoEstagio::factory()->doAdministrador()->create([
        'id_estagio' => $estagio->id_estagio,
    ]);

    Storage::disk('local')->put($plano->caminho_arquivo, 'plano');

    return [$estagio, $estagiario, $supervisor, $relatorio, $plano];
}

beforeEach(function () {
    Storage::fake('local');
});

it('impede um estagiário de consultar o documento de outra candidatura', function () {
    [$candidatura] = candidaturaComDocumentoParaAutorizacao();

    $outroEstagiario = User::factory()->estagiario()->create();

    $documento = $candidatura->documentos()->first();

    $this->actingAs($outroEstagiario, 'sanctum')
        ->getJson("/api/documentos/{$documento->id_documento}")
        ->assertStatus(403);
});

it('impede um supervisor de consultar documentos de candidatura', function () {
    [$candidatura] = candidaturaComDocumentoParaAutorizacao();

    $supervisor = User::factory()->supervisor()->create();

    $documento = $candidatura->documentos()->first();

    $this->actingAs($supervisor, 'sanctum')
        ->getJson("/api/documentos/{$documento->id_documento}")
        ->assertStatus(403);
});

it('impede um coordenador de outra direção de consultar o documento', function () {
    $departamento = Departamento::factory()->create();

    $outroCoordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $outroCoordenador->id_utilizador,
        'id_departamento' => Departamento::factory()->create()->id_departamento,
    ]);

    [$candidatura] = candidaturaComDocumentoParaAutorizacao($departamento);

    $documento = $candidatura->documentos()->first();

    $this->actingAs($outroCoordenador, 'sanctum')
        ->getJson("/api/documentos/{$documento->id_documento}")
        ->assertStatus(403);
});

it('deixa o administrador consultar documentos de qualquer direção', function () {
    [$candidatura] = candidaturaComDocumentoParaAutorizacao();

    $administrador = User::factory()->administrador()->create();

    $documento = $candidatura->documentos()->first();

    $this->actingAs($administrador, 'sanctum')
        ->getJson("/api/documentos/{$documento->id_documento}")
        ->assertOk();
});

it('impede um estagiário de substituir o documento de outra candidatura', function () {
    [$candidatura] = candidaturaComDocumentoParaAutorizacao();

    $outroEstagiario = User::factory()->estagiario()->create();

    $documento = $candidatura->documentos()->first();

    $this->actingAs($outroEstagiario, 'sanctum')
        ->postJson("/api/documentos/{$documento->id_documento}/substituir", [
            'ficheiro' => UploadedFile::fake()
                ->create('novo-bi.pdf', 100, 'application/pdf'),
        ])
        ->assertStatus(403);
});

it('impede um estagiário de consultar o relatório de outro estágio', function () {
    [, , , $relatorio] = estagioComFicheirosParaAutorizacao();

    $outroEstagiario = User::factory()->estagiario()->create();

    $this->actingAs($outroEstagiario, 'sanctum')
        ->getJson("/api/estagiario/relatorios/{$relatorio->id_relatorio}/ficheiro")
        ->assertStatus(403);
});

it('deixa o supervisor consultar o relatório do seu estágio', function () {
    [, , $supervisor, $relatorio] = estagioComFicheirosParaAutorizacao();

    $this->actingAs($supervisor, 'sanctum')
        ->getJson("/api/estagiario/relatorios/{$relatorio->id_relatorio}/ficheiro")
        ->assertOk();
});

it('impede um coordenador de outra direção de consultar o relatório', function () {
    [, , , $relatorio] = estagioComFicheirosParaAutorizacao();

    $outroCoordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $outroCoordenador->id_utilizador,
        'id_departamento' => Departamento::factory()->create()->id_departamento,
    ]);

    $this->actingAs($outroCoordenador, 'sanctum')
        ->getJson("/api/estagiario/relatorios/{$relatorio->id_relatorio}/ficheiro")
        ->assertStatus(403);
});

it('impede um estagiário de consultar o plano de outro estágio', function () {
    [, , , , $plano] = estagioComFicheirosParaAutorizacao();

    $outroEstagiario = User::factory()->estagiario()->create();

    $this->actingAs($outroEstagiario, 'sanctum')
        ->getJson("/api/estagiario/planos/{$plano->id_plano_estagio}/ficheiro")
        ->assertStatus(403);
});
