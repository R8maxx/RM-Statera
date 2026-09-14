<?php

declare(strict_types=1);

namespace App\Domain\Riesgo\Excepciones;

use DomainException;

/**
 * Una escala de la metodología que no se puede usar para medir.
 *
 * Se lanza desde el value object y no desde el `FormRequest` porque la
 * metodología también la escriben el seeder y la fábrica, y una escala con un
 * hueco rompe la matriz igual llegue de donde llegue.
 */
final class EscalaInvalida extends DomainException {}
