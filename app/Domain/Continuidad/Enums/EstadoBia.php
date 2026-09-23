<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Enums;

/**
 * El ciclo de un BIA: § 4.11.
 *
 * Misma forma que `EstadoAnalisis` —borrador, aprobado, obsoleto— y mismo
 * argumento: lo que hace útil un BIA no es lo que dice hoy, es poder comparar
 * el de este año con el del anterior. **A diferencia de un análisis del
 * contexto, aquí el ciclo va y viene**: `Aprobado` vuelve a `Borrador` cuando
 * se edita —lo hace `EditarBia`, nunca una persona a mano— y `Obsoleto` vuelve
 * a `Borrador` cuando el servicio se retoma. No hay una puerta que sólo abra
 * el sistema, como la de `EstadoAnalisis::Obsoleto`, porque aquí «obsoleto» no
 * es una consecuencia de aprobar el siguiente: es una decisión de que el
 * servicio ya no aplica, y esa decisión se puede deshacer.
 */
enum EstadoBia: string
{
    case Borrador = 'borrador';
    case Aprobado = 'aprobado';
    case Obsoleto = 'obsoleto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Aprobado => 'Aprobado',
            self::Obsoleto => 'Obsoleto',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Borrador => 'PenLine',
            // El sello, no el visto: lo que hay detrás es una aprobación.
            self::Aprobado => 'BadgeCheck',
            self::Obsoleto => 'Archive',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Borrador => 'no_iniciado',
            self::Aprobado => 'implantado',
            self::Obsoleto => 'no_aplica',
        };
    }

    /**
     * A qué estados se puede pasar desde éste.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Borrador => [self::Aprobado, self::Obsoleto],
            self::Aprobado => [self::Borrador, self::Obsoleto],
            self::Obsoleto => [self::Borrador],
        };
    }

    public function admite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }
}
