<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanoEstagioHistorico extends Model
{
    use HasFactory;

    protected $table = 'planos_estagio_historico';

    protected $primaryKey = 'id_plano_historico';

    public $timestamps = false;

    protected $fillable = [
        'id_plano_estagio',
        'objetivos',
        'atividades_previstas',
        'cronograma',
        'data_inicio',
        'data_fim',
        'estado',
        'motivo_recusa',
        'alterado_por',
        'alterado_em',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'alterado_em' => 'datetime',
        ];
    }

    public function planoEstagio(): BelongsTo
    {
        return $this->belongsTo(
            PlanoEstagio::class,
            'id_plano_estagio',
            'id_plano_estagio'
        );
    }

    public function alteradoPor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'alterado_por',
            'id_utilizador'
        );
    }
}
