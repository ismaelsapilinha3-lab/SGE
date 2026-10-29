<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function coordenador()
    {
        return $this->belongsTo(
            User::class,
            'id_coordenador',
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

    public function entrevista(): HasOne
    {
        return $this->hasOne(
            Entrevista::class,
            'id_candidatura',
            'id_candidatura'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Garantir o coordenador da direção
    |--------------------------------------------------------------------------
    |
    | Atribui a candidatura a um coordenador ativo da direção escolhida caso
    | esta ainda não tenha um. Usado ao iniciar o estágio: o candidato passa a
    | estagiário ativo e o coordenador precisa de o encontrar para lhe
    | atribuir supervisor. Quando é indicado um coordenador preferido, é esse
    | que assume a candidatura órfã. É idempotente.
    |
    */

    public function garantirCoordenador(?User $preferido = null): ?User
    {
        if ($this->id_coordenador !== null && $this->temCoordenadorValido()) {
            return $this->coordenador;
        }

        if (
            $preferido !== null
            && $this->coordenadorPertenceADirecao($preferido)
        ) {
            $this->atribuirCoordenador($preferido);

            return $preferido;
        }

        if ($this->id_departamento_selecionado === null) {
            $this->libertarCoordenadorInvalido();

            return null;
        }

        $coordenador = User::query()
            ->where('tipo_utilizador', 'coordenador')
            ->where('ativo', true)
            ->whereHas('perfil', function ($q) {
                $q->where(
                    'id_departamento',
                    $this->id_departamento_selecionado
                );
            })
            ->with('perfil')
            ->first();

        if (! $coordenador) {
            $this->libertarCoordenadorInvalido();

            return null;
        }

        $this->atribuirCoordenador($coordenador);

        return $coordenador;
    }

    /**
     * Indica se a candidatura pode ser gerida pelo coordenador indicado.
     *
     * Um coordenador ativo da direção vê as candidaturas que lhe estão
     * atribuídas, as que ainda não têm coordenador e as que ficaram presas a
     * um coordenador inativo, removido ou de outra direção.
     */
    public function podeSerCoordenadaPor(User $user): bool
    {
        if (! $this->coordenadorPertenceADirecao($user)) {
            return false;
        }

        return $this->id_coordenador === null
            || (int) $this->id_coordenador === (int) $user->id_utilizador
            || ! $this->temCoordenadorValido();
    }

    /**
     * Indica se o coordenador atualmente associado continua válido.
     */
    public function temCoordenadorValido(): bool
    {
        return $this->coordenador !== null
            && $this->coordenadorPertenceADirecao($this->coordenador);
    }

    private function coordenadorPertenceADirecao(User $user): bool
    {
        if (
            ! $user->ativo
            || $user->trashed()
            || $user->tipo_utilizador !== 'coordenador'
        ) {
            return false;
        }

        $idDepartamento = $user->perfil?->id_departamento;

        return $idDepartamento !== null
            && $this->id_departamento_selecionado !== null
            && (int) $idDepartamento
                === (int) $this->id_departamento_selecionado;
    }

    private function atribuirCoordenador(User $coordenador): void
    {
        $this->update([
            'id_coordenador' => $coordenador->id_utilizador,
            'encaminhada_em' => now(),
        ]);
    }

    private function libertarCoordenadorInvalido(): void
    {
        if ($this->id_coordenador !== null) {
            $this->update(['id_coordenador' => null]);
        }
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
