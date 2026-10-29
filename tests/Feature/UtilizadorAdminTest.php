<?php

use App\Models\Departamento;
use App\Models\Estagio;
use App\Models\Perfil;
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

it('cria um estagiario com direcao associada', function () {
    $admin = adminDeUtilizadores();
    $departamento = Departamento::factory()->create();

    $resposta = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/utilizadores', [
            'nome' => 'Estagiário de Teste',
            'email' => 'estagiario.teste@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'tipo_utilizador' => 'estagiario',
            'id_departamento' => $departamento->id_departamento,
        ]);

    $resposta->assertCreated()
        ->assertJsonPath('utilizador.tipo_utilizador', 'estagiario')
        ->assertJsonPath(
            'utilizador.id_departamento',
            $departamento->id_departamento
        )
        ->assertJsonPath(
            'utilizador.departamento',
            $departamento->nome
        );

    $this->assertDatabaseHas('perfis', [
        'id_utilizador' => $resposta->json('utilizador.id_utilizador'),
        'id_departamento' => $departamento->id_departamento,
    ]);
});

it('exige uma direcao ao criar um estagiario', function () {
    $admin = adminDeUtilizadores();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/utilizadores', [
            'nome' => 'Estagiário sem Direção',
            'email' => 'sem.direcao@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'tipo_utilizador' => 'estagiario',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_departamento');
});

it('atribui uma direcao a um estagiario existente', function () {
    $admin = adminDeUtilizadores();
    $departamento = Departamento::factory()->create();
    $estagiario = User::factory()->estagiario()->create();

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/admin/utilizadores/{$estagiario->id_utilizador}", [
            'tipo_utilizador' => 'estagiario',
            'id_departamento' => $departamento->id_departamento,
        ])
        ->assertOk()
        ->assertJsonPath(
            'utilizador.id_departamento',
            $departamento->id_departamento
        )
        ->assertJsonPath(
            'utilizador.departamento',
            $departamento->nome
        );

    $this->assertDatabaseHas('perfis', [
        'id_utilizador' => $estagiario->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);
});

it('filtra os utilizadores pela direcao', function () {
    $admin = adminDeUtilizadores();
    $departamento = Departamento::factory()->create();
    $outroDepartamento = Departamento::factory()->create();

    $esperado = User::factory()->estagiario()->create([
        'nome' => 'Estagiario da Direcao',
    ]);

    Perfil::create([
        'id_utilizador' => $esperado->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $excluido = User::factory()->estagiario()->create([
        'nome' => 'Estagiario de Outra Direcao',
    ]);

    Perfil::create([
        'id_utilizador' => $excluido->id_utilizador,
        'id_departamento' => $outroDepartamento->id_departamento,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson(
            '/api/admin/utilizadores?id_departamento='
            .$departamento->id_departamento
        )
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.id_utilizador',
            $esperado->id_utilizador
        );
});

it('rejeita o filtro por uma direcao inexistente', function () {
    $admin = adminDeUtilizadores();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/utilizadores?id_departamento=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_departamento');
});

it('lista a direcao associada ao estagiario', function () {
    $admin = adminDeUtilizadores();
    $departamento = Departamento::factory()->create();
    $estagiario = User::factory()->estagiario()->create([
        'nome' => 'Estagiario com Direcao',
    ]);

    Perfil::create([
        'id_utilizador' => $estagiario->id_utilizador,
        'id_departamento' => $departamento->id_departamento,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/utilizadores?pesquisa=Estagiario+com+Direcao')
        ->assertOk()
        ->assertJsonPath(
            'data.0.id_departamento',
            $departamento->id_departamento
        )
        ->assertJsonPath(
            'data.0.departamento',
            $departamento->nome
        );
});
