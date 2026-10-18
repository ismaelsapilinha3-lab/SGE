<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Perfil extends Model
{
    protected $table = 'perfis';

    protected $primaryKey = 'id_perfil';

    protected $fillable = [
        'id_utilizador',
        'id_departamento',
        'curso',
        'universidade',
        'nascimento',
        'sexo',
        'bi',
    ];

    protected function casts(): array
    {
        return [
            'nascimento' => 'date',
        ];
    }

    public function utilizador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_utilizador',
            'id_utilizador'
        );
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(
            Departamento::class,
            'id_departamento',
            'id_departamento'
        );
    }
}

