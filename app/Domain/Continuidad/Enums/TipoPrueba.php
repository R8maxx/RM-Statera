<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Enums;

/**
 * Cómo se comprueba un plan de continuidad: § 4.11 y `op.cont.3`.
 *
 * Cuatro formas, de menos a más costosas de montar. **No hay una obligatoria
 * ni un orden que recorrer**: la elige quien planifica la prueba según lo que
 * el servicio se pueda permitir interrumpir. Un tipo dice **qué se hizo**, no
 * **cómo fue**, así que va en el gris neutro de procedencia —el mismo criterio
 * que `TipoAuditoria::tono()`— y no en la paleta de estados.
 */
enum TipoPrueba: string
{
    case Sobremesa = 'sobremesa';
    case Simulacro = 'simulacro';
    case Tecnica = 'tecnica';
    case Completa = 'completa';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Sobremesa => 'Ejercicio de sobremesa',
            self::Simulacro => 'Simulacro',
            self::Tecnica => 'Prueba técnica',
            self::Completa => 'Prueba completa',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Sobremesa => 'Se recorre el plan en una reunión, sin tocar ningún sistema.',
            self::Simulacro => 'Se simula el incidente y se ejecutan los pasos, sin cortar el servicio real.',
            self::Tecnica => 'Se comprueba una pieza concreta: un restore, un failover, una copia.',
            self::Completa => 'Se interrumpe el servicio real y se recupera siguiendo el plan.',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Sobremesa => 'MessageCircle',
            self::Simulacro => 'Users',
            self::Tecnica => 'Wrench',
            self::Completa => 'Target',
        };
    }

    /** Un tipo dice qué se hizo, no cómo fue: gris neutro de procedencia. */
    public function tono(): string
    {
        return 'marco';
    }
}
