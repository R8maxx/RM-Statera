<?php

declare(strict_types=1);

namespace App\Domain\Cambio;

use App\Domain\Cambio\Models\CambioSgsi;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Une una tarea con el cambio del SGSI que lleva a cabo.
 *
 * **Sin doble vínculo**, como en mejoras y en objetivos: un cambio del sistema de
 * gestión no cuelga de ninguna medida, y atar la tarea a una arbitraria la
 * metería en el plan de adecuación, que presupuesta brechas del Anexo II.
 *
 * Idempotente, como todas las de su familia.
 */
final class VincularActuacionDeCambio
{
    public function vincular(CambioSgsi $cambio, Tarea $tarea, ?User $usuario = null): void
    {
        $cambio->tareas()->syncWithoutDetaching([
            $tarea->id => [
                'organizacion_id' => $cambio->organizacion_id,
                'vinculada_por_id' => $usuario?->id,
                'created_at' => Carbon::now(),
            ],
        ]);
    }

    /**
     * Suelta la actuación del cambio, y **no borra la tarea**: es trabajo real
     * con su histórico.
     */
    public function desvincular(CambioSgsi $cambio, Tarea $tarea): void
    {
        $cambio->tareas()->detach($tarea->id);
    }
}
