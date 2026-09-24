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

    /**
     * Una Declaración de Conformidad se imprime desde la declaración iniciada del
     * sistema, que es la que fija la categoría y la autoevaluación.
     */
    public static function sinDeclaracionIniciada(): self
    {
        return new self(
            'Este sistema no tiene ninguna declaración de conformidad iniciada. Iníciala desde la ficha de '
            .'conformidad: es la que fija la categoría y la autoevaluación que el documento declara.'
        );
    }

    /**
     * Un informe de auditoría sin auditoría detrás.
     *
     * El `CHECK` `documentos_auditoria_check` lo hace imposible en la base; esto
     * existe para que un dato roto se explique en vez de reventar con un «call to
     * a member function on null».
     */
    public static function sinAuditoria(): self
    {
        return new self(
            'Este informe no está vinculado a ninguna auditoría. Prepáralo desde la ficha de la auditoría cerrada.'
        );
    }

    /**
     * El informe se imprime desde una auditoría **cerrada**: es el cierre lo que
     * congela la checklist y los hallazgos. Si se reabrió después de preparar el
     * informe, hay que volver a cerrarla.
     */
    public static function auditoriaSinCerrar(string $codigo): self
    {
        return new self(sprintf(
            'La auditoría %s está abierta. El informe recoge lo que quedó congelado al cerrarla: vuelve a cerrarla antes de generarlo.',
            $codigo,
        ));
    }
}
