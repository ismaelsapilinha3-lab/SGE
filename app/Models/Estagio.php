<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Estagio extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_estagio';

    protected $fillable = [
        'id_candidatura',
        'id_estagiario',
        'data_inicio',
        'data_fim_prevista',
        'data_fim_real',
        'estado',
        'motivo_suspensao',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim_prevista' => 'date',
            'data_fim_real' => 'date',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relações
    |--------------------------------------------------------------------------
    */

    public function candidatura(): BelongsTo
    {
        return $this->belongsTo(
            Candidatura::class,
            'id_candidatura',
            'id_candidatura'
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

    public function supervisores(): HasMany
    {
        return $this->hasMany(
            EstagioSupervisor::class,
            'id_estagio',
            'id_estagio'
        );
    }

    public function supervisoresAtivos(): HasMany
    {
        return $this->supervisores()->whereNull('removido_em');
    }

    /**
     * Contas dos supervisores atualmente atribuídos ao estágio.
     *
     * @return Collection<int, User>
     */
    public function supervisoresUtilizadores(): Collection
    {
        return User::whereIn(
            'id_utilizador',
            $this->supervisoresAtivos()->pluck('id_supervisor')
        )->get();
    }

    /**
     * Coordenador responsável pela candidatura de origem do estágio.
     */
    public function coordenadorResponsavel(): ?User
    {
        $idCoordenador = $this->candidatura?->id_coordenador;

        return $idCoordenador ? User::find($idCoordenador) : null;
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(
            DocumentoEstagio::class,
            'id_estagio',
            'id_estagio'
        );
    }

    public function planos(): HasMany
    {
        return $this->hasMany(
            PlanoEstagio::class,
            'id_estagio',
            'id_estagio'
        );
    }

    public function planoAprovado(): HasOne
    {
        return $this->hasOne(
            PlanoEstagio::class,
            'id_estagio',
            'id_estagio'
        )->where('estado', 'aprovado');
    }

    /**
     * Plano em vigor: o mais recente que ainda não foi substituído.
     */
    public function planoVigente(): ?PlanoEstagio
    {
        return $this->planos()
            ->where('estado', '!=', 'substituido')
            ->latest('id_plano_estagio')
            ->first();
    }

    public function assiduidades(): HasMany
    {
        return $this->hasMany(
            Assiduidade::class,
            'id_estagio',
            'id_estagio'
        );
    }

    public function atividades(): HasMany
    {
        return $this->hasMany(
            AtividadeEstagiario::class,
            'id_estagio',
            'id_estagio'
        );
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(
            Avaliacao::class,
            'id_estagio',
            'id_estagio'
        );
    }

    public function relatorios(): HasMany
    {
        return $this->hasMany(
            Relatorio::class,
            'id_estagio',
            'id_estagio'
        );
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(
            FeedbackEstagio::class,
            'id_estagio',
            'id_estagio'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Regras de estado
    |--------------------------------------------------------------------------
    */

    /**
     * Transições de estado permitidas a partir de cada estado.
     *
     * Um estágio começa sempre em `ativo` e termina em `concluido` ou
     * `cancelado`; a partir daí já não aceita alterações.
     *
     * @var array<string, list<string>>
     */
    public const TRANSICOES = [
        'ativo' => ['suspenso', 'concluido', 'cancelado'],
        'suspenso' => ['ativo', 'cancelado'],
        'concluido' => [],
        'cancelado' => [],
    ];

    public function estaAtivo(): bool
    {
        return $this->estado === 'ativo';
    }

    public function estaSuspenso(): bool
    {
        return $this->estado === 'suspenso';
    }

    public function estaTerminado(): bool
    {
        return in_array($this->estado, ['concluido', 'cancelado'], true);
    }

    /**
     * Indica se os registos de acompanhamento (assiduidade, atividades,
     * avaliações e relatórios) podem ser criados ou alterados.
     */
    public function aceitaRegistos(): bool
    {
        return $this->estaAtivo();
    }

    /**
     * Indica se os registos de fecho (avaliações e feedback) podem ser
     * criados ou alterados.
     *
     * Ao contrário dos restantes registos, estes acontecem no fim do
     * estágio, muitas vezes já depois de este ter sido concluído (o
     * agendador conclui-os automaticamente ao fim da data prevista).
     * Só um estágio cancelado os recusa.
     */
    public function aceitaRegistosDeFecho(): bool
    {
        return $this->estado !== 'cancelado';
    }

    /**
     * Indica se o estágio pode passar para o estado indicado.
     */
    public function podeTransitarPara(string $novoEstado): bool
    {
        return in_array(
            $novoEstado,
            self::TRANSICOES[$this->estado] ?? [],
            true
        );
    }

    /**
     * Indica se a data indicada cai dentro do período do estágio.
     *
     * O limite superior é a data real de fim, quando o estágio já terminou;
     * caso contrário, a data prevista.
     */
    public function contemData(string $data): bool
    {
        $dia = substr($data, 0, 10);

        $inicio = $this->data_inicio?->format('Y-m-d');

        $fim = ($this->data_fim_real ?? $this->data_fim_prevista)
            ?->format('Y-m-d');

        if ($inicio !== null && $dia < $inicio) {
            return false;
        }

        if ($fim !== null && $dia > $fim) {
            return false;
        }

        return true;
    }
}
