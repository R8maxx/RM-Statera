<?php

declare(strict_types=1);

namespace App\Domain\Documento\Excepciones;

use RuntimeException;
use Throwable;

/**
 * La generación del PDF no salió.
 *
 * El mensaje acaba tal cual en `documento_versiones.error` y de ahí en la ficha,
 * así que tiene que servirle a quien lo lea: un fallo sin motivo legible obliga
 * a abrir los logs de Horizon, y para entonces ya nadie mira.
 */
final class GeneracionFallida extends RuntimeException
{
    public static function porGotenberg(string $detalle, ?Throwable $anterior = null): self
    {
        return new self("Gotenberg no pudo generar el PDF: {$detalle}", previous: $anterior);
    }

    public static function porAlmacenamiento(string $ruta): self
    {
        return new self("El PDF se generó pero no se pudo almacenar íntegro en «{$ruta}».");
    }
}
