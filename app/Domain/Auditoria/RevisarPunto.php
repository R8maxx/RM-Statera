<?php

declare(strict_types=1);

namespace App\Domain\Auditoria;

use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Excepciones\AuditoriaCerrada;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\AuditoriaPunto;

/**
 * Marcar líneas de la checklist, una o muchas.
 *
 * **La guarda está aquí y no en el trigger**, aunque el trigger también lo
 * impida. Un `update` sobre una auditoría cerrada levanta el `RAISE EXCEPTION` de
 * PostgreSQL y sube como `QueryException` sin capturar: el usuario ve el 500
 * genérico y el mensaje de la base —que además va sin tildes, porque es SQL— no
 * lo lee nadie. Comprobándolo antes, el controlador puede devolver un error que
 * se entiende. Es lo que ya hace `PrecargarChecklist`.
 *
 * **El marcado en bloque sólo pone `conforme`.** «No conforme» y «observación»
 * piden un hallazgo detrás que las explique —`ResultadoPunto::exigeHallazgo()`— y
 * marcar cuarenta de golpe fabricaría cuarenta huecos en `scopeSinHallazgo()`. Es
 * el mismo argumento que dejó `descartada` fuera de la acción masiva de tareas:
 * un motivo escrito una vez para cincuenta filas no es un motivo.
 *
 * **Va por `update` masivo y no por un bucle tolerante.** El bucle de
 * implantaciones existe para rechazar filas una a una según su máquina de
 * estados; un punto de checklist no tiene, así que el recuento de rechazadas
 * sería siempre cero: un mensaje que miente sobre su propio esfuerzo. Aquí todas
 * las filas comparten una auditoría, así que o entran todas o no entra ninguna.
 */
final readonly class RevisarPunto
{
    /**
     * @throws AuditoriaCerrada
     */
    public function marcar(AuditoriaPunto $punto, ResultadoPunto $resultado, ?string $nota = null): AuditoriaPunto
    {
        $auditoria = $punto->auditoria;

        if ($auditoria !== null && ! $auditoria->admiteCambios()) {
            throw AuditoriaCerrada::paraPunto($auditoria);
        }

        $punto->update([
            'resultado' => $resultado->value,
            'nota' => $nota,
        ]);

        return $punto->refresh();
    }

    /**
     * Marca conformes las líneas indicadas de esta auditoría.
     *
     * El `where` de la auditoría **no es redundante** con los ids: el scope
     * global tapa el cruce entre organizaciones, y entre dos auditorías de la
     * misma organización no hay nada que lo tape. Sin él, una lista de ids de
     * otra auditoría se aplicaría sin más.
     *
     * @param  list<int>  $ids
     * @return int cuántas líneas se han marcado
     *
     * @throws AuditoriaCerrada
     */
    public function marcarConformes(Auditoria $auditoria, array $ids): int
    {
        if (! $auditoria->admiteCambios()) {
            throw AuditoriaCerrada::paraPunto($auditoria);
        }

        if ($ids === []) {
            return 0;
        }

        return AuditoriaPunto::query()
            ->where('auditoria_id', $auditoria->id)
            ->whereIn('id', $ids)
            ->update(['resultado' => ResultadoPunto::Conforme->value]);
    }
}
