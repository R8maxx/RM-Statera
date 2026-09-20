<?php

declare(strict_types=1);

namespace App\Domain\Persona\Enums;

/**
 * Si un paso de la checklist es de incorporación o de salida.
 *
 * Son dos listas y no una con un campo: lo que hay que hacer al entrar —entregar
 * el equipo, firmar el acuerdo, dar de alta las cuentas— y lo que hay que hacer al
 * salir —recuperar el equipo, revocar los accesos, recordar que el deber de
 * confidencialidad sigue vivo— no se parecen en nada, y **la de baja es la que el
 * auditor mira**: un acceso que nadie revocó es el hallazgo clásico.
 */
enum TipoPasoPersona: string
{
    case Alta = 'alta';
    case Baja = 'baja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Alta => 'Incorporación',
            self::Baja => 'Salida',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Alta => 'UserCheck',
            self::Baja => 'Archive',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Alta => 'planificado',
            self::Baja => 'en_progreso',
        };
    }
}
