<?php

declare(strict_types=1);

namespace App\Domain\Conformidad\Enums;

/**
 * El ciclo de la conformidad con el ENS de un sistema: § 4.17.
 *
 * Son los tres pasos de categoría básica menos el primero, que ya existía como
 * auditoría: **en preparación** —hay una autoevaluación cerrada que la respalda y
 * la Declaración de Conformidad se está redactando—, **declarada** —el PDF está
 * firmado y emitido— y **publicada** —el distintivo está en la web de la
 * organización—. `Retirada` es la salida de cualquiera de los tres.
 *
 * **`Caducada` no está, y no es un olvido.** Se deriva de `vigente_hasta`
 * (`Conformidad::haCaducado()`): guardarlo obligaría a un comando que lo marcara
 * cada noche, y el día que ese comando fallara, una declaración vencida se
 * enseñaría como vigente, que es justo el fallo que el calendario existe para
 * evitar.
 *
 * **Y de `Retirada` no se vuelve.** Retirar una declaración es decir que ha
 * dejado de respaldar al sistema; resucitarla sería volver a declarar sin una
 * autoevaluación nueva detrás. Lo que se hace es iniciar otra.
 */
enum EstadoConformidad: string
{
    case EnPreparacion = 'en_preparacion';
    case Declarada = 'declarada';
    case Publicada = 'publicada';
    case Retirada = 'retirada';

    /**
     * A qué estados se puede pasar desde éste.
     *
     * Sin atajos: de preparación a publicada no se salta, porque publicar el
     * distintivo sin una declaración firmada detrás es exactamente lo que el
     * CCN-STIC 809 prohíbe.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::EnPreparacion => [self::Declarada, self::Retirada],
            self::Declarada => [self::Publicada, self::Retirada],
            self::Publicada => [self::Retirada],
            self::Retirada => [],
        };
    }

    public function permite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /** Si hay una declaración firmada detrás. */
    public function estaDeclarada(): bool
    {
        return $this === self::Declarada || $this === self::Publicada;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnPreparacion => 'En preparación',
            self::Declarada => 'Declarada',
            self::Publicada => 'Distintivo publicado',
            self::Retirada => 'Retirada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::EnPreparacion => 'CircleDotDashed',
            self::Declarada => 'FileCheck',
            self::Publicada => 'BadgeCheck',
            self::Retirada => 'Archive',
        };
    }

    /**
     * **Ninguno gasta rojo.** El rojo es de la declaración caducada, que no es un
     * estado sino una fecha pasada: lo pone `Conformidad::tono()`. Una retirada
     * es una decisión con su motivo en el histórico, no un fallo.
     */
    public function tono(): string
    {
        return match ($this) {
            self::EnPreparacion => 'en_progreso',
            self::Declarada, self::Publicada => 'implantado',
            self::Retirada => 'no_aplica',
        };
    }
}
