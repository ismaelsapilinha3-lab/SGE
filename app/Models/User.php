<?php

namespace App\Models;

use App\Notifications\RecuperarPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'utilizadores';

    protected $primaryKey = 'id_utilizador';

    protected $fillable = [
        'nome',
        'email',
        'password',
        'telefone',
        'foto',
        'tipo_utilizador',
        'ativo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }

    /**
     * URL público da foto de perfil, ou `null` quando não existe.
     */
    public function fotoUrl(): ?string
    {
        if (! $this->foto) {
            return null;
        }

        return Storage::disk('public')->url($this->foto);
    }

    /**
     * Relação entre o utilizador e o seu perfil.
     */
    public function perfil(): HasOne
    {
        return $this->hasOne(
            Perfil::class,
            'id_utilizador',
            'id_utilizador'
        );
    }

    /**
     * Notificações destinadas ao utilizador.
     */
    public function notificacoes(): HasMany
    {
        return $this->hasMany(
            Notificacao::class,
            'id_utilizador',
            'id_utilizador'
        );
    }

    /**
     * Envia o link de recuperação para o portal Angular.
     *
     * O Laravel usa este método no fluxo de `Password::sendResetLink()`.
     */
    public function sendPasswordResetNotification($token): void
    {
        /*
         * Contas desativadas não recebem o link. A resposta da API continua
         * genérica, para não revelar o estado da conta.
         */
        if (! $this->ativo) {
            return;
        }

        $url = rtrim((string) config('app.frontend_url'), '/')
            .'/redefinir-password?'
            .http_build_query([
                'token' => $token,
                'email' => $this->email,
            ]);

        $this->notify(new RecuperarPassword(
            $url,
            $this->nome,
            (int) config('auth.passwords.users.expire', 60),
        ));
    }
}
