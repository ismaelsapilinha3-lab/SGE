<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidatura extends Model
{
    protected $primaryKey = 'id_candidatura';

    protected $fillable = [
        'nome', 'email', 'curso', 'universidade', 'nascimento',
        'sexo', 'bi', 'contacto', 'estado', 'id_departamento_selecionado',
    ];

    protected $casts = [
        'nascimento' => 'date',
        'data_decisao' => 'datetime',
    ];

    public function documentos()
    {
        return $this->hasMany(Documento::class, 'id_candidatura', 'id_candidatura');
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'id_departamento_selecionado', 'id_departamento');
    }
}