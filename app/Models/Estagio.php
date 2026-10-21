<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Estagio extends Model
{
    protected $table = 'estagios';

    protected $primaryKey = 'id_estagio';

    protected $fillable = [
        'id_candidatura',
        'id_estagiario',
        'data_inicio',
        'duracao_meses',
        'data_fim_prevista',
        'dia_limite_relatorio',
        'data_fim_real',
        'estado',
        'motivo_suspensao',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim_prevista' => 'date',
            'data_fim_real' => 'date',
            'duracao_meses' => 'integer',
            'dia_limite_relatorio' => 'integer',
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

    public function candidatura(): BelongsTo
    {
        return $this->belongsTo(
            Candidatura::class,
            'id_candidatura',
            'id_candidatura'
        );
    }

    /**
     * Planos de estágio carregados pelo administrador.
     */
    public function planos(): HasMany
    {
        return $this->hasMany(
            PlanoEstagio::class,
            'id_estagio',
            'id_estagio'
        );
    }

    /**
     * Plano de estágio em vigor (o último carregado pelo administrador).
     */
    public function planoAtual(): HasOne
    {
        return $this->hasOne(
            PlanoEstagio::class,
            'id_estagio',
            'id_estagio'
        )->latestOfMany('data_upload');
    }

    /**
     * Dia do mês até ao qual o relatório do mês anterior deve ser entregue.
     */
    public function diaLimiteRelatorio(): int
    {
        $dia = (int) ($this->dia_limite_relatorio ?? 0);

        return $dia >= 1 && $dia <= 28 ? $dia : 5;
    }

    /**
     * Prazo de entrega do relatório de um mês (mês seguinte ao do relatório).
     */
    public function prazoParaMes(\DateTimeInterface|string $mes): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse($mes)
            ->startOfMonth()
            ->addMonthNoOverflow()
            ->day($this->diaLimiteRelatorio());
    }
}
