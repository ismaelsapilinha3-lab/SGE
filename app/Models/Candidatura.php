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
        'id_grupo_estagio',
        'duracao_meses',
        'id_coordenador',
        'id_diretor',
        'parecer_coordenador',
        'parecer_coordenador_motivo',
        'parecer_coordenador_em',
        'decisao_diretor',
        'decisao_diretor_motivo',
        'decisao_diretor_em',
        'encaminhada_em',
        'consentimento_dados',
        'consentimento_dados_em',
        'consentimento_versao',
        'bi_ativo',
    ];

    protected $casts = [
        'nascimento' => 'date',
        'duracao_meses' => 'integer',
        'data_decisao' => 'datetime',
        'parecer_coordenador_em' => 'datetime',
        'decisao_diretor_em' => 'datetime',
        'encaminhada_em' => 'datetime',
        'consentimento_dados' => 'boolean',
        'consentimento_dados_em' => 'datetime',
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

    public function diretor()
    {
        return $this->belongsTo(
            User::class,
            'id_diretor',
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

    public function grupoEstagio()
    {
        return $this->belongsTo(
            GrupoEstagio::class,
            'id_grupo_estagio',
            'id_grupo_estagio'
        );
    }

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
            ->where(function ($query) {
                $query->whereHas('perfil', function ($q) {
                    $q->where(
                        'id_departamento',
                        $this->id_departamento_selecionado
                    );
                })->orWhere(
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

    public function garantirDiretor(?User $preferido = null): ?User
    {
        if ($this->id_diretor !== null && $this->temDiretorValido()) {
            return $this->diretor;
        }

        if (
            $preferido !== null
            && $this->diretorPertenceADirecao($preferido)
        ) {
            $this->atribuirDiretor($preferido);

            return $preferido;
        }

        if ($this->id_departamento_selecionado === null) {
            $this->libertarDiretorInvalido();

            return null;
        }

        $diretor = User::query()
            ->where('tipo_utilizador', 'diretor')
            ->where('ativo', true)
            ->where(function ($query) {
                $query->whereHas('perfil', function ($q) {
                    $q->where(
                        'id_departamento',
                        $this->id_departamento_selecionado
                    );
                })->orWhere(
                    'id_departamento',
                    $this->id_departamento_selecionado
                );
            })
            ->with('perfil')
            ->first();

        if (! $diretor) {
            $this->libertarDiretorInvalido();

            return null;
        }

        $this->atribuirDiretor($diretor);

        return $diretor;
    }

    public function podeSerDecididaPeloDiretor(User $user): bool
    {
        if (! $this->diretorPertenceADirecao($user)) {
            return false;
        }

        return $this->id_diretor === null
            || (int) $this->id_diretor === (int) $user->id_utilizador
            || ! $this->temDiretorValido();
    }

    public function temDiretorValido(): bool
    {
        return $this->diretor !== null
            && $this->diretorPertenceADirecao($this->diretor);
    }

    public function estaEmAnalise(): bool
    {
        return $this->estado === 'em_analise';
    }

    public function aguardaDiretor(): bool
    {
        return $this->estado === 'aguarda_diretor';
    }

    public function foiAprovada(): bool
    {
        return $this->estado === 'aprovada';
    }

    public function foiRecusada(): bool
    {
        return $this->estado === 'recusada';
    }

    private function diretorPertenceADirecao(User $user): bool
    {
        if (
            ! $user->ativo
            || $user->trashed()
            || $user->tipo_utilizador !== 'diretor'
        ) {
            return false;
        }

        $idDepartamento = $user->idDepartamento();

        return $idDepartamento !== null
            && $this->id_departamento_selecionado !== null
            && $idDepartamento
                === (int) $this->id_departamento_selecionado;
    }

    private function atribuirDiretor(User $diretor): void
    {
        $this->update([
            'id_diretor' => $diretor->id_utilizador,
        ]);
    }

    private function libertarDiretorInvalido(): void
    {
        if ($this->id_diretor !== null) {
            $this->update(['id_diretor' => null]);
        }
    }

    public function podeSerCoordenadaPor(User $user): bool
    {
        if (! $this->coordenadorPertenceADirecao($user)) {
            return false;
        }

        return $this->id_coordenador === null
            || (int) $this->id_coordenador === (int) $user->id_utilizador
            || ! $this->temCoordenadorValido();
    }

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

        $idDepartamento = $user->idDepartamento();

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

    public function pertenceAo(User $utilizador): bool
    {
        if ($this->id_utilizador !== null) {
            return (int) $this->id_utilizador
                === (int) $utilizador->id_utilizador;
        }

        return $this->email === $utilizador->email;
    }
}
