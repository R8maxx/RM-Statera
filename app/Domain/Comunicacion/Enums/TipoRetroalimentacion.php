<?php

declare(strict_types=1);

namespace App\Domain\Comunicacion\Enums;

/**
 * Qué es lo recibido: la retroalimentación que pide la 9.3.2 e).
 *
 * La norma la ilustra con quejas, encuestas de satisfacción y comentarios, y esa
 * es la lista. **Ninguno gasta rojo**, tampoco la queja: una queja registrada es
 * una organización que escucha, y pintarla de alarma enseñaría a no apuntarla —
 * el quinto principio del producto.
 */
enum TipoRetroalimentacion: string
{
    case Queja = 'queja';
    case Sugerencia = 'sugerencia';
    case Consulta = 'consulta';
    case Encuesta = 'encuesta';
    case Felicitacion = 'felicitacion';
    case Otra = 'otra';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Queja => 'Queja',
            self::Sugerencia => 'Sugerencia',
            self::Consulta => 'Consulta',
            self::Encuesta => 'Resultado de encuesta',
            self::Felicitacion => 'Felicitación',
            self::Otra => 'Otra',
        };
    }
}
