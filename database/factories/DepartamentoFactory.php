<?php

namespace Database\Factories;

use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Departamento>
 */
class DepartamentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->company(),
            'descricao' => fake()->sentence(),
            'ativo' => true,
        ];
    }
}
