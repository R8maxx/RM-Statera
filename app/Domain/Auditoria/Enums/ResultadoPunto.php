<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Enums;

/**
 * Qué dijo el auditor de una medida concreta.
 *
 * **`Pendiente` no es `Conforme`, y ésa es la razón entera de que la checklist
 * exista.** Sin ella, la ausencia de hallazgo se lee como conformidad, y una
 * auditoría por muestreo —que son todas— pasaría a afirmar cosas sobre las
 * medidas que nadie miró. Es literalmente el argumento de
 * `EstadoControl::PorConfirmar`: la ausencia de dato es una pregunta abierta y se
 * cuenta aparte.
 *
 * **Y `FueraDeMuestra` no es «no aplica».** Aquí no hay «no aplica»: la checklist
 * se precarga desde lo que el motor declara exigible, así que todo punto es
 * aplicable **por construcción**. Un auditor marcando «no aplica» estaría
 * contradiciendo una derivación legal desde un desplegable, que es exactamente lo
 * que prohíbe el invariante 4. Lo que sí necesita decir es que esa medida quedó
 * fuera del muestreo, que es una decisión suya sobre el alcance y no sobre la
 * aplicabilidad.
 */
enum ResultadoPunto: string
{
    case Pendiente = 'pendiente';
    case Conforme = 'conforme';
    case NoConforme = 'no_conforme';
    case Observacion = 'observacion';
    case FueraDeMuestra = 'fuera_de_muestra';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Sin revisar',
            self::Conforme => 'Conforme',
            self::NoConforme => 'No conforme',
            self::Observacion => 'Observación',
            self::FueraDeMuestra => 'Fuera de muestra',
        };
    }

    /** Si el auditor ya dijo algo de esta medida. Es el denominador del avance. */
    public function estaRevisado(): bool
    {
        return $this !== self::Pendiente;
    }

    /** Si el resultado pide un hallazgo detrás que lo explique. */
    public function exigeHallazgo(): bool
    {
        return $this === self::NoConforme || $this === self::Observacion;
    }

    public function icono(): string
    {
        return match ($this) {
            self::Pendiente => 'Circle',
            self::Conforme => 'CircleCheck',
            self::NoConforme => 'CircleX',
            self::Observacion => 'MessageSquareWarning',
            self::FueraDeMuestra => 'CircleSlash',
        };
    }

    /**
     * **`NoConforme` es roja, y es un dueño nuevo del rojo.**
     *
     * La regla de `DESIGN.md` es «rojo para lo que va mal de verdad, no para lo
     * que es grande». Una medida que un auditor declara no conforme es el caso
     * más limpio que hay: no es un plazo que se pasó ni una estimación alta, es un
     * incumplimiento comprobado por alguien cuyo trabajo es comprobarlo.
     *
     * `Observacion` va en ámbar —hay algo que mirar, no algo que incumple— y
     * `FueraDeMuestra` en el gris de lo que no cuenta. `Pendiente` se queda en el
     * otro gris, y lo que los separa es el icono, como ya pasa con
     * `no_iniciado`/`no_aplica`.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Pendiente => 'no_iniciado',
            self::Conforme => 'implantado',
            self::NoConforme => 'caducada',
            self::Observacion => 'en_progreso',
            self::FueraDeMuestra => 'no_aplica',
        };
    }
}
