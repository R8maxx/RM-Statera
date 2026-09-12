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

    public function etiqueta(): string
    {
        return match ($this) {
            self::Tarea => 'Tarea',
            self::Evidencia => 'Evidencia',
        };
    }

    /** El icono con el que se distingue de un vistazo en el calendario. */
    public function icono(): string
    {
        return match ($this) {
            self::Tarea => 'ListTodo',
            self::Evidencia => 'Paperclip',
        };
    }

    public function url(int $id): string
    {
        return match ($this) {
            self::Tarea => "/tareas/{$id}",
            self::Evidencia => "/evidencias/{$id}",
        };
    }
}
