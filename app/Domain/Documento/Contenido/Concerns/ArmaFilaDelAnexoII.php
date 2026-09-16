<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido\Concerns;

use App\Domain\Documento\Contenido\FilaRequisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Http\Resources\Implantacion\Correspondencia;

/**
 * Una fila del Anexo II, tal como la leen la DdA y el plan de adecuación.
 *
 * Los quince campos que describen una medida del ENS —su exigencia, de dónde
 * sale, si la modula una dimensión, su madurez, su responsable, su prueba y su
 * correspondencia con ISO— son los mismos en los dos documentos, y `FilaRequisito`
 * es `readonly`: no se puede construir en la clase base y decorar en el hijo,
 * porque PHP 8.4 no tiene `clone with`. Sin esto, el plan copiaba las quince
 * asignaciones y el día que cambiara una columna del ENS habría que acordarse de
 * cambiarla dos veces.
 *
 * Lo que el plan añade viaja como parámetros con nombre y no como un array de
 * extras: son cuatro campos concretos y un array los devolvería a `mixed`.
 */
trait ArmaFilaDelAnexoII
{
    /** El nodo del que cuelga la medida: `org`, `op.acc`, `mp.info`… */
    protected function grupoRaiz(): string
    {
        return 'Anexo II';
    }

    /**
     * @param  array<int, list<Correspondencia>>  $correspondencias
     * @param  list<string>  $tareas
     * @param  list<string>  $riesgos
     */
    protected function filaDelAnexoII(
        Implantacion $implantacion,
        array $correspondencias,
        ?string $fechaObjetivo = null,
        array $tareas = [],
        ?string $costeEstimado = null,
        array $riesgos = [],
    ): FilaRequisito {
        $requisito = $implantacion->requisito;

        return new FilaRequisito(
            grupo: $this->grupo($implantacion),
            codigo: $requisito->codigo,
            titulo: $requisito->titulo,
            aplica: $implantacion->aplica,
            estado: $implantacion->estado->value,
            estadoEtiqueta: $implantacion->estado->etiqueta(),
            estadoTono: $implantacion->estado->value,
            justificacion: $implantacion->justificacion,
            exigencia: $implantacion->exigencia_calculada?->etiqueta(),
            origenExigencia: $implantacion->origen_exigencia?->etiqueta(),
            // Sólo cuando la exigencia viene de modular por una dimensión: en el
            // resto de casos la columna diría algo que no explica nada.
            dimensionModuladora: $implantacion->dimension_moduladora?->nombre(),
            madurez: $implantacion->nivel_madurez?->etiqueta(),
            madurezValor: $implantacion->nivel_madurez?->valor(),
            responsable: $implantacion->responsable?->name,
            evidencias: $this->evidenciasDe($implantacion),
            correspondencias: $this->codigosCorrespondientes($implantacion, $correspondencias),
            fechaObjetivo: $fechaObjetivo,
            tareas: $tareas,
            costeEstimado: $costeEstimado,
            riesgos: $riesgos,
        );
    }
}
