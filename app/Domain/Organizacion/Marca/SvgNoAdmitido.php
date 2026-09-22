<?php

declare(strict_types=1);

namespace App\Domain\Organizacion\Marca;

use RuntimeException;

/**
 * El SVG no pasa el saneado, o pasa y no cabe.
 *
 * Es excepción de dominio y no un 500: lo que hay detrás es un fichero que
 * alguien acaba de elegir, así que el mensaje tiene que poder enseñarse al lado
 * del campo. El controlador la traduce a un error de validación.
 */
final class SvgNoAdmitido extends RuntimeException {}
