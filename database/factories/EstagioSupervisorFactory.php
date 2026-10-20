<?php

namespace Database\Factories;

use App\Models\Estagio;
use App\Models\EstagioSupervisor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstagioSupervisor>
 */
class EstagioSupervisorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_estagio' => Estagio::factory(),
            'id_supervisor' => User::factory()->supervisor(),
            'id_coordenador' => User::factory()->coordenador(),
            'atribuido_em' => now(),
            'removido_em' => null,
        ];
    }

    public function removido(): static
    {
        return $this->state(fn (array $attributes) => [
            'removido_em' => now(),
        ]);
    }
}
