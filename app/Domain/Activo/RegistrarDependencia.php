<?php

declare(strict_types=1);

namespace App\Domain\Activo;

use App\Domain\Activo\Excepciones\DependenciaCiclicaException;
use App\Domain\Activo\Models\Activo;

/**
 * Declara y retira dependencias entre activos.
 *
 * Su único trabajo de verdad es **impedir el ciclo antes de escribirlo**. El
 * `CHECK` de la tabla sólo cubre el bucle de un salto (un activo que depende de
 * sí mismo); un ciclo de tres se cuela igual, y contra un grafo con un ciclo una
 * CTE recursiva sin protección no devuelve un resultado raro: no termina.
 *
 * Se comprueba aquí y no en el `FormRequest` porque es una regla del dominio y
 * no de la petición: la misma prohibición vale para un importador de CSV o para
 * el seeder, que nunca pasan por un formulario.
 */
final class RegistrarDependencia
{
    public function __construct(private readonly GrafoActivos $grafo) {}

    /**
     * `$activo` pasa a depender de `$dependeDe`.
     *
     * Idempotente: repetir el vínculo actualiza la nota en vez de fallar por la
     * clave única. Declarar dos veces lo mismo no es un error de quien lo hace.
     *
     * @throws DependenciaCiclicaException
     */
    public function vincular(Activo $activo, Activo $dependeDe, ?string $nota = null): void
    {
        if ($activo->is($dependeDe)) {
            throw DependenciaCiclicaException::entre($activo, $dependeDe, 0);
        }

        // El vínculo nuevo cierra un ciclo justamente cuando el sostén ya
        // depende, por el camino que sea, del que quiere apoyarse en él.
        $vuelta = $this->grafo->dependenciasDe($dependeDe)
            ->first(static fn (Activo $alcanzado): bool => $alcanzado->id === $activo->id);

        if ($vuelta !== null) {
            throw DependenciaCiclicaException::entre(
                $activo,
                $dependeDe,
                (int) $vuelta->getAttribute('profundidad'),
            );
        }

        $activo->dependeDe()->syncWithoutDetaching([
            $dependeDe->id => [
                'organizacion_id' => $activo->organizacion_id,
                'nota' => $nota,
            ],
        ]);
    }

    public function desvincular(Activo $activo, Activo $dependeDe): void
    {
        $activo->dependeDe()->detach($dependeDe->id);
    }
}
