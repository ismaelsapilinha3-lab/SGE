<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Concluir automaticamente os estágios com prazo terminado.
// Horário local de Angola: todos os dias às 00h05.
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:atualizar-estagios-concluidos')
    ->dailyAt('00:05')
    ->timezone('Africa/Luanda');

// Marcar os relatórios fora de prazo e avisar o administrador.
// Corre depois da conclusão automática dos estágios.
Schedule::command('app:alertar-relatorios-em-atraso')
    ->dailyAt('00:15')
    ->timezone('Africa/Luanda');
