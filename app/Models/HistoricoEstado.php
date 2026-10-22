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
        'id_documento_estagio',
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

    public function estagio()
    {
        return $this->belongsTo(
            Estagio::class,
            'id_estagio',
            'id_estagio'
        );
    }

    public function documento()
    {
        return $this->belongsTo(
            Documento::class,
            'id_documento',
            'id_documento'
        );
    }

    public function planoEstagio()
    {
        return $this->belongsTo(
            PlanoEstagio::class,
            'id_plano_estagio',
            'id_plano_estagio'
        );
    }

    public function documentoEstagio()
    {
        return $this->belongsTo(
            DocumentoEstagio::class,
            'id_documento_estagio',
            'id_documento_estagio'
        );
    }
}
