<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Da de baja una organización cliente, o la reactiva (punto 46).
 *
 * **No borra nada.** Lo que hay dentro —el inventario, las evidencias, los
 * documentos firmados, la traza— es del cliente y prueba lo que hizo; un
 * auditor puede pedirlo años después. La baja es un estado con fecha y motivo:
 *
 * - nadie de la organización entra (`CuentaVigente` y el login);
 * - la plataforma no entra como soporte, y la ventana abierta se cierra;
 * - los procesos diarios —avisos, indicadores, vencimientos— se la saltan.
 *
 * Reactivar lo deshace todo menos la ventana de soporte, que la vuelve a abrir
 * el cliente si la quiere. Borrar los datos de verdad sería otro proceso, y no
 * es éste.
 *
 * Se escribe **dentro de su contexto**: así `Organizacion::booted()` deja en la
 * traza del cliente desde cuándo y por qué estuvo de baja.
 */
final class BajaOrganizacion
{
    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly TrazaPlataforma $traza,
    ) {}

    public function darDeBaja(Organizacion $organizacion, string $motivo): void
    {
        if ($organizacion->estaDeBaja()) {
            return;
        }

        DB::transaction(function () use ($organizacion, $motivo): void {
            $this->contexto->paraOrganizacion($organizacion, fn () => $organizacion->forceFill([
                'activa' => false,
                'baja_en' => Carbon::now(),
                'motivo_baja' => $motivo,
                'soporte_hasta' => null,
                'soporte_abierto_por' => null,
            ])->save());

            $this->traza->registrar(AccionPlataforma::OrganizacionBaja, $organizacion, ['motivo' => $motivo]);
        });
    }

    public function reactivar(Organizacion $organizacion): void
    {
        if (! $organizacion->estaDeBaja()) {
            return;
        }

        DB::transaction(function () use ($organizacion): void {
            $this->contexto->paraOrganizacion($organizacion, fn () => $organizacion->forceFill([
                'activa' => true,
                'baja_en' => null,
                'motivo_baja' => null,
            ])->save());

            $this->traza->registrar(AccionPlataforma::OrganizacionReactivada, $organizacion);
        });
    }
}
