<?php

declare(strict_types=1);

namespace App\Http\Resources\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Qué celda pinta la columna. Es el contrato entre `Columna` y `DataTable`. */
#[TypeScript]
enum TipoColumna: string
{
    case Texto = 'texto';
    case Numero = 'numero';
    case Fecha = 'fecha';
    case FechaHora = 'fecha_hora';
    case Booleano = 'booleano';
    case Badge = 'badge';
    case Enlace = 'enlace';
    case Progreso = 'progreso';
    /** Un nivel dentro de una progresión conocida: la madurez L0–L5. */
    case Escala = 'escala';
}
