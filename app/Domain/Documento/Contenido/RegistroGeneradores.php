<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Documento\Enums\TipoDocumento;
use Illuminate\Contracts\Container\Container;

/**
 * Qué clase pinta cada tipo de documento.
 *
 * Un `match` y no un array de configuración: así el día que se añada el plan de
 * adecuación, PHPStan señala el caso que falta en vez de dejar que reviente en
 * ejecución delante de quien pulsó «Generar».
 */
final readonly class RegistroGeneradores
{
    public function __construct(private Container $contenedor) {}

    public function para(TipoDocumento $tipo): GeneradorDocumento
    {
        return $this->contenedor->make(match ($tipo) {
            TipoDocumento::SoaIso => DeclaracionAplicabilidadIso::class,
            TipoDocumento::DdaEns => DeclaracionAplicabilidadEns::class,
        });
    }
}
