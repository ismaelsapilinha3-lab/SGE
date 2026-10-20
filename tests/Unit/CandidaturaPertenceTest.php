<?php

use App\Models\Candidatura;
use App\Models\User;

/**
 * A propriedade de uma candidatura decide-se por id_utilizador.
 * O e-mail só serve de regresso para candidaturas antigas sem a chave.
 */
function utilizadorComId(int $id, string $email): User
{
    return (new User)->forceFill([
        'id_utilizador' => $id,
        'email' => $email,
    ]);
}

it('reconhece o dono da candidatura pela chave id_utilizador', function () {
    $dono = utilizadorComId(7, 'dono@exemplo.com');
    $outro = utilizadorComId(8, 'outro@exemplo.com');

    $candidatura = new Candidatura([
        'id_utilizador' => 7,
        'email' => 'dono@exemplo.com',
    ]);

    expect($candidatura->pertenceAo($dono))->toBeTrue()
        ->and($candidatura->pertenceAo($outro))->toBeFalse();
});

it('ignora o e-mail quando a chave id_utilizador esta preenchida', function () {
    /*
     * Regressão: antes desta alteração, alterar o e-mail de uma conta
     * movia a candidatura (e os documentos) para outra conta.
     */
    $dono = utilizadorComId(7, 'novo-email@exemplo.com');
    $outro = utilizadorComId(8, 'antigo-email@exemplo.com');

    $candidatura = new Candidatura([
        'id_utilizador' => 7,
        'email' => 'antigo-email@exemplo.com',
    ]);

    expect($candidatura->pertenceAo($dono))->toBeTrue()
        ->and($candidatura->pertenceAo($outro))->toBeFalse();
});

it('usa o e-mail apenas como regresso quando nao ha chave', function () {
    $dono = utilizadorComId(7, 'dono@exemplo.com');
    $outro = utilizadorComId(8, 'outro@exemplo.com');

    $candidatura = new Candidatura([
        'id_utilizador' => null,
        'email' => 'dono@exemplo.com',
    ]);

    expect($candidatura->pertenceAo($dono))->toBeTrue()
        ->and($candidatura->pertenceAo($outro))->toBeFalse();
});

it('nao pertence a ninguem quando nao ha chave nem e-mail coincidente', function () {
    $outro = utilizadorComId(8, 'outro@exemplo.com');

    $candidatura = new Candidatura([
        'id_utilizador' => null,
        'email' => 'dono@exemplo.com',
    ]);

    expect($candidatura->pertenceAo($outro))->toBeFalse();
});
