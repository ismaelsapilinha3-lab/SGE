<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsEnvio extends Model
{
    use HasFactory;

    protected $table = 'sms_envios';

    protected $primaryKey = 'id_sms_envio';

    protected $fillable = [
        'id_utilizador',
        'id_candidatura',
        'telefone',
        'mensagem',
        'provedor',
        'estado',
        'referencia',
        'erro',
        'contexto',
        'enviado_em',
    ];

    protected function casts(): array
    {
        return [
            'contexto' => 'array',
            'enviado_em' => 'datetime',
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

    public function candidatura(): BelongsTo
    {
        return $this->belongsTo(
            Candidatura::class,
            'id_candidatura',
            'id_candidatura'
        );
    }

    /**
     * Indica se o envio foi aceite pelo provedor.
     */
    public function foiEnviado(): bool
    {
        return $this->estado === 'enviado';
    }
}
