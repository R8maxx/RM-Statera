<?php

declare(strict_types=1);

namespace App\Http\Resources\Riesgo;

use App\Domain\Riesgo\Models\RiesgoValoracion;
use App\Http\Resources\Definicion\ValorEtiquetado;

/**
 * El nivel de un riesgo, pintado como badge.
 *
 * Vive aquí y no en cada pantalla porque ya hay dos sitios que lo piden —la
 * tabla de riesgos y el bloque de la ficha de un activo— y el mapa
 * nivel → color ya se copió cinco veces una vez en este producto, que es el
 * motivo por el que existe `lib/tonos.ts`.
 *
 * **Se lee con la escala CONGELADA de esa valoración, nunca con la vigente**:
 * interpretar un número de marzo con la escala de octubre es exactamente lo que
 * la instantánea existe para impedir.
 *
 * La cifra va dentro de la etiqueta —«Alto (12)»— porque el nivel solo no dice
 * cuánto de alto, y en una celda no cabe una segunda columna. La ficha del
 * riesgo los imprime por separado y por eso serializa lo suyo aparte.
 */
final class NivelDeRiesgo
{
    public static function badge(?RiesgoValoracion $valoracion, bool $residual = false): ?ValorEtiquetado
    {
        if ($valoracion === null) {
            return null;
        }

        $cifra = $residual ? $valoracion->riesgo_residual : $valoracion->riesgo_intrinseco;
        $nivel = $residual ? $valoracion->nivelResidual() : $valoracion->nivelIntrinseco();

        if ($cifra === null || $nivel === null) {
            return null;
        }

        return new ValorEtiquetado(
            (string) $cifra,
            "{$nivel->etiqueta()} ({$cifra})",
            $nivel->tono(),
            $nivel->icono(),
        );
    }
}
