<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estagio extends Model
{
    protected $table = 'estagios';

    protected $primaryKey = 'id_estagio';

    protected $fillable = [
        'id_candidatura',
        'id_estagiario',
        'data_inicio',
        'data_fim_prevista',
        'data_fim_real',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim_prevista' => 'date',
            'data_fim_real' => 'date',
        ];
    }

    public function relatorios(): HasMany
    {
        return $this->hasMany(
            Relatorio::class,
            'id_estagio',
            'id_estagio'
        );
    }

    public function estagiario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_estagiario',
            'id_utilizador'
        );
    }
}