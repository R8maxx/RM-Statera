<?php

declare(strict_types=1);

namespace App\Domain\Documento\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Qué documento se genera.
 *
 * Hay dos familias y no se parecen en nada.
 *
 * **Las declaraciones de aplicabilidad se calculan.** Son **dos consultas
 * distintas sobre `implantaciones`**, no dos ficheros que alguien mantiene en
 * paralelo. Lo que las separa no es el formato, es la naturaleza de la pregunta:
 * en ISO la aplicabilidad es una **decisión** que hay que justificar control a
 * control, y en el ENS es un **cálculo** del motor de categorización que hay que
 * poder rastrear hasta la valoración de las cinco dimensiones. De ahí que la SoA
 * lleve dos columnas de justificación y la DdA lleve origen de la exigencia,
 * refuerzo y dimensión moduladora.
 *
 * **Los documentos redactados los escribe la organización.** Una política no se
 * deriva de ninguna tabla: la redacta alguien y la firma la dirección. Son los
 * tres niveles de la jerarquía del § 4.5 —política → norma → procedimiento— y
 * son los que dan sentido al acuse de lectura, que es lo que piden la cláusula
 * 7.3 de ISO y `org.2` del ENS. No cuelgan de un sistema: una política de
 * seguridad es de la organización entera.
 *
 * El cuarto nivel de esa jerarquía —el **registro**— no entra: un registro es la
 * salida de un procedimiento, no un documento que Statera redacte y versione.
 *
 * La lista seguirá creciendo —plan de adecuación, acta de la revisión por la
 * dirección, informe de auditoría interna, informe de estado— y por eso el
 * `CHECK` de la tabla enumera valores en vez de usar un tipo enum de PostgreSQL.
 */
#[TypeScript]
enum TipoDocumento: string
{
    case SoaIso = 'soa_iso';
    case DdaEns = 'dda_ens';

    case Politica = 'politica';
    case Norma = 'norma';
    case Procedimiento = 'procedimiento';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SoaIso => 'Declaración de Aplicabilidad (ISO 27001)',
            self::DdaEns => 'Declaración de Aplicabilidad (ENS)',
            self::Politica => 'Política',
            self::Norma => 'Norma',
            self::Procedimiento => 'Procedimiento',
        };
    }

    /** La forma corta, para las columnas de una tabla y los badges. */
    public function etiquetaCorta(): string
    {
        return match ($this) {
            self::SoaIso => 'SoA',
            self::DdaEns => 'DdA',
            self::Politica => 'Política',
            self::Norma => 'Norma',
            self::Procedimiento => 'Procedimiento',
        };
    }

    /**
     * Si el contenido lo escribe la organización en vez de calcularlo el motor.
     *
     * Es la frontera que decide casi todo lo demás: un documento redactado no
     * lleva tabla de requisitos, no exige sistema, no tiene marco que casar y sus
     * huecos narrativos son otros.
     */
    public function esRedactado(): bool
    {
        return match ($this) {
            self::SoaIso, self::DdaEns => false,
            self::Politica, self::Norma, self::Procedimiento => true,
        };
    }

    /**
     * El código del marco cuyo sistema puede emitir este documento.
     *
     * Una SoA de un sistema declarado bajo el ENS no es un documento raro, es un
     * documento imposible: sus controles no existen en ese marco.
     *
     * **Nulo en los documentos redactados**, y no por descuido: una política no
     * declara conformidad con un marco concreto, así que exigirle uno sería
     * inventarse una restricción. Quien lo consuma tiene que tratar el nulo como
     * «no hay nada que casar», nunca como «no se ha rellenado».
     */
    public function marcoEsperado(): ?string
    {
        return match ($this) {
            self::SoaIso => 'ISO27001-2022',
            self::DdaEns => 'ENS-RD311-2022',
            self::Politica, self::Norma, self::Procedimiento => null,
        };
    }

    /*
     * Aquí había un `plantilla()` que devolvía la Blade de cada tipo. Ya no hay
     * una por tipo: el documento entero sale de `RenderizadorCuerpo` sobre el
     * cuerpo editable, y `documentos.layout` es el único Blade que queda. Lo
     * que cambia entre la SoA y la DdA vive en `CuerpoDeFabrica` y en
     * `ColumnasTabla`, que es donde se puede leer de un vistazo.
     */

    /** Chip neutro: un tipo de documento no es un estado y no se colorea como tal. */
    public function tono(): string
    {
        return 'marco';
    }
}
