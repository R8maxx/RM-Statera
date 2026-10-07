<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Models\EventoPlataforma;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Escribe la traza de la plataforma. Todo lo que hace un administrador pasa
 * por aquí, igual que todo lo de un tenant pasa por `RegistroTraza`.
 */
final class TrazaPlataforma
{
    /**
     * @param  array<string, mixed>  $detalle
     */
    public function registrar(
        AccionPlataforma $accion,
        ?Organizacion $organizacion = null,
        array $detalle = [],
        ?int $usuarioId = null,
    ): void {
        EventoPlataforma::query()->create([
            // Explícito cuando escribe un comando o un login todavía sin sesión;
            // si no, quien hace la petición.
            'usuario_id' => $usuarioId ?? Auth::id(),
            'organizacion_afectada_id' => $organizacion?->id,
            'accion' => $accion->value,
            'detalle' => $detalle === [] ? null : $detalle,
            'ip' => Request::ip(),
            'created_at' => Carbon::now(),
        ]);
    }
}
