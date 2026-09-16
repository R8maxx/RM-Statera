<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * De qué es un vencimiento.
 *
 * Hoy son dos, y § 4.16 —el calendario de obligaciones— tiene una lista larga
 * esperando: revisión por la dirección, auditoría interna, reevaluación de
 * riesgos, formación, pruebas de continuidad, reevaluación de proveedores, el
 * informe INES. Todas se añaden aquí y en `CalendarioVencimientos`, y ni el
 * correo ni el calendario tienen que enterarse.
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
