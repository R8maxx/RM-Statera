<?php

declare(strict_types=1);

namespace App\Http\Resources\Metrica;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Dónde está un indicador hoy: la cifra del último periodo medido, cuánto le
 * queda para el objetivo y cuánto se ha movido desde el anterior.
 *
 * Es la franja de arriba de la ficha, y viaja **escrita**: la distancia de un
 * porcentaje son puntos y la de unos euros son euros, y componer eso en el
 * cliente sería la segunda copia de `UnidadIndicador::escribir()`.
 */
#[TypeScript]
final readonly class SituacionIndicador
{
    public function __construct(
        /** «T2 2026». */
        public string $periodo,
        public float $valor,
        public string $valorEscrito,
        /** «33 de 52»: la cifra sin su denominador no dice nada. */
        public ?string $fraccion,
        /** El objetivo que se aplicó a ese periodo, no el de hoy. */
        public ?string $objetivoEscrito,
        /** «Faltan 14 pts», «3 pts por encima». Nulo sin objetivo. */
        public ?string $distancia,
        public ?bool $alcanzado,
        /**
         * Dónde cae la cifra en una escala de 0 a 1, para la barra.
         *
         * Sólo la tiene un porcentaje, que es lo único con techo natural: un
         * recuento de evidencias caducadas no tiene «lleno» contra el que
         * dibujarse.
         */
        public ?float $posicion,
        public ?float $posicionObjetivo,
        /** «+17 pts». Nulo con una sola medición. */
        public ?string $variacion,
        /** «De 46 % en T1 2026». */
        public ?string $anterior,
    ) {}
}
