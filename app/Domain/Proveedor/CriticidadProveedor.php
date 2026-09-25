<?php

declare(strict_types=1);

namespace App\Domain\Proveedor;

use App\Domain\Activo\Models\Activo;
use App\Domain\Proveedor\Enums\Criticidad;
use App\Domain\Proveedor\Excepciones\OperacionDeProveedorNoPermitida;
use App\Domain\Proveedor\Models\Proveedor;

/**
 * El mínimo de criticidad de un proveedor, derivado de lo que presta.
 *
 * **Se deriva y no se elige**, en la línea del invariante 4: la valoración más
 * alta de los activos que presta —su valoración propia en las cinco
 * dimensiones— da el mínimo. Declararla por encima es libre; por debajo exige
 * justificarlo, porque es lo que decide cada cuánto se reevalúa, y bajarla sin
 * decir por qué es la forma más barata de no volver a mirar un contrato.
 *
 * Un proveedor sin activos no tiene derivada y la declara: una gestoría o la
 * limpieza con acceso físico no prestan nada del inventario y siguen siendo
 * terceros.
 */
final class CriticidadProveedor
{
    public function __construct(private readonly RecalcularReevaluacion $reevaluacion) {}

    /** La que dicen sus activos, o nula si no presta ninguno valorado. */
    public function derivadaDe(Proveedor $proveedor): ?Criticidad
    {
        $maxima = null;

        foreach (Activo::query()->where('proveedor_id', $proveedor->id)->get() as $activo) {
            $criticidad = Criticidad::desdeNivel($activo->valoracion()->nivelMaximo());

            if ($criticidad !== null && ($maxima === null || $criticidad->peso() > $maxima->peso())) {
                $maxima = $criticidad;
            }
        }

        return $maxima;
    }

    /**
     * Comprueba lo que se declara contra lo que se deriva.
     *
     * Vale igual para el formulario y para el seeder, y es la única que decide:
     * el `CHECK` sólo sabe que al menos una de las dos existe.
     */
    public function validar(?Criticidad $derivada, ?Criticidad $declarada, ?string $justificacion): void
    {
        if ($derivada === null && $declarada === null) {
            throw OperacionDeProveedorNoPermitida::sinCriticidad();
        }

        if ($derivada !== null && $declarada !== null
            && $declarada->peso() < $derivada->peso()
            && trim((string) $justificacion) === '') {
            throw OperacionDeProveedorNoPermitida::rebajaSinJustificar($derivada);
        }
    }

    /**
     * Vuelve a derivar y, si cambia, recalcula la reevaluación.
     *
     * Se llama cuando cambia algo de lo que depende: un activo que pasa a este
     * proveedor o deja de serlo, o la valoración de uno de ellos. **No valida**:
     * si un activo baja de valoración y la declarada queda por encima, eso está
     * permitido; y si deja de prestar el último activo con la declarada vacía,
     * se congela la derivada anterior como declarada en vez de dejar la fila
     * sin criticidad, que el `CHECK` rechazaría.
     */
    public function recalcular(Proveedor $proveedor): void
    {
        $derivada = $this->derivadaDe($proveedor);

        if ($derivada === $proveedor->criticidad_derivada) {
            return;
        }

        $cambios = ['criticidad_derivada' => $derivada];

        if ($derivada === null && $proveedor->criticidad_declarada === null) {
            $cambios['criticidad_declarada'] = $proveedor->criticidad_derivada;
        }

        $proveedor->forceFill($cambios)->save();

        $this->reevaluacion->recalcular($proveedor);
    }

    /** Tras guardar un activo: su proveedor de antes y el de ahora. */
    public function trasCambioDeActivo(Activo $activo): void
    {
        $ids = array_unique(array_filter([
            $activo->getOriginal('proveedor_id'),
            $activo->proveedor_id,
        ]));

        foreach (Proveedor::query()->whereKey($ids)->get() as $proveedor) {
            $this->recalcular($proveedor);
        }
    }
}
