<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidatura extends Model
{
    protected $primaryKey = 'id_candidatura';

    protected $fillable = [
        'nome',
        'email',
        'curso',
        'universidade',
        'nascimento',
        'sexo',
        'bi',
        'contacto',
        'estado',
        'motivo_recusa',
        'data_decisao',
        'id_departamento_selecionado',
        'id_coordenador',
        'encaminhada_em',
        'bi_ativo',
    ];

    protected $casts = [
        'nascimento' => 'date',
        'data_decisao' => 'datetime',
        'encaminhada_em' => 'datetime',
        'bi_ativo' => 'boolean',
    ];

    public function documentos()
    {
        return $this->hasMany(
            Documento::class,
            'id_candidatura',
            'id_candidatura'
        );
    }

    public function departamento()
    {
        return $this->belongsTo(
            Departamento::class,
            'id_departamento_selecionado',
            'id_departamento'
        );
    }

    public function estagio()
    {
        return $this->hasOne(
            Estagio::class,
            'id_candidatura',
            'id_candidatura'
        );
    }
}

