<?php

namespace App\Http\Controllers;

use App\Models\Departamento;

class DepartamentoController extends Controller
{
    public function index()
    {
        return Departamento::where('ativo', true)->select('id_departamento', 'nome')->get();
    }
}