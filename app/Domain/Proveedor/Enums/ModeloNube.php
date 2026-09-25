<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** El modelo de servicio en la nube, para `op.nub.1` y A.5.23. */
#[TypeScript]
enum ModeloNube: string
{
    case Iaas = 'iaas';
    case Paas = 'paas';
    case Saas = 'saas';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Iaas => 'Infraestructura (IaaS)',
            self::Paas => 'Plataforma (PaaS)',
            self::Saas => 'Aplicación (SaaS)',
        };
    }
}
