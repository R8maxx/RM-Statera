<?php

declare(strict_types=1);

namespace App\Domain\Riesgo\Enums;

/**
 * Las cuatro familias en las que MAGERIT reparte su catálogo de amenazas.
 *
 * Van en un enum y no en el catálogo porque **son la estructura del catálogo, no
 * su contenido**: las amenazas concretas cambian de una revisión a otra y por eso
 * viven en `catalogo/magerit-amenazas.yaml` (invariante 3), pero que haya cuatro
 * grupos y cuáles son es lo que da forma a la tabla y al `CHECK` que la protege.
 * Mismo reparto que en el catálogo normativo: `TipoRequisito` es un enum y los
 * requisitos son datos.
 *
 * El orden es el del propio catálogo —de lo que no tiene autor a lo que lo tiene
 * y quiere hacer daño— y se conserva porque es el orden en el que se revisa un
 * análisis: nadie empieza por los ataques.
 */
enum GrupoAmenaza: string
{
    case DesastresNaturales = 'desastres_naturales';
    case OrigenIndustrial = 'origen_industrial';
    case ErroresNoIntencionados = 'errores_no_intencionados';
    case AtaquesIntencionados = 'ataques_intencionados';

    /** El prefijo del código en el catálogo: `N.1`, `I.5`, `E.8`, `A.11`. */
    public function prefijo(): string
    {
        return match ($this) {
            self::DesastresNaturales => 'N',
            self::OrigenIndustrial => 'I',
            self::ErroresNoIntencionados => 'E',
            self::AtaquesIntencionados => 'A',
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::DesastresNaturales => 'Desastres naturales',
            self::OrigenIndustrial => 'De origen industrial',
            self::ErroresNoIntencionados => 'Errores y fallos no intencionados',
            self::AtaquesIntencionados => 'Ataques intencionados',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::DesastresNaturales => 'CloudLightning',
            self::OrigenIndustrial => 'Factory',
            self::ErroresNoIntencionados => 'CircleAlert',
            self::AtaquesIntencionados => 'Swords',
        };
    }

    /**
     * El tono con el que se agrupa en una lista.
     *
     * Una amenaza no tiene estado, así que aquí el color **agrupa y no gradúa**:
     * es el mismo criterio que la tipología de activos, y por eso se apoya en esa
     * familia de tokens en lugar de en la de estados. Un badge de amenaza en el
     * verde de `implantado` se leería como que algo va bien.
     */
    public function tono(): string
    {
        return match ($this) {
            self::DesastresNaturales => 'tipo:instalaciones',
            self::OrigenIndustrial => 'tipo:equipamiento_auxiliar',
            self::ErroresNoIntencionados => 'tipo:personal',
            self::AtaquesIntencionados => 'tipo:software',
        };
    }

    /** El prefijo de un código del catálogo, resuelto a su grupo. */
    public static function deCodigo(string $codigo): ?self
    {
        $prefijo = strtoupper(strtok($codigo, '.') ?: '');

        foreach (self::cases() as $grupo) {
            if ($grupo->prefijo() === $prefijo) {
                return $grupo;
            }
        }

        return null;
    }
}
