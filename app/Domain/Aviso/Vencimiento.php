<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

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
 *
 * Lo leen dos sitios: el resumen diario que sale por correo y el calendario. Que
 * sea el mismo objeto es lo que garantiza que digan lo mismo — y es la primera
 * pieza del calendario de obligaciones de § 4.16, donde irán colgando el resto
 * de lo periódico.
 */
#[TypeScript]
final readonly class Vencimiento
{
    /**
     * La ficha de lo que vence.
     *
     * Es una propiedad y no un método porque esto viaja a Inertia, que serializa
     * lo público y no llama a nada. Y la decide `Fuente` en vez de un mapa
     * repetido en el cliente: el día que llegue la sexta fuente de § 4.16, la
     * pantalla no tiene que enterarse.
     */
    public string $url;

    /** El icono con el que se distingue de un vistazo. Mismo motivo. */
    public string $icono;

    public function __construct(
        public int $id,
        public Fuente $fuente,
        public string $titulo,
        /** En `Y-m-d`: es lo que agrupa por día y lo que viaja al cliente. */
        public string $dia,
        public string $fecha,
        /** Días hasta la fecha. Negativo si ya pasó. */
        public int $dias,
        public ?string $responsable,
        /** El tono del dominio con el que se pinta. Nunca un color. */
        public string $tono,
    ) {
        $this->url = $fuente->url($id);
        $this->icono = $fuente->icono();
    }

    public function pasado(): bool
    {
        return $this->dias < 0;
    }

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
