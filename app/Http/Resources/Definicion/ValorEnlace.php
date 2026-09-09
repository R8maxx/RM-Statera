<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El valor de una celda de tipo `enlace`.
 *
 * `TipoColumna::Enlace` existía desde el principio, pero sin una forma que
 * transportara el destino: la celda recibía una cadena suelta y no había manera
 * de saber a dónde apuntaba, así que caía al texto plano. La URL la calcula el
 * `Recurso` en su `formato()`, que es quien conoce las rutas.
 *
 * `externo` cambia cómo se navega: los enlaces internos van por Inertia y los
 * externos abren en otra pestaña con `rel="noopener"`.
 */
#[TypeScript]
final class ValorEnlace
{
    public function __construct(
        public readonly string $etiqueta,
        public readonly string $url,
        public readonly bool $externo = false,
    ) {}
}
