<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Por dónde pasa una vulnerabilidad desde que se detecta.
 *
 * **Mitigada no es cerrada.** Mitigada es que se aplicó el arreglo; cerrada es
 * que alguien comprobó que la vulnerabilidad ya no está, y lo escribió. Es la
 * distancia entre hacer la acción correctiva y verificar su eficacia, que es lo
 * que la 10.2 pide y lo que más se olvida.
 *
 * **Aceptada** es no corregirla a sabiendas, con motivo y con la firma de quien
 * tiene `vulnerabilidades.aceptar`. **Falso positivo** es que no era una
 * vulnerabilidad. Ninguna de las dos se borra: las dos se reabren si hace falta,
 * y las dos tienen que poder explicarse ante un auditor.
 */
#[TypeScript]
enum EstadoVulnerabilidad: string
{
    case Abierta = 'abierta';
    case EnRemediacion = 'en_remediacion';
    case Mitigada = 'mitigada';
    case Cerrada = 'cerrada';
    case Aceptada = 'aceptada';
    case FalsoPositivo = 'falso_positivo';

    /** @return list<self> */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Abierta => [self::EnRemediacion, self::Mitigada, self::Aceptada, self::FalsoPositivo],
            self::EnRemediacion => [self::Mitigada, self::Abierta, self::Aceptada, self::FalsoPositivo],
            self::Mitigada => [self::Cerrada, self::EnRemediacion],
            self::Cerrada, self::Aceptada, self::FalsoPositivo => [self::Abierta],
        };
    }

    public function admite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /** Si todavía corre el plazo de remediación: nadie ha aplicado el arreglo. */
    public function correPlazo(): bool
    {
        return $this === self::Abierta || $this === self::EnRemediacion;
    }

    /** Si queda algo por hacer: todo menos lo cerrado y lo descartado. */
    public function estaViva(): bool
    {
        return ! in_array($this, [self::Cerrada, self::Aceptada, self::FalsoPositivo], true);
    }

    /** Volver atrás necesita motivo: lo que se reabre es algo que alguien dio por hecho. */
    public function exigeMotivoDesde(self $anterior): bool
    {
        return match ($this) {
            self::Aceptada, self::FalsoPositivo => true,
            self::Abierta => $anterior !== self::EnRemediacion,
            self::EnRemediacion => $anterior === self::Mitigada,
            default => false,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Abierta => 'Abierta',
            self::EnRemediacion => 'En remediación',
            self::Mitigada => 'Mitigada',
            self::Cerrada => 'Cerrada',
            self::Aceptada => 'Aceptada',
            self::FalsoPositivo => 'Falso positivo',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Abierta => 'no_iniciado',
            self::EnRemediacion => 'en_progreso',
            self::Mitigada => 'en_revision',
            self::Cerrada => 'implantado',
            self::Aceptada => 'planificado',
            self::FalsoPositivo => 'no_aplica',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Abierta => 'CircleAlert',
            self::EnRemediacion => 'Wrench',
            self::Mitigada => 'Eye',
            self::Cerrada => 'CircleCheck',
            self::Aceptada => 'ShieldAlert',
            self::FalsoPositivo => 'CircleSlash',
        };
    }
}
