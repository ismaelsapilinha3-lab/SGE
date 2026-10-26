<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assiduidade extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $primaryKey = 'id_assiduidade';

    protected $fillable = [
        'id_estagio',
        'id_supervisor',
        'data',
        'estado',
        'hora_entrada',
        'hora_saida',
        'justificacao',
        'justificacao_estado',
        'motivo_recusa',
        'id_supervisor_justificacao',
        'justificacao_submetida_em',
        'justificacao_decidida_em',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date:Y-m-d',
            'justificacao_submetida_em' => 'datetime',
            'justificacao_decidida_em' => 'datetime',
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

    /**
     * Supervisor que decidiu a justificação da falta.
     */
    public function supervisorJustificacao(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_supervisor_justificacao',
            'id_utilizador'
        );
    }

    public function contaComoPresenca(): bool
    {
        return in_array($this->estado, ['presente', 'atraso'], true);
    }

    public function temJustificacaoPendente(): bool
    {
        return $this->justificacao_estado === 'pendente';
    }

    public function justificacaoAprovada(): bool
    {
        return $this->justificacao_estado === 'aprovada';
    }

    public function justificacaoRecusada(): bool
    {
        return $this->justificacao_estado === 'recusada';
    }

    /**
     * Indica se o estagiário pode (voltar a) submeter uma justificação.
     *
     * Só uma falta ainda não justificada nem à espera de decisão o pode
     * ser; uma justificação recusada pode ser corrigida e resubmetida.
     */
    public function podeSubmeterJustificacao(): bool
    {
        return $this->estado === 'falta'
            && ! $this->temJustificacaoPendente();
    }
}
