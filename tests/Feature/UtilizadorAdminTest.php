<?php

use App\Models\Estagio;
use App\Models\User;

function adminDeUtilizadores(): User
{
    return User::factory()->administrador()->create();
}

it('elimina logicamente um utilizador e revoga os tokens', function () {
    $admin = adminDeUtilizadores();
    $estagiario = User::factory()->estagiario()->create();
    $token = $estagiario->createToken('sessao-activa');

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/utilizadores/{$estagiario->id_utilizador}")
        ->assertOk()
        ->assertJsonPath('message', 'Utilizador eliminado com sucesso.');

    $this->assertSoftDeleted('utilizadores', [
        'id_utilizador' => $estagiario->id_utilizador,
    ]);

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->accessToken->id,
    ]);
});

it('esconde os utilizadores eliminados da listagem', function () {
    $admin = adminDeUtilizadores();
    $estagiario = User::factory()->estagiario()->create([
        'nome' => 'Conta Removida',
    ]);

    $estagiario->delete();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/utilizadores?pesquisa=Conta+Removida')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('devolve 404 ao consultar um utilizador eliminado', function () {
    $admin = adminDeUtilizadores();
    $estagiario = User::factory()->estagiario()->create();

    $estagiario->delete();

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/utilizadores/{$estagiario->id_utilizador}")
        ->assertNotFound();
});

it('impede o login de um utilizador eliminado', function () {
    $estagiario = User::factory()->estagiario()->create();

    $estagiario->delete();

    $this->postJson('/api/login', [
        'email' => $estagiario->email,
        'password' => 'password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('impede o administrador de eliminar a própria conta', function () {
    $admin = adminDeUtilizadores();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/utilizadores/{$admin->id_utilizador}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('utilizador');

    $this->assertNotSoftDeleted('utilizadores', [
        'id_utilizador' => $admin->id_utilizador,
    ]);
});

it('impede eliminar uma conta de administrador', function () {
    $admin = adminDeUtilizadores();
    $outroAdmin = User::factory()->administrador()->create();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/utilizadores/{$outroAdmin->id_utilizador}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('utilizador');

    $this->assertNotSoftDeleted('utilizadores', [
        'id_utilizador' => $outroAdmin->id_utilizador,
    ]);
});

it('impede outros perfis de eliminar utilizadores', function (string $perfil) {
    $utilizador = User::factory()->{$perfil}()->create();
    $alvo = User::factory()->estagiario()->create();

    $this->actingAs($utilizador, 'sanctum')
        ->deleteJson("/api/admin/utilizadores/{$alvo->id_utilizador}")
        ->assertForbidden();

    $this->assertNotSoftDeleted('utilizadores', [
        'id_utilizador' => $alvo->id_utilizador,
    ]);
})->with([
    'coordenador',
    'supervisor',
    'estagiario',
]);

it('devolve 404 ao eliminar duas vezes o mesmo utilizador', function () {
    $admin = adminDeUtilizadores();
    $estagiario = User::factory()->estagiario()->create();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/utilizadores/{$estagiario->id_utilizador}")
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/utilizadores/{$estagiario->id_utilizador}")
        ->assertNotFound();
});

it('preserva o estágio do utilizador eliminado', function () {
    $admin = adminDeUtilizadores();
    $estagiario = User::factory()->estagiario()->create();

    $estagio = Estagio::factory()->create([
        'id_estagiario' => $estagiario->id_utilizador,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/admin/utilizadores/{$estagiario->id_utilizador}")
        ->assertOk();

    $this->assertDatabaseHas('estagios', [
        'id_estagio' => $estagio->id_estagio,
        'id_estagiario' => $estagiario->id_utilizador,
    ]);
});
