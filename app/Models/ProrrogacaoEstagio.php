<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProrrogacaoEstagio extends Model
{
    protected $table = 'prorrogacoes_estagio';

    protected $primaryKey = 'id_prorrogacao';

    protected $fillable = [
        'id_estagio',
        'meses',
        'duracao_anterior',
        'duracao_nova',
        'data_fim_anterior',
        'data_fim_nova',
        'motivo',
        'id_utilizador',
    ];

    protected function casts(): array
    {
        return [
            'meses' => 'integer',
            'duracao_anterior' => 'integer',
            'duracao_nova' => 'integer',
            'data_fim_anterior' => 'date',
            'data_fim_nova' => 'date',
        ];
    }

    public function estagio(): BelongsTo
    {
        return $this->belongsTo(
            Estagio::class,
            'id_estagio',
            'id_estagio'
        );
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_utilizador',
            'id_utilizador'
        );
    }
}
