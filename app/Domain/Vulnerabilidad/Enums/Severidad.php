<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cuánto pesa una vulnerabilidad, y por tanto cuánto plazo tiene.
 *
 * **Con CVSS se deriva y no se elige**, con los tramos cualitativos de la
 * especificación de FIRST (CVSS v3.1, § 5): 0 ninguna, 0,1–3,9 baja, 4,0–6,9
 * media, 7,0–8,9 alta y 9,0–10 crítica. El `CHECK` de la tabla repite la misma
 * regla, así que no hay forma de guardar una puntuación con otra severidad.
 *
 * `Informativa` es la «ninguna» de FIRST: se registra, y no tiene plazo.
 */
#[TypeScript]
enum Severidad: string
{
    case Informativa = 'informativa';
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case Critica = 'critica';

    public static function desdeCvss(float $puntuacion): self
    {
        return match (true) {
            $puntuacion >= 9.0 => self::Critica,
            $puntuacion >= 7.0 => self::Alta,
            $puntuacion >= 4.0 => self::Media,
            $puntuacion > 0.0 => self::Baja,
            default => self::Informativa,
        };
    }

    public function peso(): int
    {
        return match ($this) {
            self::Informativa => 0,
            self::Baja => 1,
            self::Media => 2,
            self::Alta => 3,
            self::Critica => 4,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Informativa => 'Informativa',
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
            self::Critica => 'Crítica',
        };
    }

    /*
     * La familia de prioridad y no el rojo: una vulnerabilidad crítica recién
     * detectada y en plazo no va mal, se está atendiendo. El rojo es del plazo
     * vencido, igual que en una tarea.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Informativa => 'no_aplica',
            self::Baja => 'prioridad-baja',
            self::Media => 'prioridad-media',
            self::Alta => 'prioridad-alta',
            self::Critica => 'prioridad-critica',
        };
    }
}
