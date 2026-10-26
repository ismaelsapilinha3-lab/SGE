<?php

namespace App\Services;

use App\Models\AcessoFicheiro;
use App\Models\Documento;
use App\Models\DocumentoEstagio;
use App\Models\PlanoEstagio;
use App\Models\Relatorio;
use App\Models\User;
use Illuminate\Http\Request;

class AcessoFicheiroService
{
    public function registarDocumento(
        Request $request,
        User $utilizador,
        Documento $documento
    ): void {
        $this->registar($request, $utilizador, [
            'id_documento' => $documento->id_documento,
        ]);
    }

    public function registarDocumentoEstagio(
        Request $request,
        User $utilizador,
        DocumentoEstagio $documento
    ): void {
        $this->registar($request, $utilizador, [
            'id_documento_estagio' => $documento->id_documento_estagio,
        ]);
    }

    public function registarRelatorio(
        Request $request,
        User $utilizador,
        Relatorio $relatorio
    ): void {
        $this->registar($request, $utilizador, [
            'id_relatorio' => $relatorio->id_relatorio,
        ]);
    }

    public function registarPlano(
        Request $request,
        User $utilizador,
        PlanoEstagio $plano
    ): void {
        $this->registar($request, $utilizador, [
            'id_plano_estagio' => $plano->id_plano_estagio,
        ]);
    }

    /**
     * @param  array<string, int>  $recurso
     */
    private function registar(
        Request $request,
        User $utilizador,
        array $recurso
    ): void {
        AcessoFicheiro::create([
            'id_utilizador' => $utilizador->id_utilizador,
            ...$recurso,
            'ip' => $request->ip(),
            'user_agent' => mb_substr(
                (string) $request->userAgent(),
                0,
                1000
            ),
        ]);
    }
}
