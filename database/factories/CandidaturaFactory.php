<?php

namespace Database\Factories;

use App\Models\Candidatura;
use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidatura>
 */
class CandidaturaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'curso' => fake()->randomElement([
                'Engenharia Informática',
                'Gestão de Empresas',
                'Contabilidade e Auditoria',
                'Engenharia de Telecomunicações',
            ]),
            'universidade' => fake()->company(),
            'nascimento' => fake()->dateTimeBetween('-30 years', '-18 years')->format('Y-m-d'),
            'sexo' => fake()->randomElement(['Masculino', 'Feminino']),
            'bi' => strtoupper(fake()->bothify('#########??')),
            'contacto' => fake()->numerify('9########'),
            'estado' => 'em_analise',
            'id_departamento_selecionado' => Departamento::factory(),
        ];
    }

    public function aprovada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'aprovada',
            'data_decisao' => now(),
        ]);
    }

    public function recusada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'recusada',
            'motivo_recusa' => 'Perfil não compatível com a vaga.',
            'data_decisao' => now(),
        ]);
    }
}
