<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Dónde trata y guarda el proveedor los datos.
 *
 * Tres valores y no una lista de países: lo que cambia las obligaciones es salir
 * o no del EEE (RGPD, capítulo V). El país va en `ubicacion_detalle`.
 * «Desconocida» es un valor legítimo y el de partida: no saberlo es justo lo que
 * una evaluación tiene que descubrir.
 */
#[TypeScript]
enum UbicacionDatos: string
{
    case UeEee = 'ue_eee';
    case TercerPais = 'tercer_pais';
    case Desconocida = 'desconocida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::UeEee => 'Unión Europea / EEE',
            self::TercerPais => 'Fuera del EEE',
            self::Desconocida => 'Sin determinar',
        };
    }
}
