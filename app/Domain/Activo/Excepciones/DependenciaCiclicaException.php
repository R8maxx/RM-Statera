<?php

declare(strict_types=1);

namespace App\Domain\Activo\Excepciones;

use App\Domain\Activo\Models\Activo;
use RuntimeException;

/**
 * El vínculo que se pedía cerraría un ciclo en el grafo de dependencias.
 *
 * El mensaje nombra los dos activos y por dónde va la vuelta, porque «operación
 * no válida» obliga a quien lo lee a reconstruir a mano un grafo que la
 * herramienta ya tiene delante.
 */
final class DependenciaCiclicaException extends RuntimeException
{
    public static function entre(Activo $activo, Activo $dependeDe, int $saltos): self
    {
        $vuelta = $saltos === 1
            ? 'ya depende directamente de él'
            : "ya depende de él a través de {$saltos} vínculos";

        return new self(
            "«{$dependeDe->codigo} — {$dependeDe->nombre}» {$vuelta}, ".
            "así que «{$activo->codigo} — {$activo->nombre}» no puede depender a su vez de «{$dependeDe->codigo}». ".
            'Un ciclo en el grafo haría que la valoración se propagase sin fin y dejaría el análisis de impacto sin respuesta.'
        );
    }
}
