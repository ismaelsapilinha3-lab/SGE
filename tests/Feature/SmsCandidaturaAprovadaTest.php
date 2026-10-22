<?php

use App\Models\Candidatura;
use App\Models\Departamento;
use App\Models\Perfil;
use App\Models\SmsEnvio;
use App\Models\User;
use App\Services\Sms\SmsProvedor;
use App\Services\Sms\SmsResultado;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\Http;

/**
 * Cria um coordenador ativo associado a uma direção.
 */
function criarCoordenadorParaSms(int $idDepartamento): User
{
    $coordenador = User::factory()->coordenador()->create();

    Perfil::create([
        'id_utilizador' => $coordenador->id_utilizador,
        'id_departamento' => $idDepartamento,
    ]);

    return $coordenador;
}

/**
 * Cria uma candidatura em análise, atribuída ao coordenador.
 */
function criarCandidaturaParaSms(
    int $idDepartamento,
    User $coordenador,
    array $atributos = []
): Candidatura {
    return Candidatura::factory()->create(array_merge([
        'estado' => 'em_analise',
        'id_departamento_selecionado' => $idDepartamento,
        'id_coordenador' => $coordenador->id_utilizador,
    ], $atributos));
}

describe('aviso de aceitação por SMS', function () {

    beforeEach(function () {
        $this->departamento = Departamento::factory()->create();

        $this->coordenador = criarCoordenadorParaSms(
            $this->departamento->id_departamento
        );

        $this->actingAs($this->coordenador, 'sanctum');
    });

    it('envia o SMS de parabéns quando a candidatura é aceite', function () {
        $candidatura = criarCandidaturaParaSms(
            $this->departamento->id_departamento,
            $this->coordenador,
            ['nome' => 'Judith Kimfomo', 'contacto' => '927722699']
        );

        $resposta = $this->patchJson(
            "/api/coordenador/candidaturas/{$candidatura->id_candidatura}/aprovar"
        );

        $resposta->assertOk();

        $this->assertDatabaseHas('sms_envios', [
            'id_candidatura' => $candidatura->id_candidatura,
            'telefone' => '+244927722699',
            'estado' => 'enviado',
            'provedor' => 'simulado',
        ]);

        $envio = SmsEnvio::where('id_candidatura', $candidatura->id_candidatura)
            ->firstOrFail();

        expect($envio->mensagem)
            ->toContain('Parabéns, Judith')
            ->toContain('MULTITEL')
            ->toContain('aceite');

        expect($envio->enviado_em)->not->toBeNull();
    });

    it('não envia SMS quando a candidatura já foi decidida', function () {
        $candidatura = criarCandidaturaParaSms(
            $this->departamento->id_departamento,
            $this->coordenador,
            ['estado' => 'recusada', 'motivo_recusa' => 'Perfil não compatível.']
        );

        $this->patchJson(
            "/api/coordenador/candidaturas/{$candidatura->id_candidatura}/aprovar"
        )->assertStatus(422);

        $this->assertDatabaseCount('sms_envios', 0);
    });

    it('não envia SMS quando a candidatura não pertence ao coordenador', function () {
        $candidatura = criarCandidaturaParaSms(
            $this->departamento->id_departamento,
            $this->coordenador,
            ['id_coordenador' => null]
        );

        $this->patchJson(
            "/api/coordenador/candidaturas/{$candidatura->id_candidatura}/aprovar"
        )->assertNotFound();

        $this->assertDatabaseCount('sms_envios', 0);
    });

    it('não regista envio quando o envio de SMS está desactivado', function () {
        config(['sms.enabled' => false]);

        $candidatura = criarCandidaturaParaSms(
            $this->departamento->id_departamento,
            $this->coordenador
        );

        $this->patchJson(
            "/api/coordenador/candidaturas/{$candidatura->id_candidatura}/aprovar"
        )->assertOk();

        $this->assertDatabaseCount('sms_envios', 0);
    });

    it('aprova a candidatura mesmo quando o envio do SMS falha', function () {
        config(['sms.provider' => 'http', 'sms.endpoint' => 'https://sms.test/envio']);

        Http::fake([
            '*' => Http::response(['erro' => 'gateway indisponível'], 500),
        ]);

        $candidatura = criarCandidaturaParaSms(
            $this->departamento->id_departamento,
            $this->coordenador
        );

        $this->patchJson(
            "/api/coordenador/candidaturas/{$candidatura->id_candidatura}/aprovar"
        )->assertOk();

        expect($candidatura->fresh()->estado)->toBe('aprovada');

        $this->assertDatabaseHas('sms_envios', [
            'id_candidatura' => $candidatura->id_candidatura,
            'estado' => 'falhado',
        ]);
    });
});

describe('normalização do telefone', function () {

    it('converte os números guardados sem prefixo para o formato internacional', function () {
        $servico = app(SmsService::class);

        expect($servico->normalizarTelefone('927722699'))->toBe('+244927722699')
            ->and($servico->normalizarTelefone('923 000 111'))->toBe('+244923000111')
            ->and($servico->normalizarTelefone('+244923000111'))->toBe('+244923000111')
            ->and($servico->normalizarTelefone('00244923000111'))->toBe('+244923000111')
            ->and($servico->normalizarTelefone('12345'))->toBeNull()
            ->and($servico->normalizarTelefone(''))->toBeNull()
            ->and($servico->normalizarTelefone(null))->toBeNull();
    });

    it('ignora o envio quando o telefone não é válido', function () {
        $servico = app(SmsService::class);

        $resultado = $servico->enviar('123', 'Olá');

        expect($resultado->enviado)->toBeFalse()
            ->and($resultado->telefoneInvalido())->toBeTrue();

        $this->assertDatabaseCount('sms_envios', 0);
    });
});

describe('provedores de SMS', function () {

    it('envia através da API REST genérica', function () {
        config([
            'sms.provider' => 'http',
            'sms.endpoint' => 'https://sms.test/envio',
            'sms.api_key' => 'chave-de-teste',
        ]);

        Http::fake([
            '*' => Http::response(['id' => 'envio-123'], 200),
        ]);

        $servico = app(SmsService::class);

        $resultado = $servico->enviar('927722699', 'Parabéns!');

        expect($resultado->enviado)->toBeTrue()
            ->and($resultado->referencia)->toBe('envio-123');

        Http::assertSent(function ($pedido) {
            return $pedido->url() === 'https://sms.test/envio'
                && $pedido['to'] === '+244927722699'
                && $pedido['text'] === 'Parabéns!';
        });
    });

    it('regista a falha quando a API REST responde com erro', function () {
        config([
            'sms.provider' => 'http',
            'sms.endpoint' => 'https://sms.test/envio',
        ]);

        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $resultado = app(SmsService::class)->enviar('927722699', 'Parabéns!');

        expect($resultado->enviado)->toBeFalse()
            ->and($resultado->erro)->toContain('500');
    });

    it('usa o provedor indicado na configuração', function () {
        config(['sms.provider' => 'simulado']);

        expect(app(SmsProvedor::class)->nome())->toBe('simulado');
    });
});

describe('resultado do envio', function () {

    it('distingue sucesso de falha', function () {
        $sucesso = SmsResultado::sucesso('+244923000111', 'Olá', 'ref-1');
        $falha = SmsResultado::falha('+244923000111', 'Olá', 'erro');

        expect($sucesso->enviado)->toBeTrue()
            ->and($sucesso->referencia)->toBe('ref-1')
            ->and($falha->enviado)->toBeFalse()
            ->and($falha->erro)->toBe('erro');
    });
});
