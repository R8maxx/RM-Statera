<?php

declare(strict_types=1);

namespace App\Domain\Incidente\Enums;

/**
 * El ciclo de un incidente: `op.exp.7`.
 *
 * Cuatro estados, y el que cuesta explicar es el tercero. **`resuelto` no es
 * `cerrado`**: resuelto es que el servicio está restablecido —que es lo que
 * preocupa a las tres de la mañana— y cerrado es que además se ha escrito qué se
 * aprendió. Con un solo estado final, la lección aprendida se queda sin escribir
 * el día que el servicio vuelve, que es exactamente cuando todo el mundo se va a
 * dormir.
 *
 * Es el mismo reparto que `EstadoNoConformidad::Cerrada` frente a `Verificada`, y
 * por el mismo motivo: el paso que la norma pide y que todo el mundo se salta se
 * merece un estado y no una casilla.
 *
 * **Ningún estado gasta rojo**, como en tareas, no conformidades, objetivos y
 * mejoras. Un incidente abierto no va mal: va siendo atendido, que es lo que se
 * espera. El único rojo del módulo es el plazo de la AEPD vencido sin notificar.
 */
enum EstadoIncidente: string
{
    case Abierto = 'abierto';
    case EnTratamiento = 'en_tratamiento';
    case Resuelto = 'resuelto';
    case Cerrado = 'cerrado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::EnTratamiento => 'En tratamiento',
            self::Resuelto => 'Resuelto',
            self::Cerrado => 'Cerrado',
        };
    }

    /**
     * El tono. `Resuelto` gasta el violeta de `en_revision` —el cuarto badge del
     * producto que lo hace, junto a la versión esperando firma, la no conformidad
     * pendiente de verificar y el objetivo propuesto— y significa lo mismo que en
     * los otros tres: hecho y a la espera de que alguien lo confirme. Aquí lo que
     * falta confirmar es qué se aprendió.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Abierto => 'no_iniciado',
            self::EnTratamiento => 'en_progreso',
            self::Resuelto => 'en_revision',
            self::Cerrado => 'implantado',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Abierto => 'CircleAlert',
            self::EnTratamiento => 'Wrench',
            self::Resuelto => 'CircleDotDashed',
            self::Cerrado => 'CircleCheck',
        };
    }

    public function esCerrado(): bool
    {
        return $this === self::Cerrado;
    }

    /**
     * A dónde se puede ir desde aquí.
     *
     * **De `cerrado` se vuelve a `resuelto` y nunca a `abierto`**, misma puerta
     * que tienen la auditoría cerrada y la revisión por la dirección aprobada:
     * reabrir para corregir la lección aprendida es legítimo; decir que el
     * incidente nunca se resolvió es reescribir el pasado.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Abierto => [self::EnTratamiento, self::Resuelto],
            self::EnTratamiento => [self::Resuelto, self::Abierto],
            self::Resuelto => [self::Cerrado, self::EnTratamiento],
            self::Cerrado => [self::Resuelto],
        };
    }

    public function admite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }
}
