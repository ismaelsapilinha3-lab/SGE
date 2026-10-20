<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relatorio extends Model
{
    use HasFactory;

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

    public function estaSubmetido(): bool
    {
        return $this->submetido_em !== null;
    }
}
