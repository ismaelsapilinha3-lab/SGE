<?php

namespace Database\Factories;

use App\Models\Estagio;
use App\Models\Relatorio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Relatorio>
 */
class RelatorioFactory extends Factory
{
    public function definition(): array
    {
        $periodo = fake()->dateTimeBetween('-2 months', 'now');

        $mes = Carbon::parse($periodo)->startOfMonth();

        return [
            'id_estagio' => Estagio::factory(),
            'tipo' => 'mensal',
            'periodo_referencia' => $periodo->format('Y-m-d'),
            'mes_referencia' => $mes->toDateString(),
            'prazo_envio' => $mes->copy()
                ->addMonthNoOverflow()
                ->day(5)
                ->toDateString(),
            'atividades' => fake()->paragraph(),
            'resultados' => fake()->paragraph(),
            'constrangimentos' => fake()->sentence(),
            'objetivos' => fake()->sentence(),
            'submetido_em' => null,
            'estado_avaliacao' => Relatorio::ESTADO_PENDENTE,
        ];
    }

    public function submetido(): static
    {
        return $this->state(fn (array $attributes) => [
            'submetido_em' => now(),
        ]);
    }
}
