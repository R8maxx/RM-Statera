<?php

declare(strict_types=1);

namespace App\Http\Resources\Metrica;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un punto de la serie de un indicador, listo para pintarse.
 *
 * Lleva la cifra ya escrita (`valorEscrito`) además del número crudo: el número
 * lo necesita la gráfica para calcular la escala y el texto lo necesita la tabla
 * y el lector de pantalla, y componer el formato en el cliente sería la segunda
 * copia de `UnidadIndicador::escribir()`.
 *
 * `cumplimiento` viaja como tono y etiqueta resueltos por el dominio, no como
 * valor de enum: es la misma regla que ya siguen `EstadoTarea` y las bandas de
 * `CalculoRiesgo`, y evita un mapa de colores propio en el componente.
 */
#[TypeScript]
final readonly class PuntoSerie
{
    public function __construct(
        /** Inicio del periodo, ISO, que es lo que ordena la serie. */
        public string $periodo,
        /** «T1 2026», que es lo que se lee en el eje. */
        public string $etiqueta,
        public float $valor,
        public string $valorEscrito,
        public ?float $objetivo,
        public ?string $objetivoEscrito,
        public ?string $fraccion,
        public string $cumplimiento,
        public string $cumplimientoEtiqueta,
        public string $tono,
        public string $icono,
        public string $origen,
        public ?string $nota,
    ) {}
}
