<?php

declare(strict_types=1);

namespace App\Domain\Obligacion;

use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\Models\Obligacion;
use Illuminate\Support\Carbon;

/**
 * Convierte una obligación del catálogo en un compromiso de esta organización.
 *
 * **Copia el título y la periodicidad, no los lee por join.** Es el mismo criterio
 * que `mediciones.objetivo` y que la instantánea de una versión de documento: lo
 * que la organización asumió en 2026 no puede repintarse porque el catálogo cambie
 * la redacción en 2028. `obligacion_id` se queda para saber de qué salió, y es lo
 * que permite avisar de a cuántos compromisos afecta retirarla.
 *
 * La periodicidad del catálogo es **la sugerida**: quien asume puede ser más
 * estricto, y por eso entra como argumento y no como copia forzosa.
 *
 * `computa_desde` lo pone quien asume, y no se inventa aquí: «la última auditoría
 * interna fue el 14 de marzo» es un hecho que la organización sabe y la
 * herramienta no. Sin él se cae a hoy, que es lo mismo que decir «el reloj empieza
 * ahora».
 */
final class AsumirObligacion
{
    public function __construct(private readonly RegistrarCompromiso $registrar) {}

    public function __invoke(
        Obligacion $obligacion,
        ?Carbon $computaDesde = null,
        ?int $sistemaId = null,
        ?int $responsableId = null,
        ?int $periodicidadMeses = null,
    ): Compromiso {
        return ($this->registrar)([
            'obligacion_id' => $obligacion->id,
            'sistema_id' => $sistemaId,
            'titulo' => $obligacion->nombre,
            'descripcion' => $obligacion->descripcion,
            'periodicidad_meses' => $periodicidadMeses ?? $obligacion->periodicidad_meses_sugerida,
            'computa_desde' => $computaDesde ?? Carbon::today(),
            'responsable_id' => $responsableId,
        ]);
    }
}
