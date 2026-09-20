<?php

declare(strict_types=1);

namespace App\Domain\Documento\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Qué documento se genera.
 *
 * Hay dos familias y no se parecen en nada.
 *
 * **Los documentos calculados salen de una consulta sobre `implantaciones`**, no
 * de ficheros que alguien mantiene en paralelo. Lo que separa a las dos
 * declaraciones no es el formato, es la naturaleza de la pregunta: en ISO la
 * aplicabilidad es una **decisión** que hay que justificar control a control, y
 * en el ENS es un **cálculo** del motor de categorización que hay que poder
 * rastrear hasta la valoración de las cinco dimensiones. De ahí que la SoA lleve
 * dos columnas de justificación y la DdA lleve origen de la exigencia, refuerzo
 * y dimensión moduladora.
 *
 * **El plan de adecuación es el tercero, y hace la pregunta contraria.** Una
 * declaración dice qué se exige y cómo está; un plan lista sólo **lo que falta**,
 * con quién lo lleva, para cuándo y cuánto cuesta. Por eso no hereda las cifras
 * de las declaraciones: sobre filas que son todas pendientes, «porcentaje
 * implantado» daría siempre cero.
 *
 * **El análisis del contexto es el cuarto calculado, y rompe una equivalencia que
 * hasta ahora se daba por buena.** Sale de una consulta —el DAFO y las partes
 * interesadas congelados al aprobar la revisión— y por tanto no es redactado; pero
 * **no cuelga de un sistema**, porque las cuestiones internas y externas y las
 * partes interesadas son de la organización entera. Hasta aquí «calculado» y
 * «exige sistema» eran lo mismo, y de ahí sale `exigeSistema()`.
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
 * La lista seguirá creciendo —informe de auditoría interna, informe de estado,
 * Declaración de Conformidad del ENS— y por eso el `CHECK` de la tabla enumera
 * valores en vez de usar un tipo enum de PostgreSQL. El acta de la revisión por
 * la dirección, que llevaba en esta lista desde el principio, ya está.
 */
#[TypeScript]
enum TipoDocumento: string
{
    case SoaIso = 'soa_iso';
    case DdaEns = 'dda_ens';
    case PlanAdecuacionEns = 'plan_adecuacion_ens';
    case AnalisisContexto = 'analisis_contexto';
    case ActaRevision = 'acta_revision';

    case Politica = 'politica';
    case Norma = 'norma';
    case Procedimiento = 'procedimiento';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SoaIso => 'Declaración de Aplicabilidad (ISO 27001)',
            self::DdaEns => 'Declaración de Aplicabilidad (ENS)',
            self::PlanAdecuacionEns => 'Plan de adecuación (ENS)',
            self::AnalisisContexto => 'Análisis del contexto de la organización',
            self::ActaRevision => 'Acta de revisión por la dirección',
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
            self::PlanAdecuacionEns => 'Plan ENS',
            self::AnalisisContexto => 'Contexto',
            self::ActaRevision => 'Acta',
            self::Politica => 'Política',
            self::Norma => 'Norma',
            self::Procedimiento => 'Procedimiento',
        };
    }

    /**
     * Si el contenido lo escribe la organización en vez de calcularlo el motor.
     *
     * Es la frontera que decide casi todo lo demás: un documento redactado no
     * lleva tabla de requisitos, no tiene marco que casar y sus huecos narrativos
     * son otros.
     *
     * **Lo que ya no decide es si exige sistema.** Lo decidió hasta el análisis del
     * contexto, porque hasta entonces los tres calculados eran de un sistema y los
     * tres redactados de la organización; esa coincidencia se rompió y lo que hacía
     * de ella una regla —el `CHECK` de `documentos`— se mudó a `exigeSistema()`.
     */
    public function esRedactado(): bool
    {
        return match ($this) {
            self::SoaIso, self::DdaEns, self::PlanAdecuacionEns,
            self::AnalisisContexto, self::ActaRevision => false,
            self::Politica, self::Norma, self::Procedimiento => true,
        };
    }

    /**
     * Si el documento no significa nada sin un sistema detrás.
     *
     * Una Declaración de Aplicabilidad o un plan de adecuación sin sistema no son
     * un documento raro, son un documento imposible: sus filas salen de la
     * categorización de un sistema concreto. Una política y un análisis del
     * contexto son de la organización entera.
     *
     * Lo consume el `CHECK` `documentos_sistema_check` y el `FormRequest`. Y **no
     * se deduce de `esRedactado()`**, que es lo que hacía hasta ahora: las dos
     * cosas coincidían por accidente hasta que llegó un calculado de ámbito
     * organizativo.
     */
    public function exigeSistema(): bool
    {
        return match ($this) {
            self::SoaIso, self::DdaEns, self::PlanAdecuacionEns => true,
            self::AnalisisContexto, self::ActaRevision,
            self::Politica, self::Norma, self::Procedimiento => false,
        };
    }

    /**
     * Cómo se titula el apartado donde el sistema declara lo que no puede afirmar.
     *
     * Vive aquí porque lo preguntan dos sitios —el esqueleto de fábrica y el
     * resolutor que repone el bloque cuando alguien lo borra— y con la cadena
     * escrita dos veces, el documento entregado se titula de una forma y el
     * reparado de otra.
     *
     * **«Declaración» sólo cuando lo es.** Una política no declara aplicabilidad
     * de nada y un plan de adecuación tampoco: lista lo que falta por hacer.
     */
    public function tituloLimitaciones(): string
    {
        return match ($this) {
            self::SoaIso, self::DdaEns => 'Limitaciones de esta declaración',
            self::PlanAdecuacionEns, self::AnalisisContexto, self::ActaRevision,
            self::Politica, self::Norma, self::Procedimiento => 'Limitaciones de este documento',
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
     *
     * **También nulo en el análisis del contexto**, y por dos motivos a la vez: no
     * cuelga de ningún sistema, así que no hay marco con el que contrastarlo; y
     * aunque las cláusulas 4.1 y 4.2 sean de ISO, el contexto de la organización es
     * el mismo para todos los marcos que se le apliquen.
     *
     * **Y nulo en el acta de revisión por el segundo de esos dos motivos**, que
     * aquí pesa más: la 9.3 es de ISO, pero lo que la dirección revisa es el SGSI
     * entero —sus riesgos, sus auditorías, sus objetivos— y ésos cubren todos los
     * marcos que la organización tenga. Un acta por marco sería pedirle a la
     * dirección que revisara la misma organización dos veces.
     */
    public function marcoEsperado(): ?string
    {
        return match ($this) {
            self::SoaIso => 'ISO27001-2022',
            self::DdaEns, self::PlanAdecuacionEns => 'ENS-RD311-2022',
            self::AnalisisContexto, self::ActaRevision,
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
