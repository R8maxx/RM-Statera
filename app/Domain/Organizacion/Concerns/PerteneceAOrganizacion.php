<?php

declare(strict_types=1);

namespace App\Domain\Organizacion\Concerns;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Organizacion\Scopes\OrganizacionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Todo modelo de datos propios usa este trait. Aporta dos de las tres capas de
 * aislamiento: el scope global y el relleno automático de `organizacion_id`.
 *
 * Rellenar la columna aquí y no en cada servicio evita el fallo más tonto y más
 * caro: crear una fila sin tenant, que con RLS activo nadie vuelve a ver.
 */
trait PerteneceAOrganizacion
{
    public static function bootPerteneceAOrganizacion(): void
    {
        static::addGlobalScope(new OrganizacionScope);

        // Se lee el atributo y no la propiedad: el `@property int` de cada
        // modelo describe la fila ya persistida, pero aquí todavía no lo está y
        // la columna puede no venir puesta.
        static::creating(function (Model $modelo): void {
            if ($modelo->getAttribute('organizacion_id') !== null) {
                return;
            }

            $modelo->setAttribute('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio());
        });
    }

    /** @return BelongsTo<Organizacion, $this> */
    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class);
    }
}
