<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * En qué punto está la relación con un proveedor (§ 4.9).
 *
 * **Tres de los cinco los pone una evaluación y no una persona**: homologado,
 * condicionado y rechazado son el resultado de haber comprobado el contrato, y
 * elegirlos en un desplegable sería homologar sin evaluar. Lo que se mueve a
 * mano es retirar —se deja de trabajar con él— y reactivar, que devuelve a
 * «en evaluación» porque lo evaluado hace tiempo ya no vale.
 */
#[TypeScript]
enum EstadoProveedor: string
{
    case EnEvaluacion = 'en_evaluacion';
    case Homologado = 'homologado';
    case Condicionado = 'condicionado';
    case Rechazado = 'rechazado';
    case Retirado = 'retirado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnEvaluacion => 'En evaluación',
            self::Homologado => 'Homologado',
            self::Condicionado => 'Condicionado',
            self::Rechazado => 'Rechazado',
            self::Retirado => 'Retirado',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::EnEvaluacion => 'Todavía no se ha comprobado su contrato, o se reactivó y hay que volver a hacerlo.',
            self::Homologado => 'La última evaluación fue apta: se puede trabajar con él.',
            self::Condicionado => 'La última evaluación fue apta con condiciones: hay algo pendiente de resolver.',
            self::Rechazado => 'La última evaluación no fue apta.',
            self::Retirado => 'Ya no se trabaja con él. No se reevalúa.',
        };
    }

    /** Si se le sigue exigiendo reevaluación: lo retirado ya no presta nada. */
    public function seReevalua(): bool
    {
        return $this !== self::Retirado;
    }

    /*
     * Ningún estado gasta el rojo: un proveedor rechazado es una decisión bien
     * tomada. Lo que va mal de verdad es la evaluación vencida, y eso lo pinta
     * el calendario.
     */
    public function tono(): string
    {
        return match ($this) {
            self::EnEvaluacion => 'en_revision',
            self::Homologado => 'implantado',
            self::Condicionado => 'en_progreso',
            self::Rechazado => 'no_aplica',
            self::Retirado => 'retirado',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::EnEvaluacion => 'Eye',
            self::Homologado => 'BadgeCheck',
            self::Condicionado => 'CircleDotDashed',
            self::Rechazado => 'Ban',
            self::Retirado => 'Archive',
        };
    }
}
