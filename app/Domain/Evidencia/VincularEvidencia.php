<?php

declare(strict_types=1);

namespace App\Domain\Evidencia;

use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Implantacion\Models\Implantacion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Une una evidencia con un requisito, en cualquier marco.
 *
 * Es el gesto que resuelve el problema del producto: la misma captura del panel
 * del IdP se vincula a `A.8.5` de ISO y a `op.acc.5` del ENS, y a partir de ahí
 * los dos marcos cuentan con ella sin que nadie la vuelva a subir ni la
 * mantenga en dos sitios.
 *
 * El vínculo lleva su propia nota porque una evidencia que cubre cuatro medidas
 * las cubre por motivos distintos, y ese matiz no cabe en la descripción de la
 * evidencia.
 */
final class VincularEvidencia
{
    public function vincular(
        Evidencia $evidencia,
        Implantacion $implantacion,
        ?User $usuario = null,
        ?string $nota = null,
    ): void {
        // Idempotente: volver a vincular actualiza la nota en vez de fallar por
        // la clave única. Vincular dos veces lo mismo no es un error del usuario.
        $evidencia->implantaciones()->syncWithoutDetaching([
            $implantacion->id => [
                'organizacion_id' => $evidencia->organizacion_id,
                'nota' => $nota,
                'vinculada_por_id' => $usuario?->id,
                'created_at' => Carbon::now(),
            ],
        ]);
    }

    public function desvincular(Evidencia $evidencia, Implantacion $implantacion): void
    {
        $evidencia->implantaciones()->detach($implantacion->id);
    }
}
