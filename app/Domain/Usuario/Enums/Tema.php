<?php

declare(strict_types=1);

namespace App\Domain\Usuario\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El tema de la interfaz que ha elegido la cuenta.
 *
 * Tres estados reales y no dos: `sistema` sigue el cambio automático del
 * sistema operativo, y es el de partida. Lo lee `app.blade.php` antes del
 * primer pintado, así que un navegador nuevo no enseña un fogonazo del otro.
 */
#[TypeScript]
enum Tema: string
{
    case Claro = 'claro';
    case Oscuro = 'oscuro';
    case Sistema = 'sistema';
}
