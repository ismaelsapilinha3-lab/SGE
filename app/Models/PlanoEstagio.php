<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanoEstagio extends Model
{
    use HasFactory;

    protected $table = 'planos_estagio';

    protected $primaryKey = 'id_plano_estagio';

    protected $fillable = [
        'id_estagio',
        'id_supervisor',
        'objetivos',
        'atividades_previstas',
        'cronograma',
        'data_inicio',
        'data_fim',
        'estado',
        'motivo_recusa',
        'id_coordenador',
        'data_decisao',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'data_decisao' => 'datetime',
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

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_supervisor',
            'id_utilizador'
        );
    }

    public function coordenador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_coordenador',
            'id_utilizador'
        );
    }

    public function historico(): HasMany
    {
        return $this->hasMany(
            PlanoEstagioHistorico::class,
            'id_plano_estagio',
            'id_plano_estagio'
        );
    }

    public function estaSubmetido(): bool
    {
        return $this->estado === 'submetido';
    }

    public function estaAprovado(): bool
    {
        return $this->estado === 'aprovado';
    }

    /**
     * Guarda uma cópia do conteúdo atual antes de o plano ser editado
     * e reenviado, preservando o rasto das versões anteriores.
     */
    public function registarHistorico(?int $alteradoPor = null): PlanoEstagioHistorico
    {
        return $this->historico()->create([
            'objetivos' => $this->objetivos,
            'atividades_previstas' => $this->atividades_previstas,
            'cronograma' => $this->cronograma,
            'data_inicio' => $this->data_inicio,
            'data_fim' => $this->data_fim,
            'estado' => $this->estado,
            'motivo_recusa' => $this->motivo_recusa,
            'alterado_por' => $alteradoPor,
            'alterado_em' => now(),
        ]);
    }
}
