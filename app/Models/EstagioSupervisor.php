<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstagioSupervisor extends Model
{
    use HasFactory;

    protected $table = 'estagio_supervisores';

    protected $primaryKey = 'id_estagio_supervisor';

    protected $fillable = [
        'id_estagio',
        'id_supervisor',
        'id_coordenador',
        'atribuido_em',
        'removido_em',
    ];

    protected function casts(): array
    {
        return [
            'atribuido_em' => 'datetime',
            'removido_em' => 'datetime',
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

    public function estaAtivo(): bool
    {
        return $this->removido_em === null;
    }
}
