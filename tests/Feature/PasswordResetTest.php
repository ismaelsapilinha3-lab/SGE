<?php

use App\Models\User;
use App\Notifications\RecuperarPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Extrai o token do link enviado na notificação de recuperação.
 */
function tokenDoLinkDeRecuperacao(User $utilizador): string
{
    $token = null;

    Notification::assertSentTo(
        $utilizador,
        RecuperarPassword::class,
        function (RecuperarPassword $notificacao) use (&$token): bool {
            parse_str(
                (string) parse_url($notificacao->url, PHP_URL_QUERY),
                $parametros
            );

            $token = $parametros['token'] ?? null;

            return true;
        }
    );

    expect($token)->toBeString()->not->toBeEmpty();

    return $token;
}

it('envia um link de recuperação para um e-mail registado', function () {
    Notification::fake();

    $utilizador = User::factory()->create();

    $this->postJson('/api/password/forgot', [
        'email' => $utilizador->email,
    ])->assertOk();

    Notification::assertSentTo($utilizador, RecuperarPassword::class);

    expect(tokenDoLinkDeRecuperacao($utilizador))->not->toBeEmpty();

    $this->assertDatabaseHas('password_reset_tokens', [
        'email' => $utilizador->email,
    ]);
});

it('não revela se o e-mail existe', function () {
    Notification::fake();

    $respostaDesconhecido = $this->postJson('/api/password/forgot', [
        'email' => 'nao-existe@example.com',
    ]);

    $utilizador = User::factory()->create();

    $respostaConhecido = $this->postJson('/api/password/forgot', [
        'email' => $utilizador->email,
    ]);

    $respostaDesconhecido->assertOk();
    $respostaConhecido->assertOk();

    expect($respostaDesconhecido->json('message'))
        ->toBe($respostaConhecido->json('message'));

    Notification::assertSentTo($utilizador, RecuperarPassword::class);
    Notification::assertCount(1);
});

it('não envia o link para uma conta desativada', function () {
    Notification::fake();

    $utilizador = User::factory()->create([
        'ativo' => false,
    ]);

    $this->postJson('/api/password/forgot', [
        'email' => $utilizador->email,
    ])->assertOk();

    Notification::assertNotSentTo($utilizador, RecuperarPassword::class);
});

it('redefine a palavra-passe com um token válido e revoga as sessões', function () {
    Notification::fake();

    $utilizador = User::factory()->create();

    $this->postJson('/api/password/forgot', [
        'email' => $utilizador->email,
    ])->assertOk();

    $token = tokenDoLinkDeRecuperacao($utilizador);

    $utilizador->createToken('sessao-antiga');

    $this->postJson('/api/password/reset', [
        'token' => $token,
        'email' => $utilizador->email,
        'password' => 'NovaPalavra123',
        'password_confirmation' => 'NovaPalavra123',
    ])->assertOk();

    expect(Hash::check('NovaPalavra123', $utilizador->fresh()->password))
        ->toBeTrue();

    expect($utilizador->tokens()->count())->toBe(0);

    $this->assertDatabaseMissing('password_reset_tokens', [
        'email' => $utilizador->email,
    ]);

    $this->postJson('/api/login', [
        'email' => $utilizador->email,
        'password' => 'NovaPalavra123',
    ])->assertOk()->assertJsonStructure(['token', 'user']);

    $this->postJson('/api/login', [
        'email' => $utilizador->email,
        'password' => 'password',
    ])->assertStatus(422);
});

it('rejeita um token inválido sem alterar a palavra-passe', function () {
    $utilizador = User::factory()->create();

    $this->postJson('/api/password/reset', [
        'token' => 'token-invalido',
        'email' => $utilizador->email,
        'password' => 'NovaPalavra123',
        'password_confirmation' => 'NovaPalavra123',
    ])->assertStatus(422);

    expect(Hash::check('password', $utilizador->fresh()->password))
        ->toBeTrue();
});

it('rejeita uma palavra-passe fraca', function () {
    Notification::fake();

    $utilizador = User::factory()->create();

    $this->postJson('/api/password/forgot', [
        'email' => $utilizador->email,
    ])->assertOk();

    $token = tokenDoLinkDeRecuperacao($utilizador);

    $this->postJson('/api/password/reset', [
        'token' => $token,
        'email' => $utilizador->email,
        'password' => '12345678',
        'password_confirmation' => '12345678',
    ])->assertStatus(422);

    expect(Hash::check('password', $utilizador->fresh()->password))
        ->toBeTrue();
});

it('exige a confirmação da palavra-passe', function () {
    Notification::fake();

    $utilizador = User::factory()->create();

    $this->postJson('/api/password/forgot', [
        'email' => $utilizador->email,
    ])->assertOk();

    $token = tokenDoLinkDeRecuperacao($utilizador);

    $this->postJson('/api/password/reset', [
        'token' => $token,
        'email' => $utilizador->email,
        'password' => 'NovaPalavra123',
        'password_confirmation' => 'OutraPalavra123',
    ])->assertStatus(422);
});
