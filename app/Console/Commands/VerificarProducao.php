<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:verificar-producao')]
#[Description('Verifica a configuração mínima para executar em produção')]
class VerificarProducao extends Command
{
    public function handle(): int
    {
        $verificacoes = [
            'Ambiente' => [
                config('app.env') === 'production',
                'APP_ENV=production',
            ],
            'Debug' => [
                config('app.debug') === false,
                'APP_DEBUG=false',
            ],
            'Chave da aplicação' => [
                filled(config('app.key')),
                'APP_KEY definida',
            ],
            'URL da aplicação' => [
                str_starts_with((string) config('app.url'), 'https://'),
                'APP_URL com https://',
            ],
            'Cookie de sessão seguro' => [
                config('session.secure') === true,
                'SESSION_SECURE_COOKIE=true',
            ],
            'Sessão encriptada' => [
                config('session.encrypt') === true,
                'SESSION_ENCRYPT=true',
            ],
            'Cookie HTTP-only' => [
                config('session.http_only') === true,
                'SESSION_HTTP_ONLY=true',
            ],
            'Driver de sessão' => [
                ! in_array(
                    config('session.driver'),
                    ['array', 'cookie'],
                    true
                ),
                'database ou redis',
            ],
            'Fila' => [
                ! in_array(
                    config('queue.default'),
                    ['sync', 'null'],
                    true
                ),
                'database, redis ou sqs',
            ],
            'Cache com locks' => [
                in_array(
                    config('cache.default'),
                    ['database', 'redis', 'memcached', 'dynamodb'],
                    true
                ),
                'database, redis, memcached ou dynamodb',
            ],
            'Email' => [
                ! in_array(
                    config('mail.default'),
                    ['log', 'array'],
                    true
                ),
                'smtp, ses ou outro transporte real',
            ],
            'SMS' => [
                ! config('sms.enabled')
                    || config('sms.provider') !== 'simulado',
                'provedor real ou SMS_ENABLED=false',
            ],
            'Backup' => [
                (bool) config('backup.enabled'),
                'BACKUP_ENABLED=true',
            ],
        ];

        $falhas = [];

        foreach ($verificacoes as $nome => [$cumprida, $esperado]) {
            $this->line(sprintf(
                '%s %s — %s',
                $cumprida ? '<info>OK</info>' : '<error>FALHA</error>',
                $nome,
                $cumprida ? 'configurado' : "esperado: {$esperado}"
            ));

            if (! $cumprida) {
                $falhas[] = $nome;
            }
        }

        if ($falhas !== []) {
            $this->newLine();
            $this->error(
                'Configuração de produção incompleta: '
                .implode(', ', $falhas)
                .'.'
            );

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Configuração mínima de produção validada.');

        return self::SUCCESS;
    }
}
