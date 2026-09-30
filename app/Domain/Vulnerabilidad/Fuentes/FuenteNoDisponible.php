<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad\Fuentes;

use RuntimeException;

/**
 * La fuente no contestó, contestó mal o está apagada por configuración.
 *
 * No es un error de quien pregunta: el formulario sigue sirviendo a mano, y así
 * se le dice.
 */
final class FuenteNoDisponible extends RuntimeException {}
