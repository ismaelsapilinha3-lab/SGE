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
                    config('sms.endpoint'),
                    config('sms.api_key'),
                    config('sms.api_secret'),
                    (string) config('sms.auth_header', 'Authorization'),
                    (string) config('sms.auth_scheme', 'Bearer'),
                    (int) config('sms.timeout', 10),
                    (string) config('sms.from', 'MULTITEL'),
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
