<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Soporte;

/**
 * Dónde guarda la sesión la organización en la que se está como soporte
 * (punto 44). Una sola clave, y en un solo sitio, porque la leen el middleware
 * del contexto, el de sólo lectura y los props compartidos.
 */
final class SesionDeSoporte
{
    public const CLAVE = 'soporte_organizacion_id';
}
