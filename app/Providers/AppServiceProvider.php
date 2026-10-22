<?php

namespace App\Providers;

use App\Services\Sms\Provedores\SmsProvedorHttp;
use App\Services\Sms\Provedores\SmsProvedorSimulado;
use App\Services\Sms\Provedores\SmsProvedorTwilio;
use App\Services\Sms\SmsProvedor;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Provedor de SMS
        |--------------------------------------------------------------------------
        |
        | O provedor é escolhido pela configuração. Por omissão usa-se o
        | provedor simulado, que regista o envio localmente sem fazer
        | chamadas externas.
        |
        */
        $this->app->singleton(SmsProvedor::class, function () {
            return match (config('sms.provider')) {
                'http' => new SmsProvedorHttp(
                    endpoint: config('sms.endpoint'),
                    metodo: (string) config('sms.http_method', 'POST'),
                    autenticacao: (string) config('sms.auth', 'bearer'),
                    apiKey: config('sms.api_key'),
                    apiSecret: config('sms.api_secret'),
                    authHeader: (string) config('sms.auth_header', 'Authorization'),
                    authScheme: (string) config('sms.auth_scheme', 'Bearer'),
                    authQueryParam: (string) config('sms.auth_query_param', 'api_key'),
                    campoDestino: (string) config('sms.campo_destino', 'to'),
                    campoMensagem: (string) config('sms.campo_mensagem', 'text'),
                    campoRemetente: (string) config('sms.campo_remetente', 'from'),
                    camposExtra: $this->camposExtraDoGateway(),
                    campoReferencia: (string) config('sms.campo_referencia', 'id'),
                    timeout: (int) config('sms.timeout', 10),
                    remetente: (string) config('sms.from', 'MULTITEL'),
                ),

                'twilio' => new SmsProvedorTwilio(
                    config('sms.twilio.sid'),
                    config('sms.twilio.token'),
                    config('sms.twilio.from'),
                    (int) config('sms.timeout', 10),
                ),

                default => new SmsProvedorSimulado,
            };
        });
    }

    /**
     * Lê os campos fixos adicionais do gateway, definidos em JSON no .env.
     *
     * @return array<string, mixed>
     */
    private function camposExtraDoGateway(): array
    {
        $json = config('sms.campos_extra');

        if (blank($json)) {
            return [];
        }

        $campos = json_decode((string) $json, true);

        return is_array($campos) ? $campos : [];
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Política única de palavra-passe
        |--------------------------------------------------------------------------
        |
        | Aplica-se a todas as validações de criação/alteração de palavra-passe
        | que usem Password::default(): mínimo 8 caracteres, letras e números.
        |
        */
        Password::defaults(fn () => Password::min(8)
            ->letters()
            ->numbers());
    }
}
