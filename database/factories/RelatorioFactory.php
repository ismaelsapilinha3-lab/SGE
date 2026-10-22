<?php

namespace Database\Factories;

use App\Models\Estagio;
use App\Models\Relatorio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Relatorio>
 */
class RelatorioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_estagio' => Estagio::factory(),
            'tipo' => 'mensal',
            'periodo_referencia' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'atividades' => fake()->paragraph(),
            'resultados' => fake()->paragraph(),
            'constrangimentos' => fake()->sentence(),
            'objetivos' => fake()->sentence(),
            'submetido_em' => null,
        ];
    }

    public function submetido(): static
    {
        return $this->state(fn (array $attributes) => [
            'submetido_em' => now(),
        ]);
    }
}
