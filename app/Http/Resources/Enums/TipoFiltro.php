<?php

declare(strict_types=1);

namespace App\Http\Resources\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TipoFiltro: string
{
    /** Cruza varios campos a la vez y vive en la barra, no en una columna. */
    case Busqueda = 'busqueda';
    case Texto = 'texto';
    case Select = 'select';
    case MultiSelect = 'multi_select';
    case Booleano = 'booleano';
    case RangoFechas = 'rango_fechas';
}
