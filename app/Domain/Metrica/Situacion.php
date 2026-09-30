<?php

declare(strict_types=1);

namespace App\Domain\Metrica;

use App\Domain\Metrica\Enums\SentidoIndicador;
use App\Domain\Metrica\Enums\UnidadIndicador;
use App\Domain\Metrica\Models\Indicador;
use App\Http\Resources\Metrica\PuntoSerie;
use App\Http\Resources\Metrica\SituacionIndicador;

/**
 * La lectura de hoy de un indicador, sacada de su serie.
 *
 * Sale de la serie y no de otra consulta a propósito: la franja de arriba de la
 * ficha y el último punto de la gráfica tienen que decir la misma cifra, y dos
 * consultas son dos ocasiones de que no la digan.
 *
 * **La distancia se mide contra el objetivo congelado en la fila**, igual que el
 * veredicto (`Indicador::cumplimiento()`). Contra el de hoy, subir el listón
 * cambiaría lo que faltaba en un periodo que ya cerró.
 */
final readonly class Situacion
{
    /** @param list<PuntoSerie> $serie de lo antiguo a lo reciente, como la da `SerieIndicador` */
    public function de(Indicador $indicador, array $serie): ?SituacionIndicador
    {
        $ultimo = $serie[count($serie) - 1] ?? null;

        if ($ultimo === null) {
            return null;
        }

        $anterior = $serie[count($serie) - 2] ?? null;
        $unidad = $indicador->unidad;
        $porcentaje = $unidad === UnidadIndicador::Porcentaje;

        $alcanzado = $ultimo->objetivo === null
            ? null
            : $indicador->sentido->alcanza($ultimo->valor, $ultimo->objetivo);

        return new SituacionIndicador(
            periodo: $ultimo->etiqueta,
            valor: $ultimo->valor,
            valorEscrito: $ultimo->valorEscrito,
            fraccion: $ultimo->fraccion,
            objetivoEscrito: $ultimo->objetivoEscrito,
            distancia: $ultimo->objetivo === null || $alcanzado === null
                ? null
                : $this->distancia($unidad, $indicador->sentido, $ultimo->valor - $ultimo->objetivo, $alcanzado),
            alcanzado: $alcanzado,
            posicion: $porcentaje ? $this->enEscala($ultimo->valor) : null,
            posicionObjetivo: $porcentaje && $ultimo->objetivo !== null ? $this->enEscala($ultimo->objetivo) : null,
            variacion: $anterior === null ? null : $this->variacion($unidad, $ultimo->valor - $anterior->valor),
            anterior: $anterior === null ? null : "De {$anterior->valorEscrito} en {$anterior->etiqueta}",
        );
    }

    /**
     * Lo que queda, o lo que sobra, dicho hacia donde mejora el indicador.
     *
     * «Faltan» sólo tiene sentido cuando más es mejor: en «evidencias caducadas
     * ≤ 0», estar en tres no es que falten tres, es que **sobran**.
     */
    private function distancia(UnidadIndicador $unidad, SentidoIndicador $sentido, float $diferencia, bool $alcanzado): string
    {
        $cantidad = $unidad->escribirDiferencia($diferencia);

        // Por debajo de lo que la unidad sabe escribir, «faltan 0 pts» sería
        // verdad a medias en los dos sentidos.
        if ($cantidad === $unidad->escribirDiferencia(0.0)) {
            return $alcanzado ? 'Justo en el objetivo' : 'Rozando el objetivo';
        }

        return match (true) {
            $alcanzado && $sentido === SentidoIndicador::MayorMejor => "{$cantidad} por encima",
            $alcanzado => "{$cantidad} por debajo",
            $sentido === SentidoIndicador::MayorMejor => "Faltan {$cantidad}",
            default => "Sobran {$cantidad}",
        };
    }

    private function variacion(UnidadIndicador $unidad, float $diferencia): string
    {
        $cantidad = $unidad->escribirDiferencia($diferencia);

        if ($cantidad === $unidad->escribirDiferencia(0.0)) {
            return 'Sin cambio';
        }

        return ($diferencia > 0 ? '+' : '−').$cantidad;
    }

    private function enEscala(float $porcentaje): float
    {
        return min(max($porcentaje / 100, 0.0), 1.0);
    }
}
