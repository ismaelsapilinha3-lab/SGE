<?php

namespace Database\Factories;

use App\Models\Candidatura;
use App\Models\Entrevista;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entrevista>
 */
class EntrevistaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_candidatura' => Candidatura::factory(),
            'id_administrador' => User::factory()->administrador(),
            'id_supervisor_representante' => User::factory()->supervisor(),
            'data_hora' => fake()->dateTimeBetween('now', '+2 weeks'),
            'resultado' => 'pendente',
            'motivo_reprovacao' => null,
        ];
    }

    public function aprovada(): static
    {
        return $this->state(fn (array $attributes) => [
            'resultado' => 'aprovado',
        ]);
    }

    public function reprovada(): static
    {
        return $this->state(fn (array $attributes) => [
            'resultado' => 'reprovado',
            'motivo_reprovacao' => 'Falta de conhecimentos técnicos.',
        ]);
    }
}
