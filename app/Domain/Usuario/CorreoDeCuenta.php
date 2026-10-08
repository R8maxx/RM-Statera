<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * La regla del correo del responsable al dar de alta una organización **desde
 * la plataforma**.
 *
 * Es único en todo Statera, porque es con lo que se entra. La excepción es
 * quien administra la plataforma y no es de ninguna organización (punto 45):
 * entonces `AltaOrganizacion` le une con su cuenta (`UnirAdministrador`).
 *
 * **No vale para el alta de cuentas de un cliente**, que sigue con el
 * `unique` estricto: un cliente no puede unir a un administrador ni averiguar
 * que un correo es suyo.
 */
final class CorreoDeCuenta
{
    public static function libre(): Unique
    {
        return Rule::unique('users', 'email')->where(
            static fn ($consulta) => $consulta->where('es_plataforma', false)->orWhereNotNull('organizacion_id'),
        );
    }
}
