<?php

declare(strict_types=1);

namespace App\Domain\Sistema;

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\ValoracionDimensiones;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Implantacion\ResultadoGeneracion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Sistema\Models\ValoracionDimension;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Escribe la valoración de las cinco dimensiones y recalcula el conjunto
 * exigible en la misma transacción.
 *
 * Las dos cosas van juntas porque separarlas deja al sistema con una categoría
 * que no se corresponde con sus implantaciones, y esa incoherencia es
 * exactamente lo que un auditor contrasta primero.
 *
 * `simular()` existe por el mismo motivo por el que `catalogo:importar` tiene
 * `--dry-run`: quien baja una dimensión de medio a bajo tiene que ver qué deja
 * de exigírsele ANTES de aceptarlo. Y simula sobre la valoración escrita, no
 * sobre una copia en memoria, porque el motor lee de la base: es la única forma
 * de que el diff que se enseña sea el que se aplicará.
 */
final class AplicarValoracion
{
    public function __construct(private readonly GeneradorImplantaciones $generador) {}

    /** @param  array<string, ?string>  $justificaciones  Indexadas por código de dimensión. */
    public function aplicar(
        Sistema $sistema,
        ValoracionDimensiones $valoracion,
        array $justificaciones = [],
    ): ResultadoGeneracion {
        return $this->ejecutar($sistema, $valoracion, $justificaciones, simulacion: false);
    }

    /** @param  array<string, ?string>  $justificaciones */
    public function simular(
        Sistema $sistema,
        ValoracionDimensiones $valoracion,
        array $justificaciones = [],
    ): ResultadoGeneracion {
        return $this->ejecutar($sistema, $valoracion, $justificaciones, simulacion: true);
    }

    /** @param  array<string, ?string>  $justificaciones */
    private function ejecutar(
        Sistema $sistema,
        ValoracionDimensiones $valoracion,
        array $justificaciones,
        bool $simulacion,
    ): ResultadoGeneracion {
        DB::beginTransaction();

        try {
            $this->escribir($sistema, $valoracion, $justificaciones);

            // El generador abre su propia transacción, que aquí dentro es un
            // savepoint: si es simulación la revierte él, y la escritura de la
            // valoración la revierte ésta. No queda ni una fila tocada, pero el
            // diff sale de leer lo que se habría guardado de verdad.
            $resultado = $this->generador->generar($sistema, $simulacion);

            if ($simulacion) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            return $resultado;
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Cinco filas, siempre las cinco.
     *
     * Una dimensión sin fila y una dimensión valorada `na` significan lo mismo
     * para el Anexo I, pero sólo la segunda deja constancia de que alguien lo
     * decidió, que es lo que se le enseña al auditor.
     *
     * @param  array<string, ?string>  $justificaciones
     */
    private function escribir(Sistema $sistema, ValoracionDimensiones $valoracion, array $justificaciones): void
    {
        foreach (Dimension::cases() as $dimension) {
            ValoracionDimension::query()->updateOrCreate(
                ['sistema_id' => $sistema->id, 'dimension' => $dimension->value],
                [
                    'nivel' => $valoracion->nivelDe($dimension)->value,
                    'justificacion' => $justificaciones[$dimension->value] ?? null,
                ],
            );
        }
    }
}
