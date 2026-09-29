<?php

namespace Database\Seeders;

use App\Models\Departamento;
use Illuminate\Database\Seeder;

class DepartamentoSeeder extends Seeder
{
    public function run(): void
    {
        $departamentos = [
            ['nome' => 'Desenvolvimento, Design e Inovação (D.D.I.)', 'descricao' => null],
            // Acrescenta aqui os restantes departamentos da Multitel
        ];

        foreach ($departamentos as $dados) {
            Departamento::updateOrCreate(['nome' => $dados['nome']], $dados);
        }
    }

    
}