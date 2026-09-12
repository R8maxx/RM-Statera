<?php

declare(strict_types=1);

namespace App\Domain\Tarea;

use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Une una tarea con un requisito, en cualquier marco.
 *
 * Mismo gesto que `VincularEvidencia`, y por el mismo motivo: «revisar la
 * política de contraseñas» hace avanzar un control de ISO y tres medidas del ENS
 * a la vez, y anotarla una vez por marco sería volver a las hojas duplicadas.
 *
 * Sin nota en el vínculo, a diferencia de las evidencias: una evidencia prueba
 * cuatro medidas por matices distintos y hay algo que explicar; una tarea que
 * hace avanzar cuatro medidas las hace avanzar haciéndose.
 */
final class VincularTarea
{
    public function vincular(Tarea $tarea, Implantacion $implantacion, ?User $usuario = null): void
    {
        // Idempotente: volver a vincular lo mismo no es un error del usuario.
        $tarea->implantaciones()->syncWithoutDetaching([
            $implantacion->id => [
                'organizacion_id' => $tarea->organizacion_id,
                'vinculada_por_id' => $usuario?->id,
                'created_at' => Carbon::now(),
            ],
        ]);
    }

    public function desvincular(Tarea $tarea, Implantacion $implantacion): void
    {
        $tarea->implantaciones()->detach($implantacion->id);
    }
}
