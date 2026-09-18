<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El contexto de la organización, en el panel. § 4.1 y § 4.14.
 *
 * Contesta a tres preguntas y en este orden: **desde cuándo** es lo que hay
 * firmado, **qué forma tiene** el DAFO, y **qué se ha quedado sin atar**. La
 * primera es la que de verdad importa: un análisis del contexto de hace tres años
 * es un análisis que ya no describe a nadie, y es lo primero que un auditor mira.
 *
 * **Ninguna cifra va en rojo.** El rojo tiene tres dueños declarados y los tres
 * son «va mal de verdad»; aquí lo peor que hay es trabajo por hacer sobre algo que
 * la organización ha sabido ver y escribir. Pintarlo de alarma sería castigar por
 * haber hecho el análisis.
 *
 * **Y no hay porcentaje de nada**, por lo mismo que el plan de acción no lleva
 * porcentaje de tareas hechas: «cuestiones atadas a un riesgo» sube al vincular y
 * baja al apuntar una amenaza nueva, así que castigaría por seguir mirando el
 * entorno.
 */
#[TypeScript]
final class ResumenContextoPanel
{
    /**
     * @param  list<Reparto>  $porTipo
     */
    public function __construct(
        /** «Análisis n.º 3», o nulo si no hay ninguno aprobado todavía. */
        public readonly ?string $analisisVigente,
        public readonly ?string $fechaAnalisis,
        /** Cuántos meses hace que se firmó. Lo que contesta «¿sigue valiendo?». */
        public readonly ?int $mesesDesdeElAnalisis,
        /** Si hay una revisión abierta sin firmar. */
        public readonly bool $hayBorrador,
        public readonly int $cuestiones,
        public readonly array $porTipo,
        /** Debilidades y amenazas que no han acabado en ningún riesgo. */
        public readonly int $sinRiesgo,
        public readonly int $partes,
        /** Requisitos legales y contractuales: los que tienen consecuencia exigible. */
        public readonly int $requisitosQueObligan,
        /** De ésos, los que no tienen ninguna implantación detrás. */
        public readonly int $obligacionesSinCubrir,
        /**
         * Lo que se declaró sobre el cambio climático. Nulo mientras nadie lo haya
         * contestado, que no es lo mismo que haber contestado «no es pertinente».
         */
        public readonly ?bool $climaPertinente,
    ) {}
}
