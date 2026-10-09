<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricoEstado extends Model
{
    protected $table = 'historico_estados';

    protected $primaryKey = 'id_historico';

    public $timestamps = false;

    protected $fillable = [
        'id_candidatura',
        'id_estagio',
        'id_documento',
        'id_plano_estagio',
        'estado_anterior',
        'estado_novo',
        'motivo',
        'id_utilizador',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function candidatura()
    {
        return $this->belongsTo(
            Candidatura::class,
            'id_candidatura',
            'id_candidatura'
        );
    }
}