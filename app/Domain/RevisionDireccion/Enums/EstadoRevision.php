<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion\Enums;

/**
 * El ciclo de una revisión por la dirección. Cláusula 9.3.
 *
 * **Tres estados, calcados de `EstadoAuditoria`**, y la comparación no es casual:
 * las dos son un acto que ocurre en una fecha, que se prepara antes y que se
 * congela al terminar. `Planificada` es que está convocada; `EnCurso`, que se está
 * celebrando y se recogen las entradas; `Aprobada`, que el acta está firmada.
 *
 * **Aprobar es lo que congela**, y es el único gesto que importa del módulo: a
 * partir de ahí las siete entradas de la 9.3.2 quedan selladas en la instantánea y
 * el trigger de PostgreSQL vuelve la fila inmutable. Sin ese sellado, el acta de
 * marzo enseñaría las cifras de octubre.
 *
 * **No hay estado `obsoleta`**, a diferencia del análisis del contexto: allí hay
 * una vigente porque el contexto es un estado de cosas que se sustituye, y aquí
 * cada revisión es un **acto** con su fecha. La del año pasado no deja de haber
 * ocurrido porque se celebre la de este año.
 *
 * Y no hay `cancelada`: una revisión que se convoca y no se celebra se borra
 * mientras siga planificada, porque no hay nada que dejar constancia de. En cuanto
 * empieza, ya hay algo que contar.
 */
enum EstadoRevision: string
{
    case Planificada = 'planificada';
    case EnCurso = 'en_curso';
    case Aprobada = 'aprobada';

    /**
     * A qué estados se puede pasar desde éste.
     *
     * De `Aprobada` se vuelve a `EnCurso` —un acta firmada con un error tiene que
     * poder corregirse— y **nunca a `Planificada`**, que sería decir que la
     * reunión no se celebró. Es la puerta exacta de `EstadoAuditoria`, y la misma
     * que talla el trigger de inmutabilidad.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Planificada => [self::EnCurso],
            self::EnCurso => [self::Aprobada, self::Planificada],
            self::Aprobada => [self::EnCurso],
        };
    }

    public function permite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /** Si la revisión admite cambios: entradas, asistentes y conclusiones. */
    public function admiteCambios(): bool
    {
        return $this !== self::Aprobada;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Planificada => 'Planificada',
            self::EnCurso => 'En curso',
            self::Aprobada => 'Aprobada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Planificada => 'CalendarClock',
            self::EnCurso => 'Users',
            // El sello, no el visto: lo que se firma es un acta.
            self::Aprobada => 'BadgeCheck',
        };
    }

    /**
     * **`EnCurso` gasta el violeta de `en_revision`**, y es el cuarto badge que lo
     * hace. DESIGN.md le reserva ese acento a los flujos de **revisión y
     * auditoría**, y una revisión por la dirección es literalmente los dos: no hay
     * en todo el producto un estado que encaje mejor con ese token.
     *
     * Ninguno gasta rojo. Que una revisión esté planificada y aún sin celebrar no
     * va mal: va según lo previsto. Lo que sí irá en rojo cuando llegue el § 4.16
     * es la revisión **vencida**, y ésa es una cifra del calendario y no un estado.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Planificada => 'planificado',
            self::EnCurso => 'en_revision',
            self::Aprobada => 'implantado',
        };
    }
}
