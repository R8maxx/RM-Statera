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
    /**
     * La vecindad de un activo: lo que necesita, lo que lo sostiene y **las
     * aristas de verdad**.
     *
     * Existe porque `dependenciasDe()` y `dependientesDe()` NO sirven para
     * dibujar. Aquéllas devuelven los nodos alcanzables con su **profundidad
     * mínima** —el `DISTINCT ON (id)` de `recorrer()`—, que es exactamente lo que
     * una lista sangrada necesita y lo que a un diagrama le falta: si dos
     * servicios dependen de la misma base de datos, la lista la enseña una vez y
     * a un salto, y el diagrama tiene que dibujar **los dos vínculos**. Perder
     * uno es perder justo lo que el grafo existe para enseñar.
     *
     * Así que los nodos salen del recorrido —que ya sabe de profundidad, de
     * organización y de ciclos— y **las aristas de una segunda consulta acotada
     * al conjunto de nodos**, que es lo que las devuelve todas: los rombos, los
     * atajos y los vínculos entre dos ramas distintas.
     *
     * `sentido` dice de qué lado del activo cae cada nodo: `abajo` lo que
     * necesita, `arriba` lo que se cae con él, `centro` él mismo. Un activo puede
     * estar en los dos lados —un rombo que vuelve— y en ese caso gana `abajo`,
     * porque es donde su valoración empieza a subir.
     *
     * @return array{
     *     nodos: Collection<int, Activo>,
     *     aristas: list<array{desde: int, hacia: int, nota: ?string}>,
     *     sentidos: array<int, string>,
     * }
     */
    public function vecindadDe(Activo $activo): array
    {
        $organizacionId = $this->contexto->idObligatorio();

        $dependencias = $this->dependenciasDe($activo);
        $dependientes = $this->dependientesDe($activo);

        $sentidos = [$activo->id => 'centro'];

        foreach ($dependientes as $uno) {
            $sentidos[$uno->id] = 'arriba';
        }

        // `abajo` se escribe después para que gane en un rombo que vuelve.
        foreach ($dependencias as $uno) {
            $sentidos[$uno->id] = 'abajo';
        }

        $nodos = new Collection([$activo, ...$dependencias->all(), ...$dependientes->all()]);
        $nodos = $nodos->unique('id')->values();

        $ids = $nodos->pluck('id')->all();

        /*
         * Las aristas, acotadas a los nodos que se van a dibujar. Sin el `IN` de
         * los dos extremos entrarían vínculos hacia activos que no están en el
         * lienzo y Vue Flow los descartaría en silencio, dejando aristas que no
         * se ven y un `console` limpio.
         */
        $aristas = DB::select(<<<'SQL'
            SELECT d.activo_id AS desde, d.depende_de_id AS hacia, d.nota
            FROM activo_dependencias d
            WHERE d.organizacion_id = ?
              AND d.activo_id = ANY(?)
              AND d.depende_de_id = ANY(?)
        SQL, [$organizacionId, '{'.implode(',', $ids).'}', '{'.implode(',', $ids).'}']);

        return [
            'nodos' => $nodos,
            'aristas' => array_map(static fn (object $fila): array => [
                'desde' => (int) $fila->desde,
                'hacia' => (int) $fila->hacia,
                'nota' => $fila->nota === null ? null : (string) $fila->nota,
            ], $aristas),
            'sentidos' => $sentidos,
        ];
    }

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
