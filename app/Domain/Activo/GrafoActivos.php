<?php

declare(strict_types=1);

namespace App\Domain\Activo;

use App\Domain\Activo\Models\Activo;
use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El grafo de dependencias entre activos, recorrido con CTE recursivas.
 *
 * Es una de las razones por las que el proyecto va sobre PostgreSQL: en MySQL
 * habría que resolverlo en PHP a base de consultas por nivel, y el grafo de un
 * inventario real tiene cadenas de cinco o seis saltos.
 *
 * **Sobre el aislamiento:** estas consultas son SQL crudo y no pasan por el
 * global scope de Eloquent, así que llevan `organizacion_id` como parámetro
 * explícito en las dos ramas de cada CTE. RLS las respalda por debajo; el
 * parámetro está para que la consulta sea correcta por sí misma y no dependa de
 * que la sesión tenga la variable puesta.
 *
 * **Sobre los ciclos:** el recorrido arrastra la ruta en un array y se niega a
 * volver a entrar en un nodo ya visitado. `RegistrarDependencia` impide crear el
 * ciclo, pero una CTE recursiva contra un grafo con un ciclo no devuelve un
 * resultado raro: no termina nunca.
 */
final class GrafoActivos
{
    public function __construct(private readonly ContextoOrganizacion $contexto) {}

    /**
     * Todo lo que este activo necesita para funcionar, a cualquier profundidad.
     *
     * Cada modelo devuelto lleva `profundidad` (1 en las dependencias directas)
     * y la `nota` del vínculo por el que se llegó a él.
     *
     * @return Collection<int, Activo>
     */
    public function dependenciasDe(Activo|int $activo): Collection
    {
        return $this->recorrer($activo, desde: 'activo_id', hacia: 'depende_de_id');
    }

    /**
     * Todo lo que se cae si este activo cae, a cualquier profundidad.
     *
     * Es la dirección por la que sube la valoración y la que se mira antes de
     * tocar nada en producción.
     *
     * @return Collection<int, Activo>
     */
    public function dependientesDe(Activo|int $activo): Collection
    {
        return $this->recorrer($activo, desde: 'depende_de_id', hacia: 'activo_id');
    }

    /**
     * Si `$destino` es alcanzable desde `$origen` siguiendo las dependencias.
     *
     * Lo usa la comprobación de ciclos: añadir «origen depende de destino»
     * cierra un ciclo justamente cuando destino ya llega a origen.
     */
    public function alcanza(Activo|int $origen, Activo|int $destino): bool
    {
        $destinoId = $destino instanceof Activo ? $destino->id : $destino;

        return $this->dependenciasDe($origen)->contains(
            static fn (Activo $alcanzado): bool => $alcanzado->id === $destinoId,
        );
    }

    /**
     * Las dos direcciones son la misma consulta con las columnas del vínculo
     * intercambiadas, así que se escribe una vez.
     *
     * @param  'activo_id'|'depende_de_id'  $desde
     * @param  'activo_id'|'depende_de_id'  $hacia
     * @return Collection<int, Activo>
     */
    private function recorrer(Activo|int $activo, string $desde, string $hacia): Collection
    {
        $raizId = $activo instanceof Activo ? $activo->id : $activo;
        $organizacionId = $this->contexto->idObligatorio();

        // La raíz no entra en el resultado: se pregunta de qué depende un activo,
        // no de qué depende contándose a sí mismo. Por eso la rama base parte de
        // sus vínculos directos y no de él.
        //
        // Por la recursión sólo viajan identificadores; las filas de `activos` se
        // traen al final, de una vez. Arrastrar la tabla entera por cada nivel es
        // lo que convierte una CTE legible en una consulta cara.
        $filas = DB::select(<<<SQL
            WITH RECURSIVE recorrido AS (
                SELECT
                    d.{$hacia} AS id,
                    1 AS profundidad,
                    d.nota AS vinculo_nota,
                    ARRAY[?::bigint, d.{$hacia}] AS ruta
                FROM activo_dependencias d
                WHERE d.{$desde} = ?
                  AND d.organizacion_id = ?

                UNION ALL

                SELECT
                    d.{$hacia},
                    actual.profundidad + 1,
                    d.nota,
                    actual.ruta || d.{$hacia}
                FROM recorrido actual
                INNER JOIN activo_dependencias d ON d.{$desde} = actual.id
                WHERE d.organizacion_id = ?
                  AND NOT (d.{$hacia} = ANY(actual.ruta))
            )
            SELECT a.*, alcanzado.profundidad, alcanzado.vinculo_nota
            FROM (
                SELECT DISTINCT ON (id) id, profundidad, vinculo_nota
                FROM recorrido
                ORDER BY id, profundidad
            ) alcanzado
            INNER JOIN activos a ON a.id = alcanzado.id AND a.organizacion_id = ?
            ORDER BY alcanzado.profundidad, a.codigo
        SQL, [$raizId, $raizId, $organizacionId, $organizacionId, $organizacionId]);

        // `DISTINCT ON` deja una sola fila por activo cuando se llega a él por
        // dos caminos, y se queda con la profundidad menor: la distancia a la que
        // de verdad está.
        return Activo::hydrate($filas);
    }
}
