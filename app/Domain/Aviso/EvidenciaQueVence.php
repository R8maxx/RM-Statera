<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

/**
 * Una evidencia a punto de dejar de probar nada, o que ya no prueba nada.
 *
 * Escalares y nada más. Viaja dentro de una notificación en cola, y una
 * notificación que lleve modelos de Eloquent los reconsulta al deserializarse
 * —antes de cualquier middleware, sin contexto de organización— y muere con un
 * error que no menciona la palabra «organización».
 */
final readonly class EvidenciaQueVence
{
    public function __construct(
        public int $id,
        public string $titulo,
        public string $fecha,
        /** Días hasta la caducidad. Negativo si ya pasó. */
        public int $dias,
        public ?string $responsable,
    ) {}

    /** «hace 4 días» / «en 12 días» / «hoy», que es lo que se lee de un vistazo. */
    public function cuando(): string
    {
        return match (true) {
            $this->dias < 0 => 'caducó hace '.abs($this->dias).' '.($this->dias === -1 ? 'día' : 'días'),
            $this->dias === 0 => 'caduca hoy',
            default => 'caduca en '.$this->dias.' '.($this->dias === 1 ? 'día' : 'días'),
        };
    }
}
