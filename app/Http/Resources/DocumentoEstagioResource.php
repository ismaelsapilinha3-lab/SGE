<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentoEstagioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_documento_estagio' => $this->id_documento_estagio,
            'id_estagio' => $this->id_estagio,
            'id_estagiario' => $this->id_estagiario,
            'tipo_documento' => $this->tipo_documento,
            'versao' => $this->versao,
            'nome_arquivo' => $this->nome_arquivo,
            'data_upload' => $this->data_upload?->toIso8601String(),
            'validade' => $this->validade?->toDateString(),
            'estado' => $this->estado,
            'descricao' => $this->descricao,
            'url' => "/api/documentos-estagio/{$this->id_documento_estagio}",
        ];
    }
}
