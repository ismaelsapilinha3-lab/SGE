<?php

namespace Database\Factories;

use App\Models\Assiduidade;
use App\Models\Estagio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assiduidade>
 */
class AssiduidadeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_estagio' => Estagio::factory(),
            'id_supervisor' => User::factory()->supervisor(),
            'data' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'estado' => 'presente',
            'hora_entrada' => '08:00',
            'hora_saida' => '17:00',
            'justificacao' => null,
            'justificacao_estado' => null,
            'motivo_recusa' => null,
            'id_supervisor_justificacao' => null,
            'justificacao_submetida_em' => null,
            'justificacao_decidida_em' => null,
        ];
    }

    public function atraso(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'atraso',
            'hora_entrada' => '09:30',
        ]);
    }

    public function falta(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'falta',
            'hora_entrada' => null,
            'hora_saida' => null,
        ]);
    }

    public function faltaJustificada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'falta_justificada',
            'hora_entrada' => null,
            'hora_saida' => null,
            'justificacao' => 'Consulta médica.',
            'justificacao_estado' => 'aprovada',
            'justificacao_submetida_em' => now(),
            'justificacao_decidida_em' => now(),
        ]);
    }

    public function justificacaoPendente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'falta',
            'hora_entrada' => null,
            'hora_saida' => null,
            'justificacao' => 'Consulta médica.',
            'justificacao_estado' => 'pendente',
            'justificacao_submetida_em' => now(),
        ]);
    }

    public function justificacaoRecusada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'falta',
            'hora_entrada' => null,
            'hora_saida' => null,
            'justificacao' => 'Consulta médica.',
            'justificacao_estado' => 'recusada',
            'motivo_recusa' => 'Justificação insuficiente.',
            'justificacao_submetida_em' => now(),
            'justificacao_decidida_em' => now(),
        ]);
    }
}
