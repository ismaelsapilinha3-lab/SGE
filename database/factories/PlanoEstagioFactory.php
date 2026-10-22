<?php

namespace Database\Factories;

use App\Models\Estagio;
use App\Models\PlanoEstagio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanoEstagio>
 */
class PlanoEstagioFactory extends Factory
{
    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'id_estagio' => Estagio::factory(),
            'id_supervisor' => User::factory()->supervisor(),
            'objetivos' => fake()->paragraph(),
            'atividades_previstas' => fake()->paragraph(),
            'cronograma' => fake()->paragraph(),
            'data_inicio' => $inicio->format('Y-m-d'),
            'data_fim' => (clone $inicio)->modify('+6 months')->format('Y-m-d'),
            'estado' => 'submetido',
        ];
    }

    public function aprovado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'aprovado',
            'data_decisao' => now(),
        ]);
    }

    public function recusado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'recusado',
            'motivo_recusa' => 'Objetivos pouco claros.',
        ]);
    }
}
