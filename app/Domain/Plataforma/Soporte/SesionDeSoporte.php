<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Soporte;

use App\Domain\Organizacion\Models\Organizacion;

/**
 * Dónde guarda la sesión la organización en la que se está como soporte
 * (punto 44). Una sola clave, y en un solo sitio, porque la leen el middleware
 * del contexto, el de sólo lectura y los props compartidos.
 */
final class SesionDeSoporte
{
    public const CLAVE = 'soporte_organizacion_id';

    /**
     * La ventana por la que se entró: su `soporte_hasta` tal y como estaba.
     *
     * Con sólo la organización, si el cliente cerraba la puerta y la volvía a
     * abrir entre dos peticiones, quien estaba dentro seguía dentro por la
     * ventana nueva sin haber pasado por la entrada: sin evento en la traza y
     * sin correo al cliente. Cada ventana se entra de nuevo.
     */
    public const CLAVE_VENTANA = 'soporte_ventana';

    public static function ventana(Organizacion $organizacion): ?string
    {
        return $organizacion->soporte_hasta?->toIso8601String();
    }
}
