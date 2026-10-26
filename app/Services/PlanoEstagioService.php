<?php

namespace App\Services;

use App\Models\Estagio;
use App\Models\PlanoEstagio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlanoEstagioService
{
    public function __construct(
        private readonly NotificacaoService $notificacoes
    ) {}

    /**
     * Submete (ou refina) o plano de atividades do supervisor.
     *
     * @param  array<string, mixed>  $dados
     * @return array{plano: PlanoEstagio, criado: bool}
     */
    public function submeter(
        Estagio $estagio,
        User $supervisor,
        array $dados
    ): array {

        return DB::transaction(function () use (
            $estagio,
            $supervisor,
            $dados
        ) {

            $plano = $estagio->planoVigente();

            $criado = false;

            if ($plano && $plano->estaAprovado()) {
                $plano->update([
                    'estado' => 'substituido',
                ]);

                $plano = null;
            }

            $conteudo = [
                'objetivos' => $dados['objetivos'],

                'atividades_previstas' => $dados['atividades_previstas'],

                'cronograma' => $dados['cronograma'],

                'data_inicio' => $dados['data_inicio'],

                'data_fim' => $dados['data_fim'],
            ];

            if (! $plano) {
                $plano = $estagio->planos()->create(
                    $conteudo + [
                        'origem' => PlanoEstagio::ORIGEM_SUPERVISOR,
                        'id_supervisor' => $supervisor->id_utilizador,
                        'estado' => 'submetido',
                    ]
                );

                $criado = true;
            } elseif ($plano->estado === 'recusado') {
                $plano->registarHistorico($supervisor->id_utilizador);

                $plano->update($conteudo + [
                    'estado' => 'submetido',
                    'motivo_recusa' => null,
                    'id_coordenador' => null,
                    'data_decisao' => null,
                ]);
            } else {
                $plano->update($conteudo);
            }

            /*
             * A candidatura pode ter sido criada antes de existir um
             * coordenador para a direção. Nesse caso, assume-a agora um
             * coordenador ativo, para que a submissão não fique sem
             * destinatário.
             */
            $coordenador = $estagio->candidatura?->garantirCoordenador();

            if ($coordenador) {
                $this->notificacoes->paraUtilizador(
                    $coordenador,
                    'Plano de estágio submetido',
                    "O supervisor submeteu o plano do estágio #{$estagio->id_estagio} para a tua aprovação."
                );
            } else {
                Log::warning(
                    'Plano de estágio submetido sem coordenador responsável.',
                    [
                        'id_estagio' => $estagio->id_estagio,
                        'id_candidatura' => $estagio->id_candidatura,
                        'id_supervisor' => $supervisor->id_utilizador,
                    ]
                );
            }

            return [
                'plano' => $plano,
                'criado' => $criado,
            ];
        });
    }
}
