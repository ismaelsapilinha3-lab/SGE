<?php

namespace Database\Factories;

use App\Models\DocumentoEstagio;
use App\Models\Estagio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentoEstagio>
 */
class DocumentoEstagioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_estagio' => Estagio::factory(),
            'id_estagiario' => User::factory()->estagiario(),
            'tipo_documento' => 'contrato',
            'versao' => 1,
            'nome_arquivo' => 'contrato.pdf',
            'caminho_arquivo' => 'estagios/1/contrato.pdf',
            'data_upload' => now(),
            'validade' => null,
            'estado' => 'pendente',
            'descricao' => null,
        ];
    }

    public function validado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'validado',
        ]);
    }

    public function rejeitado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'rejeitado',
            'descricao' => 'Documento ilegível.',
        ]);
    }
}
