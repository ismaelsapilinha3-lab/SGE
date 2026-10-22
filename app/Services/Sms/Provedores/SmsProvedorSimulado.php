<?php

namespace App\Services\Sms\Provedores;

use App\Services\Sms\SmsProvedor;
use App\Services\Sms\SmsResultado;
use Illuminate\Support\Facades\Log;

/**
 * Provedor de teste: não faz chamadas externas.
 *
 * O envio é apenas registado no log. É o provedor por omissão enquanto o
 * gateway oficial da MULTITEL não estiver ligado, para que o fluxo completo
 * possa ser testado sem custo.
 */
class SmsProvedorSimulado implements SmsProvedor
{
    public function enviar(string $telefone, string $mensagem): SmsResultado
    {
        Log::channel('sms')->info('SMS simulado', [
            'telefone' => $telefone,
            'mensagem' => $mensagem,
        ]);

        return SmsResultado::sucesso(
            $telefone,
            $mensagem,
            'simulado-'.now()->format('YmdHis')
        );
    }

    public function nome(): string
    {
        return 'simulado';
    }
}
