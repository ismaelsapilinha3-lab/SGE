<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Avaliacao extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'avaliacoes';

    protected $primaryKey = 'id_avaliacao';

    protected $fillable = [
        'id_estagio',
        'id_supervisor',
        'tipo',
        'periodo_referencia',
        'nota_atividades',
        'nota_assiduidade',
        'nota_pontualidade',
        'nota',
        'comentarios',
        'pontos_fortes',
        'areas_melhoria',
    ];

    protected function casts(): array
    {
        return [
            'periodo_referencia' => 'date',
            'nota_atividades' => 'decimal:2',
            'nota_assiduidade' => 'decimal:2',
            'nota_pontualidade' => 'decimal:2',
            'nota' => 'decimal:2',
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
}
