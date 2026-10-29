<?php

use App\Models\Departamento;
use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Uma candidatura nova deve avisar os administradores ativos, para que
 * a possam encaminhar para a direção certa.
 */
function dadosCandidaturaValida(
    int $idDepartamento,
    string $email = 'candidato@exemplo.test'
): array {

    return [
        'nome' => 'Candidato Exemplo',
        'email' => $email,
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'curso' => 'Engenharia Informática',
        'universidade' => 'Universidade Teste',
        'nascimento' => '2000-01-01',
        'sexo' => 'Masculino',
        'bi' => '000000000AA000',
        'contacto' => '923000000',
        'id_departamento_selecionado' => $idDepartamento,
        'documentos' => [
            'declaracao_escolar' => UploadedFile::fake()
                ->create('declaracao.pdf', 100, 'application/pdf'),
            'foto' => UploadedFile::fake()
                ->image('foto.jpg', 100, 100),
            'bi' => UploadedFile::fake()
                ->image('bi.jpg', 100, 100),
            'carta_solicitacao' => UploadedFile::fake()
                ->create('carta.pdf', 100, 'application/pdf'),
            'cv' => UploadedFile::fake()
                ->create('cv.pdf', 100, 'application/pdf'),
        ],
    ];
}

beforeEach(function () {
    Storage::fake('local');
});

it('avisa os administradores quando entra uma candidatura nova', function () {
    $administrador = User::factory()->administrador()->create();

    $departamento = Departamento::factory()->create();

    $this->postJson(
        '/api/candidaturas',
        dadosCandidaturaValida($departamento->id_departamento)
    )->assertCreated();

    $notificacao = Notificacao::where(
        'id_utilizador',
        $administrador->id_utilizador
    )->first();

    expect($notificacao)->not->toBeNull()
        ->and($notificacao->titulo)->toBe('Nova candidatura recebida')
        ->and($notificacao->mensagem)->toContain('Candidato Exemplo')
        ->and($notificacao->url)->toBe('/admin?secao=candidaturas');
});

it('não avisa administradores inativos', function () {
    $inativo = User::factory()->administrador()->create([
        'ativo' => false,
    ]);

    $departamento = Departamento::factory()->create();

    $this->postJson(
        '/api/candidaturas',
        dadosCandidaturaValida($departamento->id_departamento)
    )->assertCreated();

    expect(
        Notificacao::where('id_utilizador', $inativo->id_utilizador)
            ->exists()
    )->toBeFalse();
});

it('não cria notificação quando a candidatura é rejeitada', function () {
    User::factory()->administrador()->create();

    $departamento = Departamento::factory()->create();

    $dados = dadosCandidaturaValida($departamento->id_departamento);

    unset($dados['documentos']['cv']);

    $this->postJson('/api/candidaturas', $dados)
        ->assertStatus(422);

    expect(Notificacao::count())->toBe(0);
});
