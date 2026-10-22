<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entrevista extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_entrevista';

    protected $fillable = [
        'id_candidatura',
        'id_administrador',
        'id_supervisor_representante',
        'data_hora',
        'resultado',
        'motivo_reprovacao',
    ];

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
        ];
    }

    public function candidatura(): BelongsTo
    {
        return $this->belongsTo(
            Candidatura::class,
            'id_candidatura',
            'id_candidatura'
        );
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_administrador',
            'id_utilizador'
        );
    }

    public function supervisorRepresentante(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_supervisor_representante',
            'id_utilizador'
        );
    }

    public function estaDecidida(): bool
    {
        return $this->resultado !== 'pendente';
    }
}
