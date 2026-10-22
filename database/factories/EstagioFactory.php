<?php

namespace Database\Factories;

use App\Models\Candidatura;
use App\Models\Estagio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estagio>
 */
class EstagioFactory extends Factory
{
    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('-4 months', '-1 month');

        return [
            'id_candidatura' => Candidatura::factory()->aprovada(),
            'id_estagiario' => User::factory()->estagiario(),
            'data_inicio' => $inicio->format('Y-m-d'),
            'data_fim_prevista' => (clone $inicio)
                ->modify('+6 months')
                ->format('Y-m-d'),
            'estado' => 'ativo',
        ];
    }

    public function suspenso(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'suspenso',
            'motivo_suspensao' => 'Suspensão temporária por motivos académicos.',
        ]);
    }

    public function concluido(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'concluido',
            'data_fim_real' => now()->format('Y-m-d'),
        ]);
    }

    public function cancelado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'cancelado',
            'data_fim_real' => now()->format('Y-m-d'),
        ]);
    }
}
