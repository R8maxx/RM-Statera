<?php

declare(strict_types=1);

namespace App\Domain\Documento\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El ciclo de vida del TRABAJO que produce el PDF.
 *
 * No es el flujo de aprobación del documento. El nombre `estado` se deja libre a
 * propósito para `borrador|en_revision|aprobado|obsoleto`, que es otra cosa y
 * llega con el § 4.5: confundirlos haría que «generada» pareciera «aprobada», y
 * una versión generada no la ha firmado nadie.
 */
#[TypeScript]
enum EstadoGeneracion: string
{
    case Encolada = 'encolada';
    case Generando = 'generando';
    case Generada = 'generada';
    case Fallida = 'fallida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Encolada => 'En cola',
            self::Generando => 'Generando…',
            self::Generada => 'Lista',
            self::Fallida => 'Falló',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Encolada => 'planificado',
            self::Generando => 'en_progreso',
            self::Generada => 'implantado',
            self::Fallida => 'caducada',
        };
    }

    /**
     * Si el trabajo sigue vivo.
     *
     * Es lo que decide si la ficha tiene que seguir preguntando al servidor, así
     * que la pregunta se contesta aquí una vez y no en cada sitio que la
     * necesite.
     */
    public function enCurso(): bool
    {
        return $this === self::Encolada || $this === self::Generando;
    }
}
