<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Lo que concluye una evaluación, y el estado en que deja al proveedor. */
#[TypeScript]
enum ResultadoEvaluacion: string
{
    case Apto = 'apto';
    case AptoConCondiciones = 'apto_con_condiciones';
    case NoApto = 'no_apto';

    public function estadoResultante(): EstadoProveedor
    {
        return match ($this) {
            self::Apto => EstadoProveedor::Homologado,
            self::AptoConCondiciones => EstadoProveedor::Condicionado,
            self::NoApto => EstadoProveedor::Rechazado,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Apto => 'Apto',
            self::AptoConCondiciones => 'Apto con condiciones',
            self::NoApto => 'No apto',
        };
    }

    public function tono(): string
    {
        return $this->estadoResultante()->tono();
    }

    public function icono(): string
    {
        return $this->estadoResultante()->icono();
    }
}
