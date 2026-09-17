<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Enums;

/**
 * Los tres tipos del § 2.2, y no son tres formas de lo mismo.
 *
 * **La interna la hace la organización sobre sí misma** (ISO 9.2): es la que
 * alimenta la revisión por la dirección y la que produce la mayoría de las no
 * conformidades. **La externa la hace un tercero acreditado** y su resultado es
 * la certificación. **La autoevaluación es del ENS**: para categoría básica, el
 * RD 311/2022 no exige auditoría por entidad acreditada, exige que la
 * organización se evalúe y lo declare (§ 4.17).
 *
 * De ahí que la entidad certificadora sólo tenga sentido en la externa, y que el
 * `CHECK` de la tabla lo imponga: una interna con entidad acreditada detrás es un
 * dato que contradice a su propio tipo.
 */
enum TipoAuditoria: string
{
    case Interna = 'interna';
    case Externa = 'externa';
    case Autoevaluacion = 'autoevaluacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Interna => 'Auditoría interna',
            self::Externa => 'Auditoría externa',
            self::Autoevaluacion => 'Autoevaluación',
        };
    }

    /** La forma corta, para el badge de una tabla. */
    public function etiquetaCorta(): string
    {
        return match ($this) {
            self::Interna => 'Interna',
            self::Externa => 'Externa',
            self::Autoevaluacion => 'Autoevaluación',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Interna => 'La organización se audita a sí misma. Es la que pide la cláusula 9.2 de ISO.',
            self::Externa => 'La realiza un tercero acreditado, y de ella sale la certificación.',
            self::Autoevaluacion => 'El flujo de conformidad del ENS para categoría básica.',
        };
    }

    /** Sólo la externa la firma una entidad acreditada por ENAC. */
    public function admiteEntidadCertificadora(): bool
    {
        return $this === self::Externa;
    }

    public function icono(): string
    {
        return match ($this) {
            self::Interna => 'SearchCheck',
            self::Externa => 'BadgeCheck',
            self::Autoevaluacion => 'UserCheck',
        };
    }

    /**
     * Un tipo dice **qué es** una auditoría, no cómo va, así que va en el gris
     * neutro de procedencia y no en la paleta de estados — el mismo criterio por
     * el que `TipoDocumento::tono()` devuelve `marco`.
     */
    public function tono(): string
    {
        return 'marco';
    }
}
