<?php

namespace App\Services\Sms\Provedores;

use App\Services\Sms\SmsProvedor;
use App\Services\Sms\SmsResultado;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Provedor da Twilio.
 *
 * A Twilio aceita credenciais de teste: com o número mágico de testes
 * nenhum SMS real é entregue, o que permite validar o fluxo sem custo.
 */
class SmsProvedorTwilio implements SmsProvedor
{
    public function __construct(
        private readonly ?string $sid,
        private readonly ?string $token,
        private readonly ?string $remetente,
        private readonly int $timeout = 10,
    ) {}

    public function enviar(string $telefone, string $mensagem): SmsResultado
    {
        if (blank($this->sid) || blank($this->token)) {
            return SmsResultado::falha(
                $telefone,
                $mensagem,
                'As credenciais da Twilio não estão configuradas.'
            );
        }

        $url = sprintf(
            'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
            $this->sid
        );

        try {
            $resposta = Http::asForm()
                ->withBasicAuth($this->sid, $this->token)
                ->timeout($this->timeout)
                ->post($url, [
                    'To' => $telefone,
                    'From' => $this->remetente ?: config('sms.from'),
                    'Body' => $mensagem,
                ]);

            if (! $resposta->successful()) {
                Log::channel('sms')->warning('Twilio respondeu com erro', [
                    'telefone' => $telefone,
                    'estado' => $resposta->status(),
                    'corpo' => $resposta->body(),
                ]);

                return SmsResultado::falha(
                    $telefone,
                    $mensagem,
                    'A Twilio respondeu com estado '.$resposta->status().'.'
                );
            }

            $corpo = $resposta->json();
            $referencia = is_array($corpo) && isset($corpo['sid'])
                ? (string) $corpo['sid']
                : null;

            Log::channel('sms')->info('SMS enviado pela Twilio', [
                'telefone' => $telefone,
                'referencia' => $referencia,
            ]);

            return SmsResultado::sucesso($telefone, $mensagem, $referencia);
        } catch (ConnectionException $excecao) {
            return SmsResultado::falha(
                $telefone,
                $mensagem,
                'Não foi possível contactar a Twilio: '.$excecao->getMessage()
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
        return 'twilio';
    }
}
