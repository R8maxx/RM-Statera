<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Riesgo\Models\Riesgo;
use App\Models\User;

/**
 * Ata un riesgo a la cuestión del contexto de la que salió.
 *
 * Es el vínculo que hace que el DAFO no sea un papel suelto. «Dependemos de un
 * solo proveedor de nube» deja de ser una frase en un acta de enero y pasa a ser
 * el origen declarado de R-014, que es lo que ISO 6.1.1 pide cuando dice que la
 * apreciación de riesgos se hace **considerando las cuestiones del 4.1**.
 *
 * **N:M**, porque una amenaza puede abrir varios riesgos y un riesgo puede venir
 * de dos cuestiones a la vez —«plantilla pequeña» y «normativa que se endurece»
 * explican el mismo riesgo de incumplimiento—. Con clave singular habría que
 * elegir una y las demás quedarían invisibles, que es el argumento que ya hizo
 * N:M a riesgo↔activo.
 *
 * **Se vincula, no se crea.** Este módulo no abre riesgos: sugerir que una amenaza
 * se convierta automáticamente en un riesgo produciría riesgos sin probabilidad,
 * sin impacto y sin propietario, que es exactamente lo que ISO 6.1.3 f) no admite.
 * Quien registra el riesgo decide que lo es.
 *
 * **Y se puede vincular un riesgo a una cuestión retirada.** Puede parecer un
 * descuido y no lo es: una amenaza que dejó de estar sobre la mesa explica igual
 * de bien por qué se abrió en su día el riesgo que sigue vivo, y prohibirlo
 * obligaría a borrar el origen justo cuando el contexto cambia.
 */
final class VincularRiesgoACuestion
{
    public function vincular(CuestionContexto $cuestion, Riesgo $riesgo, ?User $usuario = null): void
    {
        // `syncWithoutDetaching` y no `attach`: pulsar dos veces no puede reventar
        // con un error de índice único que hable de una restricción de la base.
        $cuestion->riesgos()->syncWithoutDetaching([
            $riesgo->id => [
                'organizacion_id' => $cuestion->organizacion_id,
                'vinculada_por_id' => $usuario?->id,
            ],
        ]);
    }

    public function desvincular(CuestionContexto $cuestion, Riesgo $riesgo): void
    {
        $cuestion->riesgos()->detach($riesgo->id);
    }
}
