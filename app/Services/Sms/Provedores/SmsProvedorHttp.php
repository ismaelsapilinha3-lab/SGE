<?php

namespace App\Services\Sms\Provedores;

use App\Services\Sms\SmsProvedor;
use App\Services\Sms\SmsResultado;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Provedor para gateways SMS com API REST genérica.
 *
 * Envia um POST JSON com o número, o texto e o remetente. A autenticação é
 * configurável porque cada gateway usa o seu próprio esquema.
 */
class SmsProvedorHttp implements SmsProvedor
{
    /**
     * @param  array<string, mixed>  $opcoes
     */
    public function __construct(
        private readonly ?string $endpoint,
        private readonly ?string $apiKey,
        private readonly ?string $apiSecret,
        private readonly string $authHeader = 'Authorization',
        private readonly string $authScheme = 'Bearer',
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

        $cabecalhos = [
            'Accept' => 'application/json',
        ];

        $autorizacao = $this->cabecalhoAutorizacao();

        if ($autorizacao !== null) {
            $cabecalhos[$this->authHeader] = $autorizacao;
        }

        try {
            $resposta = Http::withHeaders($cabecalhos)
                ->timeout($this->timeout)
                ->post($this->endpoint, [
                    'from' => $this->remetente,
                    'to' => $telefone,
                    'text' => $mensagem,
                    'message' => $mensagem,
                ]);

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
     * Monta o valor do cabeçalho de autorização, quando há credenciais.
     */
    private function cabecalhoAutorizacao(): ?string
    {
        if (filled($this->apiKey) && filled($this->apiSecret)) {
            return 'Basic '.base64_encode($this->apiKey.':'.$this->apiSecret);
        }

        if (filled($this->apiKey)) {
            return trim($this->authScheme.' '.$this->apiKey);
        }

        return null;
    }

    /**
     * Extrai o identificador do envio a partir do corpo da resposta.
     */
    private function extrairReferencia(mixed $corpo): ?string
    {
        if (! is_array($corpo)) {
            return null;
        }

        foreach (['id', 'messageId', 'message_id', 'reference', 'sid'] as $chave) {
            if (isset($corpo[$chave]) && is_scalar($corpo[$chave])) {
                return (string) $corpo[$chave];
            }
        }

        return null;
    }
}
