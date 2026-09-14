<?php

declare(strict_types=1);

namespace App\Domain\Riesgo\Enums;

/**
 * Qué se decide hacer con un riesgo: las cuatro opciones de ISO 27005 y de
 * § 2.2 de la especificación.
 *
 * **No es un estado y por eso no hay `EstadoRiesgo`.** Un riesgo no va pasando
 * por situaciones: se valora, se decide qué hacer con él y alguien lo acepta.
 * Lo que en otros módulos sería el estado aquí son dos hechos distintos y ya
 * registrados —la `decision` y el par `aceptada_por`/`aceptada_en`—, y un enum de
 * estado paralelo sería un tercer dato que mantener sincronizado con los otros
 * dos.
 *
 * `Aceptar` es la que más se malinterpreta: no significa «no pasa nada», significa
 * que la organización decide convivir con él a sabiendas. Por eso aceptar exige
 * firma y fecha, y por eso `riesgos.aceptar` es un permiso aparte de
 * `riesgos.gestionar`.
 */
enum DecisionRiesgo: string
{
    case Mitigar = 'mitigar';
    case Aceptar = 'aceptar';
    case Transferir = 'transferir';
    case Evitar = 'evitar';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Mitigar => 'Mitigar',
            self::Aceptar => 'Aceptar',
            self::Transferir => 'Transferir',
            self::Evitar => 'Evitar',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Mitigar => 'Se implantan salvaguardas para bajarlo hasta el umbral.',
            self::Aceptar => 'Se decide convivir con él tal como está, a sabiendas.',
            self::Transferir => 'Lo asume un tercero: un seguro, un proveedor o un contrato.',
            self::Evitar => 'Se suprime la actividad o el activo que lo produce.',
        };
    }

    /**
     * Si la decisión da por hecho que hay trabajo detrás.
     *
     * Sólo `Mitigar` lo da: un riesgo que se mitiga y no tiene ni una salvaguarda
     * vinculada es una declaración de intenciones, no un tratamiento. Lo usa
     * `RegistroRiesgos` para contarlos.
     */
    public function exigeSalvaguardas(): bool
    {
        return $this === self::Mitigar;
    }

    public function icono(): string
    {
        return match ($this) {
            self::Mitigar => 'ShieldPlus',
            self::Aceptar => 'Handshake',
            self::Transferir => 'ArrowRightLeft',
            self::Evitar => 'CircleSlash',
        };
    }

    /**
     * El tono del dominio con el que se pinta.
     *
     * Se reutiliza el vocabulario de `--estado-*` que ya existe, y la lectura es
     * «cuánto queda expuesto después de decidir»: `Evitar` suprime la exposición
     * y va en verde; `Transferir` la coloca en otro sitio y va en el azul de lo
     * planificado; `Mitigar` deja trabajo por hacer y va en ámbar; `Aceptar` no
     * cambia nada del riesgo y va en gris.
     *
     * **Ninguna es roja.** Aceptar un riesgo alto es una decisión de la dirección,
     * no un incumplimiento, y pintarla de rojo convertiría en alarma algo que la
     * organización tiene todo el derecho a decidir. El rojo del módulo se lo
     * quedan las dos cosas que sí van mal: un riesgo por encima del umbral
     * crítico y un residual sin salvaguardas que lo respalden.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Mitigar => 'en_progreso',
            self::Aceptar => 'no_aplica',
            self::Transferir => 'planificado',
            self::Evitar => 'implantado',
        };
    }
}
