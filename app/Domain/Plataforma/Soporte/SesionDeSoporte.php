<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Soporte;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Models\User;

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

    /**
     * Si la cuenta está ahora dentro de un cliente **como soporte**: es de la
     * plataforma y el contexto fijado no es el de su propia organización.
     *
     * En la suya (punto 45) es un usuario más y trabaja con su rol; fuera de
     * ella sólo lee. Es la pregunta que se hacen `Gate::before`,
     * `SoporteSoloLectura` y los props compartidos, y se contesta aquí para
     * que los tres digan lo mismo.
     */
    public static function activo(?User $usuario, ContextoOrganizacion $contexto): bool
    {
        return $usuario !== null
            && $usuario->esPlataforma()
            && $contexto->hayContexto()
            && ! $usuario->esSuOrganizacion($contexto->id());
    }

    public static function ventana(Organizacion $organizacion): ?string
    {
        return $organizacion->soporte_hasta?->toIso8601String();
    }
}
