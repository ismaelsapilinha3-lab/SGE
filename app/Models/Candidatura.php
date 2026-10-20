<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidatura extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_candidatura';

    protected $fillable = [
        'nome',
        'email',
        'id_utilizador',
        'curso',
        'universidade',
        'nascimento',
        'sexo',
        'bi',
        'contacto',
        'estado',
        'motivo_recusa',
        'data_decisao',
        'id_departamento_selecionado',
        'id_coordenador',
        'encaminhada_em',
        'bi_ativo',
    ];

    protected $casts = [
        'nascimento' => 'date',
        'data_decisao' => 'datetime',
        'encaminhada_em' => 'datetime',
        'bi_ativo' => 'boolean',
    ];

    public function documentos()
    {
        return $this->hasMany(
            Documento::class,
            'id_candidatura',
            'id_candidatura'
        );
    }

    public function utilizador()
    {
        return $this->belongsTo(
            User::class,
            'id_utilizador',
            'id_utilizador'
        );
    }

    public function departamento()
    {
        return $this->belongsTo(
            Departamento::class,
            'id_departamento_selecionado',
            'id_departamento'
        );
    }

    public function estagio()
    {
        return $this->hasOne(
            Estagio::class,
            'id_candidatura',
            'id_candidatura'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Verificação de propriedade
    |--------------------------------------------------------------------------
    |
    | A propriedade decide-se por id_utilizador. O e-mail só é usado como
    | regresso de segurança para candidaturas antigas ainda sem a chave,
    | nunca como chave principal.
    |
    */

    public function pertenceAo(User $utilizador): bool
    {
        if ($this->id_utilizador !== null) {
            /*
             * Comparação explícita em inteiro: o driver MySQL pode
             * devolver os IDs como texto, e uma comparação estrita
             * entre "2" e 2 negaria acesso ao próprio dono.
             */
            return (int) $this->id_utilizador
                === (int) $utilizador->id_utilizador;
        }

        return $this->email === $utilizador->email;
    }
}
