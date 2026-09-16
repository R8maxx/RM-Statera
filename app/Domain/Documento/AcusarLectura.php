<?php

declare(strict_types=1);

namespace App\Domain\Documento;

use App\Domain\Documento\Excepciones\AprobacionNoPermitida;
use App\Domain\Documento\Models\DocumentoLectura;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * «He leído esta versión.»
 *
 * **Sólo sobre una versión aprobada.** Acusar recibo de un borrador no significa
 * nada: el borrador se regenera, así que la fila diría que alguien leyó un
 * documento que ya no existe. Lo que la cláusula 7.3 pide es que la gente conozca
 * lo que está vigente.
 *
 * **Idempotente.** Pulsar dos veces no acusa dos veces, y la segunda no
 * reescribe la fecha: cuándo se leyó es cuándo se leyó. El índice único de
 * `(documento_version_id, user_id)` lo sostiene aunque alguien llegue por dos
 * pestañas a la vez.
 */
final class AcusarLectura
{
    /**
     * @throws AprobacionNoPermitida
     */
    public function __invoke(DocumentoVersion $version, User $lector): DocumentoLectura
    {
        if (! $version->estaAprobada()) {
            throw AprobacionNoPermitida::noAprobada($version);
        }

        $existente = $version->lecturas()->where('user_id', $lector->id)->first();

        if ($existente instanceof DocumentoLectura) {
            return $existente;
        }

        $ahora = Carbon::now();

        return $version->lecturas()->create([
            'user_id' => $lector->id,
            'acusada_en' => $ahora,
            'created_at' => $ahora,
        ]);
    }
}
