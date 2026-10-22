<?php

namespace App\Services\Sms;

use App\Models\Candidatura;
use App\Models\SmsEnvio;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de envio de SMS.
 *
 * Normaliza o número, escolhe o provedor configurado, regista a tentativa na
 * tabela sms_envios e devolve sempre um resultado — nunca lança excepções,
 * para que uma falha no SMS não impeça a aprovação da candidatura.
 */
class SmsService
{
    /** Erro usado quando o número não é um telefone válido. */
    public const ERRO_TELEFONE_INVALIDO = 'telefone_invalido';

    public function __construct(
        private readonly SmsProvedor $provedor,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Envio genérico
    |--------------------------------------------------------------------------
    */

    /**
     * Envia uma mensagem para o telefone indicado.
     *
     * @param  array<string, mixed>  $contexto  Dados adicionais para auditoria.
     */
    public function enviar(
        string $telefone,
        string $mensagem,
        ?Candidatura $candidatura = null,
        ?User $utilizador = null,
        array $contexto = []
    ): SmsResultado {
        $normalizado = $this->normalizarTelefone($telefone);

        if ($normalizado === null) {
            Log::channel('sms')->warning('SMS ignorado: telefone inválido', [
                'telefone' => $telefone,
            ]);

            return SmsResultado::falha(
                $telefone,
                $mensagem,
                self::ERRO_TELEFONE_INVALIDO
            );
        }

        if (! config('sms.enabled', true)) {
            Log::channel('sms')->info('Envio de SMS desactivado', [
                'telefone' => $normalizado,
            ]);

            return SmsResultado::falha(
                $normalizado,
                $mensagem,
                'O envio de SMS está desactivado.'
            );
        }

        $resultado = $this->provedor->enviar($normalizado, $mensagem);

        $this->registar($resultado, $candidatura, $utilizador, $contexto);

        return $resultado;
    }

    /*
    |--------------------------------------------------------------------------
    | Notificação de candidatura aceite
    |--------------------------------------------------------------------------
    */

    /**
     * Envia ao candidato a mensagem de parabéns pela candidatura aceite.
     */
    public function notificarCandidaturaAprovada(
        Candidatura $candidatura
    ): SmsResultado {
        $telefone = $candidatura->contacto
            ?? $candidatura->utilizador?->telefone
            ?? '';

        $mensagem = $this->montarMensagem(
            (string) config('sms.messages.candidatura_aprovada'),
            $candidatura->nome
        );

        return $this->enviar(
            $telefone,
            $mensagem,
            $candidatura,
            $candidatura->utilizador,
            ['motivo' => 'candidatura_aprovada']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Normalização do telefone
    |--------------------------------------------------------------------------
    */

    /**
     * Converte o número guardado no sistema para o formato internacional.
     *
     * Em Angola os contactos são guardados com 9 dígitos (ex.: 923000111) e
     * são enviados como +244923000111. Devolve null quando o número não é
     * utilizável.
     */
    public function normalizarTelefone(?string $telefone): ?string
    {
        if (blank($telefone)) {
            return null;
        }

        $prefixo = (string) config('sms.country_code', '+244');

        $limpo = preg_replace('/[^0-9+]/', '', trim($telefone)) ?? '';

        if ($limpo === '') {
            return null;
        }

        if (str_starts_with($limpo, '+')) {
            $digitos = ltrim($limpo, '+');

            return strlen($digitos) >= 9 ? '+'.$digitos : null;
        }

        $digitos = ltrim($limpo, '0');

        if (str_starts_with($digitos, '00')) {
            $digitos = substr($digitos, 2);
        }

        $codigo = ltrim($prefixo, '+');

        if (str_starts_with($digitos, $codigo) && strlen($digitos) > 10) {
            $digitos = substr($digitos, strlen($codigo));
        }

        if (strlen($digitos) === 9) {
            return '+'.$codigo.$digitos;
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Auxiliares
    |--------------------------------------------------------------------------
    */

    /**
     * Substitui o marcador {nome} no modelo de mensagem.
     */
    private function montarMensagem(string $modelo, ?string $nome): string
    {
        $primeiroNome = trim((string) str($nome ?? '')->before(' '));

        if ($primeiroNome === '') {
            $primeiroNome = 'candidato';
        }

        return str_replace('{nome}', $primeiroNome, $modelo);
    }

    /**
     * Regista a tentativa de envio na tabela sms_envios.
     *
     * @param  array<string, mixed>  $contexto
     */
    private function registar(
        SmsResultado $resultado,
        ?Candidatura $candidatura,
        ?User $utilizador,
        array $contexto
    ): void {
        try {
            SmsEnvio::create([
                'id_utilizador' => $utilizador?->id_utilizador
                    ?? $candidatura?->id_utilizador,

                'id_candidatura' => $candidatura?->id_candidatura,

                'telefone' => $resultado->telefone,

                'mensagem' => $resultado->mensagem,

                'provedor' => $this->provedor->nome(),

                'estado' => $resultado->enviado ? 'enviado' : 'falhado',

                'referencia' => $resultado->referencia,

                'erro' => $resultado->erro,

                'enviado_em' => $resultado->enviado ? now() : null,

                'contexto' => json_encode($contexto, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $excecao) {
            /*
             * A auditoria nunca pode impedir o fluxo de negócio: se a
             * registo falhar, o erro fica apenas no log.
             */
            Log::channel('sms')->error('Não foi possível registar o envio de SMS', [
                'erro' => $excecao->getMessage(),
            ]);
        }
    }
}
