<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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
}
