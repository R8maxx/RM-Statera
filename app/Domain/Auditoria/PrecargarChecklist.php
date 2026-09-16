<?php

declare(strict_types=1);

namespace App\Domain\Auditoria;

use App\Domain\Auditoria\Excepciones\AuditoriaCerrada;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\AuditoriaPunto;
use App\Domain\Implantacion\Models\Implantacion;
use Illuminate\Support\Facades\DB;

/**
 * La checklist, generada desde el catálogo.
 *
 * Es la mitad literal del § 4.12 —«checklists generadas desde el catálogo»— y la
 * razón de que exista `auditoria_puntos`: sin ella, la ausencia de hallazgo se
 * lee como conformidad, y una auditoría por muestreo pasaría a afirmar cosas
 * sobre las medidas que nadie miró.
 *
 * **Una fila por medida exigible del sistema**, por el scope `aplicables()` que ya
 * existe y no por una condición escrita otra vez: es el mismo conjunto del que
 * salen las cifras del panel y las filas del plan de adecuación, y con la regla
 * repetida el día que cambie una la auditoría revisaría un universo distinto del
 * que el producto dice exigir.
 *
 * **Idempotente.** Volver a precargar no borra lo ya revisado ni duplica nada:
 * añade lo que falte. Hace falta que lo sea porque una revaloración del sistema
 * puede hacer exigible una medida a mitad de auditoría, y porque el botón se
 * pulsa dos veces.
 *
 * **Y lo que sobra no se borra.** Si una medida deja de exigirse después de que
 * el auditor la haya mirado, su línea se queda: borrarla eliminaría un hecho
 * —alguien la revisó y dijo algo— para que cuadre una lista. Lo que se hace con
 * eso es congelarlo al cerrar.
 */
final readonly class PrecargarChecklist
{
    /**
     * Devuelve cuántas líneas se han añadido.
     *
     * @throws AuditoriaCerrada
     */
    public function __invoke(Auditoria $auditoria): int
    {
        if (! $auditoria->admiteCambios()) {
            throw AuditoriaCerrada::paraChecklist($auditoria);
        }

        $exigibles = Implantacion::query()
            ->delSistema($auditoria->sistema_id)
            ->aplicables()
            ->pluck('implantaciones.id');

        $yaPuestas = AuditoriaPunto::query()
            ->where('auditoria_id', $auditoria->id)
            ->pluck('implantacion_id');

        $faltan = $exigibles->diff($yaPuestas);

        if ($faltan->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($auditoria, $faltan): int {
            foreach ($faltan as $implantacionId) {
                /*
                 * Una a una y por el modelo, no un `insert` en bloque: el evento
                 * `creating` de `PerteneceAOrganizacion` es quien pone
                 * `organizacion_id`, y sin él RLS rechaza la fila con un error de
                 * privilegios que no menciona la palabra «organización».
                 */
                AuditoriaPunto::query()->create([
                    'auditoria_id' => $auditoria->id,
                    'implantacion_id' => $implantacionId,
                    'resultado' => Auditoria::resultadoInicial()->value,
                ]);
            }

            return $faltan->count();
        });
    }
}
