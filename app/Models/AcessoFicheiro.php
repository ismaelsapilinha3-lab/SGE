<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcessoFicheiro extends Model
{
    protected $table = 'acessos_ficheiros';

    protected $primaryKey = 'id_acesso';

    public $timestamps = false;

    protected $fillable = [
        'id_utilizador',
        'id_documento',
        'id_documento_estagio',
        'id_relatorio',
        'id_plano_estagio',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
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

    public function documento(): BelongsTo
    {
        return $this->belongsTo(
            Documento::class,
            'id_documento',
            'id_documento'
        );
    }

    public function documentoEstagio(): BelongsTo
    {
        return $this->belongsTo(
            DocumentoEstagio::class,
            'id_documento_estagio',
            'id_documento_estagio'
        );
    }

    public function relatorio(): BelongsTo
    {
        return $this->belongsTo(
            Relatorio::class,
            'id_relatorio',
            'id_relatorio'
        );
    }

    public function planoEstagio(): BelongsTo
    {
        return $this->belongsTo(
            PlanoEstagio::class,
            'id_plano_estagio',
            'id_plano_estagio'
        );
    }
}
