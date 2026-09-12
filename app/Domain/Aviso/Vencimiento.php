<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

/**
 * Algo con fecha que se acerca o que ya pasó.
 *
 * Una evidencia a punto de dejar de probar nada y una tarea a punto de vencer
 * son la misma forma con distinto nombre, y el correo las lee igual: qué es,
 * cuándo, y de quién era.
 *
 * Escalares y nada más. Viaja dentro de una notificación en cola, y una
 * notificación que lleve modelos de Eloquent los reconsulta al deserializarse
 * —antes de cualquier middleware, sin contexto de organización— y muere con un
 * error que no menciona la palabra «organización».
 */
final readonly class Vencimiento
{
    public function __construct(
        public int $id,
        public string $titulo,
        public string $fecha,
        /** Días hasta la fecha. Negativo si ya pasó. */
        public int $dias,
        public ?string $responsable,
    ) {}

    /** «caducó hace 4 días» / «vence en 12 días», que es lo que se lee de un vistazo. */
    public function cuando(string $verbo = 'vence', string $pasado = 'venció'): string
    {
        return match (true) {
            $this->dias < 0 => $pasado.' hace '.abs($this->dias).' '.($this->dias === -1 ? 'día' : 'días'),
            $this->dias === 0 => $verbo.' hoy',
            default => $verbo.' en '.$this->dias.' '.($this->dias === 1 ? 'día' : 'días'),
        };
    }
}
