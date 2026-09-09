<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * El valor centinela de «ninguno» de los desplegables opcionales.
 *
 * Vive en su propia clase y no en el trait porque PHP no deja leer una constante
 * de trait desde fuera (`Cannot access trait constant directly`), y esto tiene
 * que poder nombrarse desde los tests.
 *
 * Su gemelo en el cliente es `SIN_VALOR` de `resources/js/lib/formularios.ts`.
 * Los dos tienen que decir lo mismo y sólo se escriben en esos dos sitios.
 */
final class SeleccionVacia
{
    public const VALOR = '__ninguno__';
}
