<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdministradorSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command->warn('ADMIN_EMAIL / ADMIN_PASSWORD não definidos no .env: administrador não criado.');
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'nome' => env('ADMIN_NOME', 'Administrador'),
                'password' => $password,
                'tipo_utilizador' => 'administrador',
                'ativo' => true,
            ]
        );
    }
}