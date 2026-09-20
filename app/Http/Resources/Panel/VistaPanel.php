<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Una de las tres vistas del panel, tal y como la pinta el conmutador.
 *
 * **`alertas` es lo que impide que una pestaña esconda un rojo.** La tira de
 * arriba los lleva todos y siempre se ve, así que el punto es la segunda red:
 * dice de dónde viene cada uno sin obligar a abrir las tres.
 *
 * `href` es una ruta de verdad y no una clave de estado: el conmutador del plan
 * de acción ya tomó esa decisión —«un conmutador que recuerda la última vista
 * hace que el enlace que alguien pega en un correo abra otra pantalla»— y aquí
 * vale igual.
 */
#[TypeScript]
final class VistaPanel
{
    public function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly string $href,
        /** Qué contesta esta vista, para el `title` del enlace. */
        public readonly string $pregunta,
        /** Cuántas alertas rojas hay dentro. Cero es sin punto. */
        public readonly int $alertas,
    ) {}
}
