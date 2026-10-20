<?php

namespace Database\Factories;

use App\Models\Estagio;
use App\Models\FeedbackEstagio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackEstagio>
 */
class FeedbackEstagioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_estagio' => Estagio::factory(),
            'nota_geral' => fake()->numberBetween(0, 10),
            'comentario' => fake()->paragraph(),
            'sugestoes' => fake()->sentence(),
        ];
    }
}
