<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacao extends Model
{
    protected $table = 'notificacoes';

    protected $primaryKey = 'id_notificacao';

    protected $fillable = [
        'id_utilizador',
        'id_candidatura',
        'canal',
        'titulo',
        'mensagem',
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

    /**
     * Registo rápido de uma notificação dentro do sistema.
     */
    public static function enviar(
        int $idUtilizador,
        string $titulo,
        string $mensagem
    ): self {
        return self::create([
            'id_utilizador' => $idUtilizador,
            'canal' => 'sistema',
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'enviada_em' => now(),
        ]);
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
        string $mensagem
    ): int {
        $administradores = User::query()
            ->where('tipo_utilizador', 'administrador')
            ->pluck('id_utilizador');

        foreach ($administradores as $idAdministrador) {
            self::enviar((int) $idAdministrador, $titulo, $mensagem);
        }

        return $administradores->count();
    }
}
