<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Documento\Enums\TipoDocumento;
use Illuminate\Contracts\Container\Container;

/**
 * Qué clase pinta cada tipo de documento.
 *
 * Un `match` y no un array de configuración: así cada tipo nuevo lo señala
 * PHPStan en vez de dejar que reviente en ejecución delante de quien pulsó
 * «Generar». Ha avisado ya con el plan de adecuación, con el análisis del
 * contexto y con el acta de revisión.
 */
final readonly class RegistroGeneradores
{
    public function __construct(private Container $contenedor) {}

    public function para(TipoDocumento $tipo): GeneradorDocumento
    {
        return $this->contenedor->make(match ($tipo) {
            TipoDocumento::SoaIso => DeclaracionAplicabilidadIso::class,
            TipoDocumento::DdaEns => DeclaracionAplicabilidadEns::class,
            TipoDocumento::PlanAdecuacionEns => PlanAdecuacionEns::class,
            TipoDocumento::AnalisisContexto => AnalisisDelContexto::class,
            TipoDocumento::ActaRevision => ActaRevisionDireccion::class,

            /*
             * Los tres redactados comparten generador: lo que los separa es qué
             * dicen y en qué nivel de la jerarquía están, no cómo se producen.
             * En la tubería son el mismo documento.
             */
            TipoDocumento::Politica,
            TipoDocumento::Norma,
            TipoDocumento::Procedimiento,
            TipoDocumento::PlanContinuidad => DocumentoRedactado::class,
        });
    }
}
