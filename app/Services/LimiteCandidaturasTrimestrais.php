<?php

namespace App\Services;

use App\Models\Candidatura;
use Carbon\CarbonImmutable;

/**
 * Controla o limite de candidaturas por trimestre.
 *
 * O programa de estágios admite um máximo de 100 candidaturas por
 * trimestre civil. O limite é verificado no servidor para que não
 * possa ser contornado a partir do portal.
 */
class LimiteCandidaturasTrimestrais
{
    /** Número máximo de candidaturas admitidas por trimestre. */
    public const MAXIMO_POR_TRIMESTRE = 100;

    /**
     * Devolve o intervalo do trimestre que contém a data indicada.
     *
     * @return array{inicio: CarbonImmutable, fim: CarbonImmutable, numero: int, ano: int}
     */
    public function trimestreDe(?CarbonImmutable $data = null): array
    {
        $referencia = ($data ?? CarbonImmutable::now())->startOfQuarter();

        return [
            'inicio' => $referencia,
            'fim' => $referencia->endOfQuarter(),
            'numero' => $referencia->quarter,
            'ano' => $referencia->year,
        ];
    }

    /**
     * Número de candidaturas submetidas no trimestre corrente.
     */
    public function totalNoTrimestre(?CarbonImmutable $data = null): int
    {
        $trimestre = $this->trimestreDe($data);

        return Candidatura::query()
            ->whereBetween('created_at', [
                $trimestre['inicio'],
                $trimestre['fim'],
            ])
            ->count();
    }

    /**
     * Indica se o trimestre corrente já atingiu o limite.
     */
    public function limiteAtingido(?CarbonImmutable $data = null): bool
    {
        return $this->totalNoTrimestre($data)
            >= self::MAXIMO_POR_TRIMESTRE;
    }

    /**
     * Candidaturas ainda disponíveis no trimestre corrente.
     */
    public function vagasRestantes(?CarbonImmutable $data = null): int
    {
        return max(
            self::MAXIMO_POR_TRIMESTRE - $this->totalNoTrimestre($data),
            0
        );
    }

    /**
     * Mensagem de erro usada quando o limite é atingido.
     */
    public function mensagemLimiteAtingido(?CarbonImmutable $data = null): string
    {
        $trimestre = $this->trimestreDe($data);

        return sprintf(
            'O limite de %d candidaturas para o %d.º trimestre de %d já foi atingido. '
            .'Volta a tentar no próximo trimestre.',
            self::MAXIMO_POR_TRIMESTRE,
            $trimestre['numero'],
            $trimestre['ano']
        );
    }
}
