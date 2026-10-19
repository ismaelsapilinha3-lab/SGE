<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relatorio extends Model
{
    protected $table = 'relatorios';

    protected $primaryKey = 'id_relatorio';

    protected $fillable = [
        'id_estagio',
        'tipo',
        'periodo_referencia',
        'atividades',
        'resultados',
        'constrangimentos',
        'objetivos',
        'submetido_em',
        'estado_avaliacao',
        'comentario_admin',
        'ficheiro_path',
        'ficheiro_nome',
    ];

    protected function casts(): array
    {
        return [
            'periodo_referencia' => 'date',
            'submetido_em' => 'datetime',
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
}