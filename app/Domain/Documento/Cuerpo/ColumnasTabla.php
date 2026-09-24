<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

use App\Domain\Documento\Enums\TipoDocumento;

/**
 * Las columnas de la tabla larga de cada declaración.
 *
 * Estaban declaradas en `soa-iso.blade.php` y `dda-ens.blade.php`, que es donde
 * tenían sentido mientras la plantilla pintaba la tabla. Ahora la pinta
 * `MaterializarCuerpo` y se mudan aquí enteras, anchuras incluidas.
 *
 * **Lo que cambia entre la SoA y la DdA son exactamente estas columnas**, y no
 * por capricho: en ISO la aplicabilidad es una decisión que hay que justificar
 * —de ahí «Origen de la inclusión» y «Justificación de exclusión»— y en el ENS
 * es un cálculo que hay que poder rastrear —de ahí «Exigencia» y «Origen de la
 * exigencia»—. Lo demás es idéntico.
 *
 * Las anchuras van en pulgadas y **tienen que caber en el ancho útil de la
 * hoja**: 10,28 pulgadas, que es el A4 apaisado de `GeometriaPagina` menos sus
 * dos márgenes laterales. Sin anchuras declaradas Chromium reparte a su gusto y
 * la columna de justificación se come media página; pasándose, hace algo peor.
 *
 * Y se pasaban: sumaban 11,32 en la SoA y 11,24 en la DdA. Con
 * `table-layout: fixed` las anchuras declaradas ganan al `width: 100%` de la
 * hoja, así que la tabla salía una pulgada más ancha que la caja de texto y la
 * última columna —la correspondencia cruzada, que es el argumento del producto—
 * se imprimía dentro del margen derecho y se cortaba en el borde del papel. En
 * el PDF no se veía venir: no hay error, no hay barra de desplazamiento, sólo
 * una columna más estrecha de lo que debería y un texto que acaba antes de
 * tiempo. Lo destapó el editor, que ahora compone sobre la misma hoja.
 *
 * `tests/Unit/Documento/ColumnasTablaTest.php` fija que sigan cabiendo.
 *
 * @phpstan-type Columna array{clave: string, titulo: string, ancho: string}
 */
