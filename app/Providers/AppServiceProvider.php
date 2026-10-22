<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
