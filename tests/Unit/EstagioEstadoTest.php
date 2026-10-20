<?php

use App\Models\Assiduidade;
use App\Models\DocumentoEstagio;
use App\Models\Entrevista;
use App\Models\Estagio;
use App\Models\PlanoEstagio;

it('reconhece o estado de um estágio', function () {
    $ativo = new Estagio(['estado' => 'ativo']);
    $suspenso = new Estagio(['estado' => 'suspenso']);
    $concluido = new Estagio(['estado' => 'concluido']);

    expect($ativo->estaAtivo())->toBeTrue()
        ->and($ativo->estaTerminado())->toBeFalse()
        ->and($ativo->aceitaRegistos())->toBeTrue()
        ->and($suspenso->estaSuspenso())->toBeTrue()
        ->and($suspenso->aceitaRegistos())->toBeFalse()
        ->and($concluido->estaTerminado())->toBeTrue()
        ->and($concluido->aceitaRegistos())->toBeFalse();
});

it('conta presenças e atrasos como presença efetiva', function () {
    expect((new Assiduidade(['estado' => 'presente']))->contaComoPresenca())->toBeTrue()
        ->and((new Assiduidade(['estado' => 'atraso']))->contaComoPresenca())->toBeTrue()
        ->and((new Assiduidade(['estado' => 'falta']))->contaComoPresenca())->toBeFalse()
        ->and((new Assiduidade(['estado' => 'falta_justificada']))->contaComoPresenca())->toBeFalse();
});

it('reconhece o estado de decisão de um plano de estágio', function () {
    expect((new PlanoEstagio(['estado' => 'submetido']))->estaSubmetido())->toBeTrue()
        ->and((new PlanoEstagio(['estado' => 'aprovado']))->estaAprovado())->toBeTrue()
        ->and((new PlanoEstagio(['estado' => 'recusado']))->estaSubmetido())->toBeFalse();
});

it('reconhece um documento de estágio validado', function () {
    expect((new DocumentoEstagio(['estado' => 'validado']))->estaValidado())->toBeTrue()
        ->and((new DocumentoEstagio(['estado' => 'pendente']))->estaValidado())->toBeFalse();
});

it('reconhece uma entrevista já decidida', function () {
    expect((new Entrevista(['resultado' => 'pendente']))->estaDecidida())->toBeFalse()
        ->and((new Entrevista(['resultado' => 'aprovado']))->estaDecidida())->toBeTrue()
        ->and((new Entrevista(['resultado' => 'reprovado']))->estaDecidida())->toBeTrue();
});

it('valida as transições de estado permitidas', function () {
    $ativo = new Estagio(['estado' => 'ativo']);

    expect($ativo->podeTransitarPara('suspenso'))->toBeTrue()
        ->and($ativo->podeTransitarPara('concluido'))->toBeTrue()
        ->and($ativo->podeTransitarPara('cancelado'))->toBeTrue();
});

it('impede transições a partir de estados terminados e saltos inválidos', function () {
    expect((new Estagio(['estado' => 'concluido']))->podeTransitarPara('ativo'))
        ->toBeFalse()
        ->and((new Estagio(['estado' => 'cancelado']))->podeTransitarPara('ativo'))
        ->toBeFalse()
        ->and((new Estagio(['estado' => 'suspenso']))->podeTransitarPara('concluido'))
        ->toBeFalse()
        ->and((new Estagio(['estado' => 'suspenso']))->podeTransitarPara('ativo'))
        ->toBeTrue();
});
