<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Documento extends Model
{
    protected $primaryKey = 'id_documento';

    protected $fillable = [
        'id_candidatura', 'tipo_documento', 'versao', 'nome_arquivo',
        'caminho_arquivo', 'data_upload', 'validade', 'estado', 'descricao',
    ];

    protected $casts = [
        'data_upload' => 'datetime',
        'validade' => 'date',
    ];

    public function candidatura()
    {
        return $this->belongsTo(Candidatura::class, 'id_candidatura', 'id_candidatura');
    }
}
