<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Persona\Excepciones\PuestoCiclico;
use App\Domain\Persona\Models\Puesto;

/**
 * De quién depende un puesto, sin cerrar bucles.
 *
 * **La comprobación vive aquí y no en un `CHECK`**, y el motivo es estructural:
 * un ciclo es una condición ENTRE FILAS —«A reporta a B, que reporta a C, que
 * reporta a A»— y un `CHECK` sólo ve una fila. El `CHECK` de la migración tapa el
 * bucle de un salto; el resto es de aquí.
 *
 * Y no está en el `FormRequest` porque la prohibición vale igual para un
 * importador y para el seeder. Es el precedente exacto de `RegistrarDependencia`
 * con el grafo de activos, y de `DesignarRol` con la incompatibilidad del 5.3.
 */
final readonly class AsignarSuperior
{
    public function __construct(private Organigrama $organigrama) {}

    /**
     * @throws PuestoCiclico
     */
    public function __invoke(Puesto $puesto, ?Puesto $superior): Puesto
    {
        if ($superior === null) {
            $puesto->update(['reporta_a_id' => null]);

            return $puesto;
        }

        // El auto-bucle lo rechaza también el `CHECK`, pero llegar hasta la base
        // para esto devolvería un error que habla de una restricción y no de un
        // organigrama.
        if ($puesto->is($superior)) {
            throw PuestoCiclico::entre($puesto, $superior, 0);
        }

        // Si el futuro jefe ya cuelga del puesto, el vínculo cierra el bucle.
        if ($this->organigrama->cuelgaDe($puesto, $superior)) {
            throw PuestoCiclico::entre($puesto, $superior, $this->saltosHasta($puesto, $superior));
        }

        $puesto->update(['reporta_a_id' => $superior->id]);

        return $puesto;
    }

    /** Cuántos niveles hay entre los dos, para que el mensaje diga por dónde. */
    private function saltosHasta(Puesto $puesto, Puesto $superior): int
    {
        $alcanzado = $this->organigrama->ramaDe($puesto)
            ->first(static fn (Puesto $uno): bool => $uno->id === $superior->id);

        return (int) ($alcanzado?->getAttribute('profundidad') ?? 1) - 1;
    }
}
