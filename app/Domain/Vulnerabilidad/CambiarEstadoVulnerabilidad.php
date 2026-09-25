<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Vulnerabilidad\Enums\EstadoVulnerabilidad;
use App\Domain\Vulnerabilidad\Excepciones\OperacionDeVulnerabilidadNoPermitida;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use App\Domain\Vulnerabilidad\Models\VulnerabilidadTransicion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El único sitio donde cambia el estado de una vulnerabilidad, y el que deja el
 * histórico (invariante 7).
 *
 * Tres reglas, y las tres se imponen aquí y en el `CHECK`:
 *
 * - **Aceptar** necesita `vulnerabilidades.aceptar` y motivo, y guarda quién y
 *   cuándo. No corregir a sabiendas es asumir un riesgo.
 * - **Cerrar** necesita la verificación escrita, y guarda quién la hizo.
 * - **Volver atrás** —reabrir, o devolver a remediación una mitigada que no
 *   pasó la verificación— necesita motivo.
 *
 * Salir de aceptada o de cerrada borra la firma que ya no vale: una aceptación
 * reabierta no sigue aceptada, y un cierre reabierto no sigue verificado.
 */
final class CambiarEstadoVulnerabilidad
{
    public function __invoke(Vulnerabilidad $vulnerabilidad, EstadoVulnerabilidad $nuevo, User $quien, ?string $nota = null): Vulnerabilidad
    {
        $actual = $vulnerabilidad->estado;
        $nota = $nota !== null && trim($nota) !== '' ? trim($nota) : null;

        if (! $actual->admite($nuevo)) {
            throw OperacionDeVulnerabilidadNoPermitida::transicion($actual, $nuevo);
        }

        if ($nuevo === EstadoVulnerabilidad::Aceptada && ! $quien->can(Permiso::VulnerabilidadesAceptar->value)) {
            throw OperacionDeVulnerabilidadNoPermitida::sinPermisoParaAceptar();
        }

        if ($nuevo === EstadoVulnerabilidad::Cerrada && $nota === null) {
            throw OperacionDeVulnerabilidadNoPermitida::sinVerificacion();
        }

        if ($nuevo->exigeMotivoDesde($actual) && $nota === null) {
            throw OperacionDeVulnerabilidadNoPermitida::sinMotivo($nuevo);
        }

        return DB::transaction(function () use ($vulnerabilidad, $actual, $nuevo, $quien, $nota): Vulnerabilidad {
            $cambios = ['estado' => $nuevo];

            if ($nuevo === EstadoVulnerabilidad::Aceptada) {
                $cambios += ['motivo_aceptacion' => $nota, 'aceptada_por_id' => $quien->id, 'aceptada_en' => Carbon::now()];
            } elseif ($actual === EstadoVulnerabilidad::Aceptada) {
                $cambios += ['motivo_aceptacion' => null, 'aceptada_por_id' => null, 'aceptada_en' => null];
            }

            if ($nuevo === EstadoVulnerabilidad::Cerrada) {
                $cambios += ['verificacion' => $nota, 'verificada_por_id' => $quien->id, 'cerrada_en' => Carbon::now()];
            } elseif ($actual === EstadoVulnerabilidad::Cerrada) {
                $cambios += ['verificacion' => null, 'verificada_por_id' => null, 'cerrada_en' => null];
            }

            $vulnerabilidad->forceFill($cambios)->save();

            VulnerabilidadTransicion::query()->create([
                'vulnerabilidad_id' => $vulnerabilidad->id,
                'estado_anterior' => $actual,
                'estado_nuevo' => $nuevo,
                'usuario_id' => $quien->id,
                'nota' => $nota,
            ]);

            return $vulnerabilidad->refresh();
        });
    }
}
