<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Enums;

/**
 * Tres estados, y el tercero es una frontera de verdad.
 *
 * `Cerrada` no es «ya está»: es el punto a partir del cual **la base deja de
 * admitir cambios** en la checklist y en los hallazgos. Esa es la razón de que el
 * estado exista, y no llevar la cuenta de por dónde va el trabajo.
 *
 * **De cerrada se puede volver a `en_curso`**, y hace falta: un error material en
 * una auditoría cerrada tiene que poder corregirse. Lo que no puede es corregirse
 * a escondidas, y por eso reabrir es una transición con su fecha y su autor y no
 * una edición silenciosa. Es la misma puerta que `vigente` en las valoraciones de
 * riesgo y `obsoleto` en las versiones de documento.
 *
 * **A `planificada` no se vuelve nunca**: decir que una auditoría que ya se hizo
 * está por hacer es reescribir el pasado, y ésa es justamente la puerta que estos
 * tres enums no abren.
 */
enum EstadoAuditoria: string
{
    case Planificada = 'planificada';
    case EnCurso = 'en_curso';
    case Cerrada = 'cerrada';

    /** @return list<self> */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Planificada => [self::EnCurso],
            self::EnCurso => [self::Cerrada],
            // Reabrir, y sólo eso. El trigger de la base lo comprueba otra vez.
            self::Cerrada => [self::EnCurso],
        };
    }

    public function permite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /** Si la base admite todavía cambios en su checklist y sus hallazgos. */
    public function admiteCambios(): bool
    {
        return $this !== self::Cerrada;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Planificada => 'Planificada',
            self::EnCurso => 'En curso',
            self::Cerrada => 'Cerrada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Planificada => 'CalendarClock',
            self::EnCurso => 'CircleDotDashed',
            self::Cerrada => 'CircleCheck',
        };
    }

    /**
     * El vocabulario de siempre: azul lo planificado, ámbar lo que está en
     * marcha, verde lo terminado. **Ninguno gasta rojo**: una auditoría no va mal
     * por estar abierta, y lo que va mal de verdad son sus hallazgos.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Planificada => 'planificado',
            self::EnCurso => 'en_progreso',
            self::Cerrada => 'implantado',
        };
    }
}
