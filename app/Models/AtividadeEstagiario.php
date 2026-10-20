<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtividadeEstagiario extends Model
{
    use HasFactory;

    protected $table = 'atividades_estagiario';

    protected $primaryKey = 'id_atividade';

    protected $fillable = [
        'id_estagio',
        'data',
        'titulo',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
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
}
