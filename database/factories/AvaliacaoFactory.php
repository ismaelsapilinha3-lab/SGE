<?php

namespace Database\Factories;

use App\Models\Avaliacao;
use App\Models\Estagio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Avaliacao>
 */
class AvaliacaoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_estagio' => Estagio::factory(),
            'id_supervisor' => User::factory()->supervisor(),
            'tipo' => 'mensal',
            'periodo_referencia' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'nota_atividades' => fake()->randomFloat(2, 0, 20),
            'nota_assiduidade' => fake()->randomFloat(2, 0, 20),
            'nota_pontualidade' => fake()->randomFloat(2, 0, 20),
            'nota' => fake()->randomFloat(2, 0, 20),
            'comentarios' => fake()->sentence(),
        ];
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'final',
            'pontos_fortes' => fake()->paragraph(),
            'areas_melhoria' => fake()->paragraph(),
        ]);
    }
}
