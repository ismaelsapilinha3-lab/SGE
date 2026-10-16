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
