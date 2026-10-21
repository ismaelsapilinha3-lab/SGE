<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relatorio extends Model
{
    protected $table = 'relatorios';

    protected $primaryKey = 'id_relatorio';

    public const ESTADO_PENDENTE = 'pendente';

    public const ESTADO_EM_ATRASO = 'em_atraso';

    public const ESTADO_APROVADO = 'aprovado';

    public const ESTADO_REJEITADO = 'rejeitado';

    protected $fillable = [
        'id_estagio',
        'tipo',
        'periodo_referencia',
        'mes_referencia',
        'prazo_envio',
        'atividades',
        'resultados',
        'constrangimentos',
        'objetivos',
        'submetido_em',
        'enviado_em',
        'entrega_confirmada_em',
        'alerta_atraso_enviado_em',
        'estado_avaliacao',
        'comentario_admin',
        'ficheiro_path',
        'ficheiro_nome',
    ];

    protected function casts(): array
    {
        return [
            'periodo_referencia' => 'date',
            'mes_referencia' => 'date',
            'prazo_envio' => 'date',
            'submetido_em' => 'datetime',
            'enviado_em' => 'datetime',
            'entrega_confirmada_em' => 'datetime',
            'alerta_atraso_enviado_em' => 'datetime',
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

    /**
     * O relatório já foi avaliado pelo administrador?
     */
    public function avaliado(): bool
    {
        return in_array(
            $this->estado_avaliacao,
            [self::ESTADO_APROVADO, self::ESTADO_REJEITADO],
            true
        );
    }

    /**
     * O relatório foi entregue pelo estagiário?
     */
    public function entregue(): bool
    {
        return $this->enviado_em !== null
            || $this->entrega_confirmada_em !== null;
    }

    /**
     * O prazo de entrega já passou sem o relatório ser entregue?
     */
    public function emAtraso(?string $hoje = null): bool
    {
        if ($this->entregue() || !$this->prazo_envio) {
            return false;
        }

        $hoje ??= now()->toDateString();

        return $this->prazo_envio->toDateString() < $hoje;
    }
}
