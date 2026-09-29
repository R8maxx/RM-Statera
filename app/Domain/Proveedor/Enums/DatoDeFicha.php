<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Enums;

use App\Domain\Proveedor\Models\Proveedor;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un dato de la ficha del proveedor que una cláusula del catálogo contrasta.
 *
 * Qué cláusula mira qué dato lo dice `catalogo/clausulas-proveedor.yaml`
 * (`dato_de_ficha`). Aquí está sólo la regla de cuándo la ficha y la respuesta
 * se contradicen, porque depende de columnas de `proveedores`.
 *
 * **Una contradicción no dice cuál de las dos está mal.** Puede que la ficha se
 * quedara sin actualizar o que la cláusula se contestara sin mirar. Por eso se
 * enseña y no se corrige nada solo.
 */
#[TypeScript]
enum DatoDeFicha: string
{
    case UbicacionDatos = 'ubicacion_datos';
    case EncargadoTratamiento = 'encargado_tratamiento';

    public function etiqueta(): string
    {
        return match ($this) {
            self::UbicacionDatos => 'Dónde están los datos',
            self::EncargadoTratamiento => 'Trata datos personales por cuenta de la organización',
        };
    }

    /**
     * Lo que dice la ficha hoy, como se lee en ella.
     */
    public function valorEn(Proveedor $proveedor): string
    {
        return match ($this) {
            self::UbicacionDatos => $proveedor->ubicacion_datos->etiqueta(),
            self::EncargadoTratamiento => $proveedor->es_subencargado_rgpd ? 'Sí' : 'No',
        };
    }

    /**
     * Por qué la ficha y la respuesta no casan, y qué hacer; o `null` si casan.
     *
     * - Ubicación: dar por cumplida la cláusula con la ficha en «sin
     *   determinar» es afirmar que el contrato dice algo que nadie ha apuntado.
     *   Un «no cumple» con la ubicación desconocida es coherente.
     * - Encargo: si no trata datos personales por cuenta de la organización, la
     *   cláusula no le aplica, y contestarla es exigírsela; si los trata, «no
     *   aplica» se salta el art. 28 del RGPD.
     *
     * El texto usa las palabras del formulario del proveedor —«Dónde están los
     * datos», «Trata datos personales por cuenta de la organización»— y no las
     * de la columna, para que quien lo lea encuentre el campo que tiene que
     * tocar. **Las dos salidas se dicen siempre**, porque cuál de las dos está
     * mal lo sabe quien leyó el contrato, no la aplicación.
     *
     * @return array{motivo: string, sugerencia: string}|null
     */
    public function contradiccion(Proveedor $proveedor, ResultadoClausula $resultado): ?array
    {
        return match ($this) {
            self::UbicacionDatos => $resultado === ResultadoClausula::Cumple
                && $proveedor->ubicacion_datos === UbicacionDatos::Desconocida
                    ? [
                        'motivo' => 'Se dio por cumplida —el contrato dice dónde se tratan los datos—, pero en la ficha «Dónde están los datos» sigue en «Sin determinar».',
                        'sugerencia' => 'Si el contrato lo dice, apúntalo en la ficha. Si no lo dice, la cláusula no se cumplía y hay que evaluar otra vez.',
                    ]
                    : null,
            self::EncargadoTratamiento => match (true) {
                ! $proveedor->es_subencargado_rgpd && $resultado !== ResultadoClausula::NoAplica => [
                    'motivo' => "Se contestó «{$resultado->etiqueta()}», pero en la ficha no está marcado «Trata datos personales por cuenta de la organización»: sin eso, la cláusula no le aplica.",
                    'sugerencia' => 'Si trata datos personales —alojar una base de datos que los contiene ya cuenta—, márcalo en la ficha. Si no los trata, la respuesta era «No aplica» y hay que evaluar otra vez.',
                ],
                $proveedor->es_subencargado_rgpd && $resultado === ResultadoClausula::NoAplica => [
                    'motivo' => 'Se marcó «No aplica», pero en la ficha consta que trata datos personales por cuenta de la organización: el art. 28 del RGPD le aplica.',
                    'sugerencia' => 'Si los trata, hay que evaluar otra vez y comprobar el encargo. Si no los trata, corrige la ficha.',
                ],
                default => null,
            },
        };
    }
}
