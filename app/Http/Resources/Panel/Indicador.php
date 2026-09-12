<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Una cifra que pide acción, con el camino para ir a verla.
 *
 * Lo que lo separa de la hoja «Resumen» del Excel es `filtro`: allí el número
 * es un callejón sin salida —«43 activos sin cifrar», y ahora búscalos— y aquí
 * es un enlace a la lista exacta. Una cifra que no lleva a la lista no se
 * acciona, se mira.
 *
 * **No es del inventario.** Nació ahí y la forma resultó ser la misma para
 * cualquier módulo que tenga tabla: la tira que los pinta se reutiliza igual en
 * activos y en tareas, y duplicar el DTO por módulo es el «cuatro dialectos
 * distintos para el sexto» que la capa de recursos existe para evitar. Por eso
 * `base` viaja con el indicador: quien lo pinta no tiene por qué saber de qué
 * tabla salió.
 *
 * El `tono` es un nombre de estado del dominio, nunca un color: los colores
 * viven en `app.css`.
 */
#[TypeScript]
final class Indicador
{
    public function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly int $valor,
        public readonly string $tono,
        /** La query string que aísla justo esas filas en la tabla. */
        public readonly string $filtro,
        /** La ruta de esa tabla: `/activos`, `/tareas`. */
        public readonly string $base,
        public readonly ?string $ayuda = null,
    ) {}
}
