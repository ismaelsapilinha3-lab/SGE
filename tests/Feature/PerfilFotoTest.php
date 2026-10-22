<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Cada utilizador carrega a sua própria foto de perfil
|--------------------------------------------------------------------------
|
| A foto é guardada no disco público e o URL é devolvido para o portal
| mostrar o avatar em vez da inicial.
|
*/

beforeEach(function () {
    Storage::fake('public');
});

it('devolve o perfil do utilizador autenticado sem foto', function () {
    $utilizador = User::factory()->estagiario()->create();

    $this->actingAs($utilizador, 'sanctum')
        ->getJson('/api/perfil')
        ->assertOk()
        ->assertJsonPath('utilizador.id_utilizador', $utilizador->id_utilizador)
        ->assertJsonPath('utilizador.foto', null)
        ->assertJsonPath('utilizador.foto_url', null);
});

it('exige autenticação para consultar o perfil', function () {
    $this->getJson('/api/perfil')->assertUnauthorized();
});

it('guarda a foto de perfil no disco público', function () {
    $utilizador = User::factory()->estagiario()->create();

    $this->actingAs($utilizador, 'sanctum')
        ->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('perfil.jpg', 200, 200),
        ])
        ->assertOk()
        ->assertJsonPath('foto_url', fn ($url) => is_string($url) && $url !== '');

    $utilizador->refresh();

    expect($utilizador->foto)->not->toBeNull();

    Storage::disk('public')->assertExists($utilizador->foto);
});

it('remove a foto anterior quando é carregada uma nova', function () {
    $utilizador = User::factory()->estagiario()->create();

    $this->actingAs($utilizador, 'sanctum')
        ->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('primeira.jpg', 100, 100),
        ])
        ->assertOk();

    $anterior = $utilizador->fresh()->foto;

    $this->actingAs($utilizador, 'sanctum')
        ->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('segunda.jpg', 100, 100),
        ])
        ->assertOk();

    $nova = $utilizador->fresh()->foto;

    expect($nova)->not->toBe($anterior);

    Storage::disk('public')->assertMissing($anterior);
    Storage::disk('public')->assertExists($nova);
});

it('rejeita ficheiros que não sejam imagem', function () {
    $utilizador = User::factory()->estagiario()->create();

    $this->actingAs($utilizador, 'sanctum')
        ->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->create('curriculo.pdf', 100, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('foto');
});

it('remove a foto de perfil e apaga o ficheiro', function () {
    $utilizador = User::factory()->estagiario()->create();

    $this->actingAs($utilizador, 'sanctum')
        ->postJson('/api/perfil/foto', [
            'foto' => UploadedFile::fake()->image('perfil.jpg', 100, 100),
        ])
        ->assertOk();

    $caminho = $utilizador->fresh()->foto;

    $this->actingAs($utilizador, 'sanctum')
        ->deleteJson('/api/perfil/foto')
        ->assertOk()
        ->assertJsonPath('foto', null)
        ->assertJsonPath('foto_url', null);

    expect($utilizador->fresh()->foto)->toBeNull();

    Storage::disk('public')->assertMissing($caminho);
});

it('devolve o URL da foto no login', function () {
    $utilizador = User::factory()->estagiario()->create([
        'password' => 'Password123',
        'foto' => 'perfis/exemplo.jpg',
    ]);

    Storage::disk('public')->put('perfis/exemplo.jpg', 'conteudo');

    $this->postJson('/api/login', [
        'email' => $utilizador->email,
        'password' => 'Password123',
    ])
        ->assertOk()
        ->assertJsonPath('user.id_utilizador', $utilizador->id_utilizador)
        ->assertJsonPath('user.foto_url', fn ($url) => is_string($url) && $url !== '');
});
