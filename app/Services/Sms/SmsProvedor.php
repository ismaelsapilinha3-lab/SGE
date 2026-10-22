<?php

namespace App\Services\Sms;

/**
 * Contrato mínimo de um provedor de SMS.
 *
 * As implementações não devem lançar excepções: um envio falhado tem de
 * devolver um SmsResultado de falha, para que a falha do SMS nunca
 * interrompa o fluxo de negócio que o originou.
 */
interface SmsProvedor
{
    /**
     * Envia uma mensagem para o número indicado.
     *
     * O número já vem normalizado (formato internacional, ex.: +244923000111).
     */
    public function enviar(string $telefone, string $mensagem): SmsResultado;

    /**
     * Identificador do provedor, usado para auditoria.
     */
    public function nome(): string;
}
