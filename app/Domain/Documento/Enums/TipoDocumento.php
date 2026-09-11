<?php

declare(strict_types=1);

namespace App\Domain\Documento\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Qué documento se genera.
 *
 * Las dos declaraciones de aplicabilidad son **dos consultas distintas sobre
 * `implantaciones`**, no dos ficheros que alguien mantiene en paralelo. Lo que
 * las separa no es el formato, es la naturaleza de la pregunta: en ISO la
 * aplicabilidad es una **decisión** que hay que justificar control a control, y
 * en el ENS es un **cálculo** del motor de categorización que hay que poder
 * rastrear hasta la valoración de las cinco dimensiones.
 *
 * De ahí que la SoA lleve dos columnas de justificación y la DdA lleve origen de
 * la exigencia, refuerzo y dimensión moduladora.
 *
 * La lista crecerá —plan de adecuación, acta de la revisión por la dirección,
 * informe de auditoría interna, informe de estado— y por eso el `CHECK` de la
 * tabla enumera valores en vez de usar un tipo enum de PostgreSQL.
 */
#[TypeScript]
enum TipoDocumento: string
{
    case SoaIso = 'soa_iso';
    case DdaEns = 'dda_ens';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SoaIso => 'Declaración de Aplicabilidad (ISO 27001)',
            self::DdaEns => 'Declaración de Aplicabilidad (ENS)',
        };
    }

    /** La forma corta, para las columnas de una tabla y los badges. */
    public function etiquetaCorta(): string
    {
        return match ($this) {
            self::SoaIso => 'SoA',
            self::DdaEns => 'DdA',
        };
    }

    /**
     * El código del marco cuyo sistema puede emitir este documento.
     *
     * Una SoA de un sistema declarado bajo el ENS no es un documento raro, es un
     * documento imposible: sus controles no existen en ese marco.
     */
    public function marcoEsperado(): string
    {
        return match ($this) {
            self::SoaIso => 'ISO27001-2022',
            self::DdaEns => 'ENS-RD311-2022',
        };
    }

    /** La vista Blade que lo pinta. */
    public function plantilla(): string
    {
        return match ($this) {
            self::SoaIso => 'documentos.soa-iso',
            self::DdaEns => 'documentos.dda-ens',
        };
    }

    /** Chip neutro: un tipo de documento no es un estado y no se colorea como tal. */
    public function tono(): string
    {
        return 'marco';
    }
}
