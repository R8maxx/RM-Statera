<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un indicador de control del inventario.
 *
 * Lo que lo separa de la hoja «Resumen» del Excel es `filtro`: allí el número
 * es un callejón sin salida —«43 activos sin cifrar», y ahora búscalos— y aquí
 * es un enlace a la lista exacta. Una cifra que no lleva a la lista no se
 * acciona, se mira.
 *
 * El `tono` es un nombre de estado del dominio, nunca un color: los colores
 * viven en `app.css`.
 */
#[TypeScript]
final class IndicadorInventario
{
    public function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly int $valor,
        public readonly string $tono,
        /** La query string que aísla justo esos activos en la tabla. */
        public readonly string $filtro,
        public readonly ?string $ayuda = null,
    ) {}
}
