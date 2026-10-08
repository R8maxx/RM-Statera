<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Exportacion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Los modelos que guardan datos de un cliente, descubiertos y no enumerados
 * (punto 56).
 *
 * Todo modelo de `app/Domain/*\/Models/` cuya tabla tiene `organizacion_id`.
 * Es el mismo recorrido que `modelosDelDominio()` de los tests, y por lo mismo:
 * un módulo nuevo entra en la exportación sin que nadie se acuerde. Uno que se
 * quedara fuera haría una exportación incompleta sin que fallara nada, que es
 * el peor fallo posible para una exportación.
 */
final class ModelosDelCliente
{
    /**
     * @return list<class-string<Model>>
     */
    public function todos(): array
    {
        $modelos = [];

        foreach (glob(app_path('Domain/*/Models/*.php')) ?: [] as $ruta) {
            $clase = 'App\\Domain\\'.str_replace('/', '\\', substr((string) strstr($ruta, 'Domain/'), 7, -4));

            if (! class_exists($clase) || ! is_subclass_of($clase, Model::class)) {
                continue;
            }

            if (Schema::hasColumn((new $clase)->getTable(), 'organizacion_id')) {
                $modelos[] = $clase;
            }
        }

        sort($modelos);

        return $modelos;
    }
}
