<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * De qué es un vencimiento.
 *
 * § 4.16 —el calendario de obligaciones— tiene una lista larga esperando:
 * revisión por la dirección, auditoría interna, reevaluación de riesgos,
 * formación, pruebas de continuidad, reevaluación de proveedores, el informe
 * INES y el periodo de medición de un indicador. Todas se añaden aquí y en
 * `CalendarioVencimientos`, y ni el correo ni el calendario tienen que
 * enterarse.
 *
 * **Sin recuento en la cabecera, a propósito.** Decía «hoy son dos» y pasó a ser
 * falso el día que el § 4.5 añadió `Fuente::Documento`, sin que nadie lo notara
 * —el mismo descuido que la limitación del plan de adecuación, que enumeraba las
 * fuentes que el calendario sí recoge y se quedó corta a la vez—. Un recuento
 * dentro de un comentario envejece cada vez que el producto crece; `cases()` no.
 */
#[TypeScript]
enum Fuente: string
{
    case Tarea = 'tarea';
    case Evidencia = 'evidencia';

    /**
     * La tercera, y la primera que no es un registro con fecha propia.
     *
     * Lo que vence no es el documento sino **la revisión de su versión
     * aprobada**: la fecha se calcula al firmar, desde la periodicidad que
     * declara el documento, y se congela en la versión. Un documento sin
     * periodicidad no vence nunca, y es una respuesta legítima — una Declaración
     * de Aplicabilidad se rehace cuando cambia el alcance, no cuando pasa un año.
     */
    case Documento = 'documento';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Tarea => 'Tarea',
            self::Evidencia => 'Evidencia',
            self::Documento => 'Documento',
        };
    }

    /** El icono con el que se distingue de un vistazo en el calendario. */
    public function icono(): string
    {
        return match ($this) {
            self::Tarea => 'ListTodo',
            self::Evidencia => 'Paperclip',
            self::Documento => 'FileCheck',
        };
    }

    public function url(int $id): string
    {
        return match ($this) {
            self::Tarea => "/tareas/{$id}",
            self::Evidencia => "/evidencias/{$id}",
            self::Documento => "/documentos/{$id}",
        };
    }
}
