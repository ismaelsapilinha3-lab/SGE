<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanoEstagioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_plano_estagio' => $this->id_plano_estagio,
            'id_estagio' => $this->id_estagio,
            'origem' => $this->origem,
            'id_supervisor' => $this->id_supervisor,
            'nome_arquivo' => $this->nome_arquivo,
            'formato' => $this->formato,
            'tamanho' => $this->tamanho,
            'versao' => $this->versao,
            'data_upload' => $this->data_upload?->toIso8601String(),
            'objetivos' => $this->objetivos,
            'atividades_previstas' => $this->atividades_previstas,
            'cronograma' => $this->cronograma,
            'data_inicio' => $this->data_inicio?->toDateString(),
            'data_fim' => $this->data_fim?->toDateString(),
            'estado' => $this->estado,
            'motivo_recusa' => $this->motivo_recusa,
            'id_coordenador' => $this->id_coordenador,
            'data_decisao' => $this->data_decisao?->toIso8601String(),
            'tem_ficheiro' => $this->caminho_arquivo !== null,
            'url' => $this->caminho_arquivo
                ? "/api/estagiario/planos/{$this->id_plano_estagio}/ficheiro"
                : null,
        ];
    }
}
