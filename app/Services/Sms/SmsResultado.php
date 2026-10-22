<?php

namespace App\Services\Sms;

/**
 * Resultado de uma tentativa de envio de SMS.
 */
class SmsResultado
{
    /**
     * @param  bool  $enviado  Indica se a mensagem foi aceite pelo provedor.
     * @param  string  $telefone  Número normalizado para onde se tentou enviar.
     * @param  string  $mensagem  Conteúdo da mensagem.
     * @param  ?string  $referencia  Identificador do envio devolvido pelo provedor.
     * @param  ?string  $erro  Motivo da falha, quando existe.
     * @param  ?int  $idSmsEnvio  Registo criado na tabela sms_envios.
     */
    public function __construct(
        public readonly bool $enviado,
        public readonly string $telefone,
        public readonly string $mensagem,
        public readonly ?string $referencia = null,
        public readonly ?string $erro = null,
        public readonly ?int $idSmsEnvio = null,
    ) {}

    /**
     * Cria um resultado de sucesso.
     */
    public static function sucesso(
        string $telefone,
        string $mensagem,
        ?string $referencia = null,
        ?int $idSmsEnvio = null
    ): self {
        return new self(
            true,
            $telefone,
            $mensagem,
            $referencia,
            null,
            $idSmsEnvio,
        );
    }

    /**
     * Cria um resultado de falha.
     */
    public static function falha(
        string $telefone,
        string $mensagem,
        string $erro,
        ?int $idSmsEnvio = null
    ): self {
        return new self(
            false,
            $telefone,
            $mensagem,
            null,
            $erro,
            $idSmsEnvio,
        );
    }

    /**
     * Indica se o envio não foi possível por falta de telefone válido.
     */
    public function telefoneInvalido(): bool
    {
        return $this->erro === SmsService::ERRO_TELEFONE_INVALIDO;
    }
}
