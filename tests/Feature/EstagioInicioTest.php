<?php

use App\Models\Estagio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{0: User, 1: Estagio}
 */
function estagiarioComEstagioAPartirDe(string $inicio): array
{
    $estagiario = User::factory()->estagiario()->create();

    $estagio = Estagio::factory()->create([
        'id_estagiario' => $estagiario->id_utilizador,
        'data_inicio' => $inicio,
        'data_fim_prevista' => Carbon::parse($inicio)
            ->addMonths(6)
            ->format('Y-m-d'),
    ]);

    return [$estagiario, $estagio];
}

it('bloqueia a área reservada antes da data de início', function () {
    $inicio = now()->addDays(5)->format('Y-m-d');

    [$estagiario] = estagiarioComEstagioAPartirDe($inicio);

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/estagio-ativo')
        ->assertForbidden()
        ->assertJsonPath('message', 'O estágio ainda não começou.')
        ->assertJsonPath('data_inicio', $inicio);

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/painel')
        ->assertForbidden()
        ->assertJsonPath('message', 'O estágio ainda não começou.');
});

it('liberta a área reservada no próprio dia de início', function () {
    [$estagiario] = estagiarioComEstagioAPartirDe(
        now()->format('Y-m-d')
    );

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/estagio-ativo')
        ->assertOk();

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/painel')
        ->assertOk();
});

it('liberta a área reservada depois da data de início', function () {
    [$estagiario] = estagiarioComEstagioAPartirDe(
        now()->subMonth()->format('Y-m-d')
    );

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/estagio-ativo')
        ->assertOk();
});

it('mantém a consulta do estágio disponível antes do início', function () {
    [$estagiario] = estagiarioComEstagioAPartirDe(
        now()->addDays(10)->format('Y-m-d')
    );

    $this->actingAs($estagiario, 'sanctum')
        ->getJson('/api/estagiario/estagio')
        ->assertOk()
        ->assertJsonStructure([
            'estagio' => [
                'data_inicio',
                'data_fim_prevista',
            ],
        ]);
});

it('bloqueia a criação de relatórios antes do início', function () {
    [$estagiario] = estagiarioComEstagioAPartirDe(
        now()->addDays(3)->format('Y-m-d')
    );

    $this->actingAs($estagiario, 'sanctum')
        ->postJson('/api/estagiario/estagio/relatorios', [])
        ->assertForbidden()
        ->assertJsonPath('message', 'O estágio ainda não começou.');
});

it('permite enviar o contrato antes do início', function () {
    Storage::fake('local');

    [$estagiario] = estagiarioComEstagioAPartirDe(
        now()->addDays(7)->format('Y-m-d')
    );

    $this->actingAs($estagiario, 'sanctum')
        ->post(
            '/api/estagiario/estagio/contrato',
            [
                'ficheiro' => UploadedFile::fake()->create(
                    'contrato.pdf',
                    100,
                    'application/pdf'
                ),
            ],
            ['Accept' => 'application/json']
        )
        ->assertCreated()
        ->assertJsonPath('message', 'Contrato carregado com sucesso.');
});
