<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Persona\Models\Puesto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El árbol de puestos, con su profundidad.
 *
 * Una CTE recursiva sobre `puestos.reporta_a_id`, hermana de la de
 * `Requisito::subarbol()` —que baja por `parent_id`— y de `GrafoActivos`, de la
 * que toma las dos precauciones que aquélla no necesita:
 *
 * 1. **`organizacion_id` explícito en las DOS ramas.** Este SQL es crudo, así que
 *    no pasa por el global scope de Eloquent; RLS lo respalda por debajo, pero
 *    una CTE que cruzara la frontera en la rama recursiva sería un fallo de
 *    aislamiento que ningún `where` de PHP taparía. (`Requisito` no lo lleva
 *    porque el catálogo es global — invariante 2.)
 * 2. **El guardián de ciclos**, `NOT (... = ANY(ruta))`. `AsignarSuperior` impide
 *    crearlos, pero contra un grafo que ya tuviera uno esta consulta **no
 *    devolvería un resultado raro: no terminaría**. La red se paga una vez.
 */
final readonly class Organigrama
{
    public function __construct(private ContextoOrganizacion $contexto) {}

    /**
     * El árbol entero, de las raíces hacia abajo y en orden de lectura.
     *
     * Cada puesto llega con `profundidad` (0 en la raíz) como atributo extra, que
     * es lo que la pantalla usa para sangrar. Los que no reportan a nadie son las
     * raíces; **un puesto huérfano por un borrado también sale**, porque su
     * `reporta_a_id` se quedó nulo — verlo en la raíz es lo que permite arreglarlo.
     *
     * @return Collection<int, Puesto>
     */
    public function arbol(): Collection
    {
        return $this->recorrer(null);
    }

    /**
     * La rama que cuelga de un puesto, él incluido.
     *
     * @return Collection<int, Puesto>
     */
    public function ramaDe(Puesto|int $puesto): Collection
    {
        return $this->recorrer($puesto instanceof Puesto ? $puesto->id : $puesto);
    }

    /**
     * Si `$posibleSuperior` cuelga de `$puesto`, directa o indirectamente.
     *
     * Es la comprobación de ciclos: si el futuro jefe ya está por debajo del
     * puesto, el vínculo cerraría el bucle.
     */
    public function cuelgaDe(Puesto|int $puesto, Puesto|int $posibleDescendiente): bool
    {
        $buscado = $posibleDescendiente instanceof Puesto ? $posibleDescendiente->id : $posibleDescendiente;

        return $this->ramaDe($puesto)
            ->contains(static fn (Puesto $alcanzado): bool => $alcanzado->id === $buscado);
    }

    /**
     * @return Collection<int, Puesto>
     */
    private function recorrer(?int $raizId): Collection
    {
        $organizacionId = $this->contexto->idObligatorio();

        // La rama base son las raíces —o el puesto pedido—, a profundidad 0. Por
        // la recursión sólo viajan identificadores; las filas de `puestos` se
        // traen al final y de una vez, que es lo que separa una CTE legible de
        // una consulta cara.
        $condicionRaiz = $raizId === null ? 'p.reporta_a_id IS NULL' : 'p.id = ?';
        $bindings = $raizId === null
            ? [$organizacionId, $organizacionId, $organizacionId]
            : [$raizId, $organizacionId, $organizacionId, $organizacionId];

        /*
         * `orden` va casteado a `text` en las DOS ramas, y no es cosmética: la
         * base devuelve `varchar(255)` y la recursiva una concatenación sin
         * límite, y PostgreSQL exige que los tipos casen —«recursive query
         * column N has type character varying(255) in non-recursive term but
         * type character varying overall»—. Lo cazó un test, no una lectura.
         */
        $filas = DB::select(<<<SQL
            WITH RECURSIVE arbol AS (
                SELECT
                    p.id,
                    0 AS profundidad,
                    ARRAY[p.id] AS ruta,
                    p.titulo::text AS orden
                FROM puestos p
                WHERE {$condicionRaiz}
                  AND p.organizacion_id = ?

                UNION ALL

                SELECT
                    hijo.id,
                    padre.profundidad + 1,
                    padre.ruta || hijo.id,
                    (padre.orden || ' / ' || hijo.titulo)::text
                FROM arbol padre
                INNER JOIN puestos hijo ON hijo.reporta_a_id = padre.id
                WHERE hijo.organizacion_id = ?
                  AND NOT (hijo.id = ANY(padre.ruta))
            )
            SELECT p.*, arbol.profundidad
            FROM arbol
            INNER JOIN puestos p ON p.id = arbol.id AND p.organizacion_id = ?
            ORDER BY arbol.orden
        SQL, $bindings);

        return Puesto::hydrate($filas);
    }
}
