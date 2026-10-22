<?php

namespace Database\Factories;

use App\Models\AtividadeEstagiario;
use App\Models\Estagio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AtividadeEstagiario>
 */
class AtividadeEstagiarioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_estagio' => Estagio::factory(),
            'data' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'titulo' => fake()->sentence(4),
            'descricao' => fake()->paragraph(),
        ];
    }
}
