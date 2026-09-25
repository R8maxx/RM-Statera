<?php

declare(strict_types=1);

namespace App\Domain\Proveedor;

use App\Domain\Proveedor\Enums\EstadoProveedor;
use App\Domain\Proveedor\Excepciones\OperacionDeProveedorNoPermitida;
use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Proveedor\Models\ProveedorTransicion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * El único sitio donde cambia el estado de un proveedor, y el que deja el
 * histórico (invariante 7).
 *
 * `RegistrarEvaluacion` pasa por aquí con el estado que da el resultado. A mano
 * sólo se retira y se reactiva: reactivar devuelve a «en evaluación», porque lo
 * evaluado antes de retirarlo ya no dice nada del contrato de hoy.
 */
final class CambiarEstadoProveedor
{
    public function __construct(private readonly RecalcularReevaluacion $reevaluacion) {}

    public function aplicar(Proveedor $proveedor, EstadoProveedor $nuevo, ?User $quien, ?string $nota = null): void
    {
        $anterior = $proveedor->estado;

        // Una reevaluación que confirma el estado no es un cambio de estado: el
        // histórico no gana nada con «homologado → homologado», y la evaluación
        // ya queda registrada por su cuenta. Sí cambia la próxima fecha.
        if ($anterior === $nuevo) {
            $this->reevaluacion->recalcular($proveedor);

            return;
        }

        DB::transaction(function () use ($proveedor, $nuevo, $quien, $nota, $anterior): void {
            $proveedor->forceFill(['estado' => $nuevo])->save();

            ProveedorTransicion::query()->create([
                'proveedor_id' => $proveedor->id,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $nuevo,
                'usuario_id' => $quien?->id,
                'nota' => $nota,
            ]);

            $this->reevaluacion->recalcular($proveedor);
        });
    }

    public function retirar(Proveedor $proveedor, User $quien, string $motivo): void
    {
        if ($proveedor->estado === EstadoProveedor::Retirado) {
            throw OperacionDeProveedorNoPermitida::yaEnEseEstado();
        }

        if (trim($motivo) === '') {
            throw OperacionDeProveedorNoPermitida::sinMotivo();
        }

        $this->aplicar($proveedor, EstadoProveedor::Retirado, $quien, $motivo);
    }

    public function reactivar(Proveedor $proveedor, User $quien, ?string $nota = null): void
    {
        if ($proveedor->estado !== EstadoProveedor::Retirado) {
            throw OperacionDeProveedorNoPermitida::yaEnEseEstado();
        }

        $this->aplicar($proveedor, EstadoProveedor::EnEvaluacion, $quien, $nota);
    }
}
