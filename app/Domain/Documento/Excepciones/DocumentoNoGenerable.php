<?php

declare(strict_types=1);

namespace App\Domain\Documento\Excepciones;

use RuntimeException;

/**
 * Falta algo fuera del documento sin lo cual no hay nada que imprimir.
 *
 * No es lo mismo que `GeneracionFallida`, que es cuando algo se rompió por el
 * camino: aquí la generación no se intenta siquiera, porque el dato que el
 * documento tiene que enseñar todavía no existe.
 */
final class DocumentoNoGenerable extends RuntimeException
{
    /**
     * Un análisis del contexto se imprime desde la revisión **aprobada**.
     *
     * Generarlo desde el borrador sería entregar un contexto que nadie ha firmado,
     * y además uno que todavía puede cambiar: la instantánea —que es de donde sale
     * todo lo que se imprime— se escribe al aprobar.
     */
    public static function sinContextoAprobado(): self
    {
        return new self(
            'No hay ningún análisis del contexto aprobado. Apruébalo antes de generar el documento: '
            .'lo que se imprime es lo que quedó congelado al firmarlo.'
        );
    }

    /**
     * El mismo caso en la revisión por la dirección: sin acta firmada no hay
     * documento que entregar.
     */
    public static function sinRevisionAprobada(): self
    {
        return new self(
            'No hay ninguna revisión por la dirección con el acta aprobada. Apruébala antes de generar el '
            .'documento: lo que se imprime son las siete entradas tal como quedaron congeladas al firmarla.'
        );
    }
}
