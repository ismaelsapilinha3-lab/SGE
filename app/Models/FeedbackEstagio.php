<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackEstagio extends Model
{
    use HasFactory;

    protected $table = 'feedbacks_estagio';

    protected $primaryKey = 'id_feedback';

    protected $fillable = [
        'id_estagio',
        'nota_geral',
        'comentario',
        'sugestoes',
    ];

    public function estagio(): BelongsTo
    {
        return $this->belongsTo(
            Estagio::class,
            'id_estagio',
            'id_estagio'
        );
    }
}
