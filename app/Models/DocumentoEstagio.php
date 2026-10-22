<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoEstagio extends Model
{
    use HasFactory;

    protected $table = 'documentos_estagio';

    protected $primaryKey = 'id_documento_estagio';

    protected $fillable = [
        'id_estagio',
        'id_estagiario',
        'tipo_documento',
        'versao',
        'nome_arquivo',
        'caminho_arquivo',
        'data_upload',
        'validade',
        'estado',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'data_upload' => 'datetime',
            'validade' => 'date',
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

    public function estagiario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_estagiario',
            'id_utilizador'
        );
    }

    public function estaPendente(): bool
    {
        return $this->estado === 'pendente';
    }

    public function estaValidado(): bool
    {
        return $this->estado === 'validado';
    }

    public function estaRejeitado(): bool
    {
        return $this->estado === 'rejeitado';
    }
}
