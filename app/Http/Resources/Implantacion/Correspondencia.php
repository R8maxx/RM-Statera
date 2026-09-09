<?php

declare(strict_types=1);

namespace App\Http\Resources\Implantacion;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un requisito de otro marco que se corresponde con éste.
 *
 * Es el mapeo cruzado, que es el problema que el producto resuelve: una captura
 * puede probar un control de ISO y tres medidas del ENS, y hasta ahora eso se
 * llevaba en dos hojas de cálculo que nadie sincronizaba.
 *
 * `cubreDelTodo` viaja aparte de la etiqueta porque la interfaz tiene que poder
 * distinguir «esto vale como prueba» de «esto cubre una parte» sin interpretar
 * un texto.
 */
#[TypeScript]
final class Correspondencia
{
    /** @param  list<EstadoCorrespondencia>  $implantaciones */
    public function __construct(
        public readonly int $requisitoId,
        public readonly string $codigo,
        public readonly string $titulo,
        public readonly ?string $marco,
        public readonly string $tipo,
        public readonly bool $cubreDelTodo,
        public readonly ?string $nota,
        public readonly array $implantaciones,
    ) {}
}