final class ColumnasTabla
{
    /**
     * @return list<array{clave: string, titulo: string, ancho: string}>
     */
    public static function para(TipoDocumento $tipo): array
    {
        return match ($tipo) {
            TipoDocumento::SoaIso => [
                ['clave' => 'codigo', 'titulo' => 'Control', 'ancho' => '0.58in'],
                ['clave' => 'titulo', 'titulo' => 'Título', 'ancho' => '1.95in'],
                ['clave' => 'aplica', 'titulo' => 'Aplica', 'ancho' => '0.4in'],
                ['clave' => 'justificacionInclusion', 'titulo' => 'Origen de la inclusión', 'ancho' => '1.45in'],
                ['clave' => 'justificacion', 'titulo' => 'Justificación de exclusión', 'ancho' => '1.5in'],
                ['clave' => 'estado', 'titulo' => 'Estado', 'ancho' => '0.72in'],
                ['clave' => 'madurez', 'titulo' => 'Madurez', 'ancho' => '0.5in'],
                ['clave' => 'responsable', 'titulo' => 'Responsable', 'ancho' => '0.85in'],
                ['clave' => 'evidencias', 'titulo' => 'Evidencia', 'ancho' => '1.35in'],
                ['clave' => 'correspondencias', 'titulo' => 'Correspondencia ENS', 'ancho' => '0.9in'],
            ],

            TipoDocumento::DdaEns => [
                ['clave' => 'codigo', 'titulo' => 'Medida', 'ancho' => '0.65in'],
                ['clave' => 'titulo', 'titulo' => 'Título', 'ancho' => '1.8in'],
                ['clave' => 'exigencia', 'titulo' => 'Exigencia', 'ancho' => '0.65in'],
                ['clave' => 'origenExigencia', 'titulo' => 'Origen de la exigencia', 'ancho' => '1.15in'],
                ['clave' => 'aplica', 'titulo' => 'Aplica', 'ancho' => '0.4in'],
                ['clave' => 'justificacion', 'titulo' => 'Justificación', 'ancho' => '1.25in'],
                ['clave' => 'estado', 'titulo' => 'Estado', 'ancho' => '0.72in'],
                ['clave' => 'madurez', 'titulo' => 'Madurez', 'ancho' => '0.5in'],
                ['clave' => 'responsable', 'titulo' => 'Responsable', 'ancho' => '0.85in'],
                ['clave' => 'evidencias', 'titulo' => 'Evidencia', 'ancho' => '1.28in'],
                ['clave' => 'correspondencias', 'titulo' => 'Correspondencia ISO', 'ancho' => '0.95in'],
            ],

            /*
             * El plan no repite las columnas de la DdA: ahí sobran «Aplica»
             * —todas aplican, si no no estarían— y «Justificación», que es de
             * una exclusión que aquí no existe. Lo que entra en su lugar es lo
             * que convierte una lista en un plan: para cuándo, quién, qué
             * trabajo hay apuntado, cuánto cuesta y qué riesgo lo motiva.
             */
            TipoDocumento::PlanAdecuacionEns => [
                ['clave' => 'codigo', 'titulo' => 'Medida', 'ancho' => '0.65in'],
                ['clave' => 'titulo', 'titulo' => 'Título', 'ancho' => '1.85in'],
                ['clave' => 'exigencia', 'titulo' => 'Exigencia', 'ancho' => '0.6in'],
                ['clave' => 'estado', 'titulo' => 'Estado', 'ancho' => '0.72in'],
                ['clave' => 'responsable', 'titulo' => 'Responsable', 'ancho' => '0.85in'],
                ['clave' => 'fechaObjetivo', 'titulo' => 'Fecha objetivo', 'ancho' => '0.85in'],
                ['clave' => 'tareas', 'titulo' => 'Trabajo planificado', 'ancho' => '2.4in'],
                ['clave' => 'coste', 'titulo' => 'Coste estimado', 'ancho' => '0.8in'],
                ['clave' => 'riesgos', 'titulo' => 'Riesgo que la motiva', 'ancho' => '0.95in'],
            ],

            /*
             * Un documento redactado no tiene tabla larga: su contenido lo
             * escribe la organización y no sale de ninguna consulta. Lista vacía
             * y no una excepción, porque quien llame a esto está pintando un
             * documento y no tiene por qué saber de qué tipo es.
             *
             * **El análisis del contexto tampoco, y sí tiene tablas.** Es la única
             * excepción a la equivalencia «calculado = tabla de requisitos»: sus
             * dos tablas son de cuestiones y de partes interesadas, y no de filas
             * del catálogo. Sus columnas las declara cada materializador, porque
             * son dos tablas distintas y esto sólo sabe describir una.
             */
            TipoDocumento::AnalisisContexto,
            TipoDocumento::ActaRevision,
            TipoDocumento::DeclaracionConformidadEns,
            TipoDocumento::Politica,
            TipoDocumento::Norma,
            TipoDocumento::Procedimiento,
            TipoDocumento::PlanContinuidad => [],
        };
    }

    /** La etiqueta del recuento total del resumen. */
    public static function etiquetaTotal(TipoDocumento $tipo): string
    {
        return match ($tipo) {
            TipoDocumento::SoaIso => 'Controles del Anexo A',
            TipoDocumento::DdaEns => 'Medidas del Anexo II',
            // Lo que cuenta la tabla del plan no son las medidas del Anexo II,
            // son las que faltan. El denominador va aparte, en su propia cifra.
            TipoDocumento::PlanAdecuacionEns => 'Medidas pendientes',

            // Sin tabla larga no hay recuento que etiquetar.
            TipoDocumento::AnalisisContexto,
            TipoDocumento::ActaRevision,
            TipoDocumento::DeclaracionConformidadEns,
            TipoDocumento::Politica,
            TipoDocumento::Norma,
            TipoDocumento::Procedimiento,
            TipoDocumento::PlanContinuidad => '',
        };
    }
}
