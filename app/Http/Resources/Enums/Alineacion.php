<?php

declare(strict_types=1);

namespace App\Http\Resources\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum Alineacion: string
{
    case Izquierda = 'izquierda';
    case Centro = 'centro';
    case Derecha = 'derecha';
}
