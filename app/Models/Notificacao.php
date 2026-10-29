<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class Notificacao extends Model
{
    use HasFactory;

    protected $table = 'notificacoes';

    protected $primaryKey = 'id_notificacao';

    protected $fillable = [
        'id_utilizador',
        'id_candidatura',
        'canal',
        'titulo',
        'mensagem',
        'url',
        'enviada_em',
        'lida_em',
    ];

    protected function casts(): array
    {
        return [
            'enviada_em' => 'datetime',
            'lida_em' => 'datetime',
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

    public function candidatura(): BelongsTo
    {
        return $this->belongsTo(
            Candidatura::class,
            'id_candidatura',
            'id_candidatura'
        );
    }

    public function scopeNaoLidas(Builder $query): Builder
    {
        return $query->whereNull('lida_em');
    }

    public function estaLida(): bool
    {
        return $this->lida_em !== null;
    }

    public function marcarComoLida(): void
    {
        if ($this->lida_em === null) {
            $this->forceFill(['lida_em' => now()])->save();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Criação
    |--------------------------------------------------------------------------
    */

    /**
     * Registo rápido de uma notificação dentro do sistema.
     */
    public static function enviar(
        int $idUtilizador,
        string $titulo,
        string $mensagem,
        ?string $url = null
    ): self {
        return self::create([
            'id_utilizador' => $idUtilizador,
            'canal' => 'sistema',
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'url' => $url,
            'enviada_em' => now(),
        ]);
    }

    /**
     * Regista uma notificação de sistema para um utilizador com conta.
     *
     * Sem destinatário não é criada notificação (por exemplo, um estágio
     * cuja conta de estagiário já não existe).
     */
    public static function paraUtilizador(
        User|int|null $utilizador,
        string $titulo,
        string $mensagem,
        ?string $url = null
    ): ?self {
        if ($utilizador === null) {
            Log::warning(
                'Notificação sem destinatário.',
                ['titulo' => $titulo]
            );

            return null;
        }

        return self::create([
            'id_utilizador' => $utilizador instanceof User
                ? $utilizador->id_utilizador
                : $utilizador,

            'canal' => 'sistema',

            'titulo' => $titulo,

            'mensagem' => $mensagem,

            'url' => $url,

            'enviada_em' => now(),
        ]);
    }

    /**
     * Regista a mesma notificação de sistema para vários utilizadores.
     *
     * @param  iterable<int, User|int>  $utilizadores
     */
    public static function paraUtilizadores(
        iterable $utilizadores,
        string $titulo,
        string $mensagem,
        ?string $url = null
    ): void {
        foreach ($utilizadores as $utilizador) {
            self::paraUtilizador($utilizador, $titulo, $mensagem, $url);
        }
    }

    /**
     * Aviso interno entregue a todos os administradores.
     *
     * A tabela de notificações exige sempre um destinatário, por isso
     * o aviso é enviado a cada conta de administrador.
     *
     * @return int Número de administradores notificados.
     */
    public static function paraAdministradores(
        string $titulo,
        string $mensagem,
        ?string $url = null
    ): int {
        $administradores = User::query()
            ->where('tipo_utilizador', 'administrador')
            ->pluck('id_utilizador');

        foreach ($administradores as $idAdministrador) {
            self::enviar(
                (int) $idAdministrador,
                $titulo,
                $mensagem,
                $url
            );
        }

        return $administradores->count();
    }

    /**
     * Regista uma notificação de sistema para o candidato de uma candidatura.
     *
     * Fica sempre ligada à candidatura e, quando já existe conta, também ao
     * utilizador, para que o candidato a possa consultar.
     */
    public static function paraCandidato(
        Candidatura $candidatura,
        string $titulo,
        string $mensagem,
        ?string $url = null
    ): self {
        return self::paraCandidatoPorId(
            $candidatura->id_candidatura,
            $candidatura->id_utilizador,
            $titulo,
            $mensagem,
            $url
        );
    }

    /**
     * Variante usada pelo pipeline de notificações em fila, que não tem
     * acesso à instância da candidatura.
     */
    public static function paraCandidatoPorId(
        int $idCandidatura,
        ?int $idUtilizador,
        string $titulo,
        string $mensagem,
        ?string $url = null
    ): self {
        return self::create([
            'id_utilizador' => $idUtilizador,

            'id_candidatura' => $idCandidatura,

            'canal' => 'sistema',

            'titulo' => $titulo,

            'mensagem' => $mensagem,

            'url' => $url,

            'enviada_em' => now(),
        ]);
    }
}
