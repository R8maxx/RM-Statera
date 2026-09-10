<?php

declare(strict_types=1);

namespace App\Domain\Activo;

use App\Domain\Activo\Models\Activo;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\ValoracionDimensiones;
use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Support\Facades\DB;

/**
 * Lo que un activo vale de verdad: el máximo entre su valoración propia y la de
 * todo lo que depende de él.
 *
 * Es la regla de MAGERIT y la que una hoja de cálculo no aplica: una base de
 * datos que alguien valoró «bajo» en disponibilidad porque «total, es una base
 * de datos» pasa a valer «alto» en cuanto sostiene un servicio esencial. Sin
 * esto, el inventario deja sin proteger justo lo que hay debajo de lo
 * importante.
 *
 * **La propia nunca se toca.** El auditor pregunta qué valoró la organización,
 * no qué dedujo la herramienta, y las dos cifras se enseñan juntas.
 */
final class ValoracionEfectiva
{
    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly GrafoActivos $grafo,
    ) {}

    /**
     * La de un activo concreto. Recorre sus dependientes y pliega.
     */
    public function de(Activo $activo): ValoracionDimensiones
    {
        $efectiva = $activo->valoracion();

        foreach ($this->grafo->dependientesDe($activo) as $dependiente) {
            $efectiva = $efectiva->elevadaCon($dependiente->valoracion());
        }

        return $efectiva;
    }

    /**
     * Quién eleva la valoración de este activo por encima de la suya, y en qué
     * dimensiones. Sin esto la cifra efectiva parece un error de la herramienta.
     *
     * @return list<array{activo: Activo, dimensiones: list<Dimension>}>
     */
    public function motivos(Activo $activo): array
    {
        $propia = $activo->valoracion();
        $motivos = [];

        foreach ($this->grafo->dependientesDe($activo) as $dependiente) {
            $suya = $dependiente->valoracion();

            $dimensiones = array_values(array_filter(
                Dimension::cases(),
                static fn (Dimension $dimension): bool => $suya->nivelDe($dimension)->peso() > $propia->nivelDe($dimension)->peso(),
            ));

            if ($dimensiones !== []) {
                $motivos[] = ['activo' => $dependiente, 'dimensiones' => $dimensiones];
            }
        }

        return $motivos;
    }

    /**
     * La de todos los activos de la organización, en una sola consulta.
     *
     * La tabla del inventario enseña la valoración efectiva de cada fila, y
     * resolverla activo a activo serían cincuenta CTE recursivas por página.
     * Aquí la propagación entera se hace de una vez y se indexa por id.
     *
     * @return array<int, ValoracionDimensiones>
     */
    public function paraLaOrganizacion(): array
    {
        $organizacionId = $this->contexto->idObligatorio();
        $columnas = Activo::columnasDeValoracion();

        // El orden de los niveles vive en NivelDimension::peso(); aquí se traduce
        // a SQL desde el enum para que no haya una segunda escala escrita a mano
        // que se pueda desincronizar de la primera.
        $agregados = [];
        foreach ($columnas as $dimension => $columna) {
            $agregados[] = sprintf(
                'MAX(%s) AS %s',
                $this->casoDePeso("origen.{$columna}"),
                $columna,
            );
        }
        $seleccion = implode(",\n                   ", $agregados);

        /*
         * La propagación va del que depende al que sostiene: por cada arista
         * «X depende de Y», el valor de X alcanza a Y. La rama base hace que cada
         * activo se alcance a sí mismo, que es lo que mete su valoración propia
         * en el máximo sin tratarla como un caso aparte.
         */
        $filas = DB::select(<<<SQL
            WITH RECURSIVE alcance AS (
                SELECT a.id AS sostiene_id, a.id AS origen_id, ARRAY[a.id] AS ruta
                FROM activos a
                WHERE a.organizacion_id = ?

                UNION ALL

                SELECT d.depende_de_id, alcance.origen_id, alcance.ruta || d.depende_de_id
                FROM alcance
                INNER JOIN activo_dependencias d ON d.activo_id = alcance.sostiene_id
                WHERE d.organizacion_id = ?
                  AND NOT (d.depende_de_id = ANY(alcance.ruta))
            )
            SELECT alcance.sostiene_id AS id,
                   {$seleccion}
            FROM alcance
            INNER JOIN activos origen ON origen.id = alcance.origen_id
            GROUP BY alcance.sostiene_id
        SQL, [$organizacionId, $organizacionId]);

        $porActivo = [];

        foreach ($filas as $fila) {
            $niveles = [];

            foreach ($columnas as $dimension => $columna) {
                $niveles[$dimension] = $this->nivelDePeso((int) $fila->{$columna});
            }

            $porActivo[(int) $fila->id] = ValoracionDimensiones::desdeArray($niveles);
        }

        return $porActivo;
    }

    /** El `CASE` que convierte el nivel almacenado en su peso comparable. */
    private function casoDePeso(string $expresion): string
    {
        $ramas = '';

        foreach (NivelDimension::cases() as $nivel) {
            $ramas .= sprintf(" WHEN '%s' THEN %d", $nivel->value, $nivel->peso());
        }

        return sprintf('CASE %s%s ELSE 0 END', $expresion, $ramas);
    }

    private function nivelDePeso(int $peso): NivelDimension
    {
        foreach (NivelDimension::cases() as $nivel) {
            if ($nivel->peso() === $peso) {
                return $nivel;
            }
        }

        return NivelDimension::Na;
    }
}
