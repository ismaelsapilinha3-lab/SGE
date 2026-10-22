<?php

namespace App\Services\Sms\Provedores;

use App\Services\Sms\SmsProvedor;
use App\Services\Sms\SmsResultado;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Provedor para gateways SMS com API REST genérica.
 *
 * O método HTTP, o esquema de autenticação e os nomes dos campos são
 * configuráveis, para que um gateway novo seja ligado apenas pelo .env.
 */
class SmsProvedorHttp implements SmsProvedor
{
    /**
     * @param  array<string, mixed>  $camposExtra
     */
    public function __construct(
        private readonly ?string $endpoint,
        private readonly string $metodo = 'POST',
        private readonly string $autenticacao = 'bearer',
        private readonly ?string $apiKey = null,
        private readonly ?string $apiSecret = null,
        private readonly string $authHeader = 'Authorization',
        private readonly string $authScheme = 'Bearer',
        private readonly string $authQueryParam = 'api_key',
        private readonly string $campoDestino = 'to',
        private readonly string $campoMensagem = 'text',
        private readonly string $campoRemetente = 'from',
        private readonly array $camposExtra = [],
        private readonly string $campoReferencia = 'id',
        private readonly int $timeout = 10,
        private readonly string $remetente = 'MULTITEL',
    ) {}

    public function enviar(string $telefone, string $mensagem): SmsResultado
    {
        if (blank($this->endpoint)) {
            return SmsResultado::falha(
                $telefone,
                $mensagem,
                'O endpoint do provedor de SMS não está configurado.'
            );
        }

        $corpo = array_merge($this->corpoDoPedido($telefone, $mensagem), $this->camposExtra);

        $cabecalhos = [
            'Accept' => 'application/json',
        ];

        $parametros = [];

        $this->aplicarAutenticacao($cabecalhos, $parametros);

        try {
            $resposta = $this->executar($cabecalhos, $parametros, $corpo);

            if (! $resposta->successful()) {
                Log::channel('sms')->warning('Gateway de SMS respondeu com erro', [
                    'telefone' => $telefone,
                    'estado' => $resposta->status(),
                    'corpo' => $resposta->body(),
                ]);

                return SmsResultado::falha(
                    $telefone,
                    $mensagem,
                    'O gateway de SMS respondeu com estado '.$resposta->status().'.'
                );
            }

            $referencia = $this->extrairReferencia($resposta->json());

            Log::channel('sms')->info('SMS enviado', [
                'telefone' => $telefone,
                'referencia' => $referencia,
            ]);

            return SmsResultado::sucesso($telefone, $mensagem, $referencia);
        } catch (ConnectionException $excecao) {
            return SmsResultado::falha(
                $telefone,
                $mensagem,
                'Não foi possível contactar o gateway de SMS: '.$excecao->getMessage()
            );
        } catch (Throwable $excecao) {
            return SmsResultado::falha(
                $telefone,
                $mensagem,
                'Falha inesperada ao enviar o SMS: '.$excecao->getMessage()
            );
        }
    }

    public function nome(): string
    {
        return 'http';
    }

    /**
     * Monta o corpo do pedido com os nomes de campos configurados.
     *
     * @return array<string, mixed>
     */
    private function corpoDoPedido(string $telefone, string $mensagem): array
    {
        return array_filter([
            $this->campoRemetente => $this->remetente,
            $this->campoDestino => $telefone,
            $this->campoMensagem => $mensagem,
        ], fn ($valor) => filled($valor));
    }

    /**
     * Aplica o esquema de autenticação configurado.
     *
     * @param  array<string, string>  $cabecalhos
     * @param  array<string, string>  $parametros
     */
    private function aplicarAutenticacao(array &$cabecalhos, array &$parametros): void
    {
        if ($this->autenticacao === 'none' || blank($this->apiKey)) {
            return;
        }

        match ($this->autenticacao) {
            'basic' => $cabecalhos[$this->authHeader] = 'Basic '.base64_encode(
                $this->apiKey.':'.($this->apiSecret ?? '')
            ),

            'header' => $cabecalhos[$this->authHeader] = $this->apiKey,

            'query' => $parametros[$this->authQueryParam] = $this->apiKey,

            'bearer' => $cabecalhos[$this->authHeader] = trim(
                $this->authScheme.' '.$this->apiKey
            ),

            default => null,
        };
    }

    /**
     * Executa o pedido HTTP no método configurado.
     *
     * @param  array<string, string>  $cabecalhos
     * @param  array<string, string>  $parametros
     * @param  array<string, mixed>  $corpo
     */
    private function executar(
        array $cabecalhos,
        array $parametros,
        array $corpo
    ): Response {
        $pedido = $this->prepararPedido($cabecalhos, $parametros);

        return match (strtoupper($this->metodo)) {
            'GET' => $pedido->get($this->endpoint, $corpo),
            'PUT' => $pedido->put($this->endpoint, $corpo),
            'PATCH' => $pedido->patch($this->endpoint, $corpo),
            default => $pedido->post($this->endpoint, $corpo),
        };
    }

    /**
     * @param  array<string, string>  $cabecalhos
     * @param  array<string, string>  $parametros
     */
    private function prepararPedido(
        array $cabecalhos,
        array $parametros
    ): PendingRequest {
        $pedido = Http::withHeaders($cabecalhos)->timeout($this->timeout);

        if ($parametros !== []) {
            $pedido = $pedido->withQueryParameters($parametros);
        }

        return $pedido;
    }

    /**
     * Extrai o identificador do envio a partir do corpo da resposta.
     */
    private function extrairReferencia(mixed $corpo): ?string
    {
        if (! is_array($corpo)) {
            return null;
        }

        $candidatos = array_unique([
            $this->campoReferencia,
            'id',
            'messageId',
            'message_id',
            'reference',
            'sid',
        ]);

        foreach ($candidatos as $chave) {
            if (isset($corpo[$chave]) && is_scalar($corpo[$chave])) {
                return (string) $corpo[$chave];
            }
        }

        return null;
    }
}
