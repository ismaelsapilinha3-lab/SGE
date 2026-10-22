<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'tipo_utilizador' => 'estagiario',
            'ativo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function administrador(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_utilizador' => 'administrador',
        ]);
    }

    public function coordenador(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_utilizador' => 'coordenador',
        ]);
    }

    public function supervisor(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_utilizador' => 'supervisor',
        ]);
    }

    public function estagiario(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_utilizador' => 'estagiario',
        ]);
    }

    public function inativo(): static
    {
        return $this->state(fn (array $attributes) => [
            'ativo' => false,
        ]);
    }
}
