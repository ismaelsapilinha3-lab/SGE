<?php

namespace App\Models;

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
}
