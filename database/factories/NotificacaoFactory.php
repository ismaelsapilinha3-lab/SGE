<?php

namespace Database\Factories;

use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notificacao>
 */
class NotificacaoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_utilizador' => User::factory(),
            'id_candidatura' => null,
            'canal' => 'sistema',
            'titulo' => fake()->sentence(3),
            'mensagem' => fake()->paragraph(),
            'enviada_em' => now(),
            'lida_em' => null,
        ];
    }

    public function lida(): static
    {
        return $this->state(fn (array $attributes) => [
            'lida_em' => now(),
        ]);
    }
}
