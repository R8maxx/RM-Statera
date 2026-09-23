<?php

declare(strict_types=1);

namespace App\Domain\Documento\Narrativa;

use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;

/**
 * El texto con el que Statera viene de fábrica.
 *
 * Es el último eslabón del resolutor: documento → plantilla de la organización →
 * **esto**. Una organización que no ha tocado nada entrega un documento con
 * estos textos, y por eso tienen que estar bien escritos: son lo primero que lee
 * un auditor.
 *
 * **Cuatro de estos textos estaban clavados en las plantillas Blade** y nadie
 * podía cambiarlos sin tocar código. Están transcritos a Markdown carácter a
 * carácter, con `**negrita**` donde había `<strong>`, de modo que un documento
 * generado antes y después de esta entrega dice exactamente lo mismo. Hay un
 * test que lo fija; si alguien mejora la redacción, ese test se pone rojo y hay
 * que actualizarlo a conciencia, que es justo lo que se quiere.
 *
 * Lo que devuelve cadena vacía es un hueco que **no se imprime** mientras nadie
 * escriba en él: una sección con título y nada debajo se lee como un documento
 * roto.
 */
final class TextosDeFabrica
{
    /**
     * El texto de fábrica de un hueco, o cadena vacía si viene sin nada.
     */
    public static function para(TipoDocumento $tipo, SeccionNarrativa $seccion): string
    {
        if (! $seccion->aplicaA($tipo)) {
            return '';
        }

        if ($tipo->esRedactado()) {
            return self::redactado($tipo, $seccion);
        }

        return match ($seccion) {
            SeccionNarrativa::Introduccion => self::introduccion($tipo),
            SeccionNarrativa::ObjetoYAlcance => self::objetoYAlcance($tipo),
            SeccionNarrativa::Metodologia => self::metodologia($tipo),
            SeccionNarrativa::NotaTabla => self::notaTabla($tipo),
            SeccionNarrativa::NotaDerivacion => self::notaDerivacion(),
            SeccionNarrativa::NotaMadurez => self::notaMadurez(),

            // Vacíos de partida: son huecos que existen para quien los quiera,
            // no texto que Statera tenga nada que decir en nombre de nadie.
            SeccionNarrativa::NotaResumen,
            SeccionNarrativa::NotaExclusiones,
            SeccionNarrativa::Conclusiones,
            SeccionNarrativa::LimitacionesPropias,
            SeccionNarrativa::Aprobacion => '',
        };
    }

    /**
     * Todos los huecos de un tipo de documento, indexados por su clave.
     *
     * @return array<string, string>
     */
    public static function todos(TipoDocumento $tipo): array
    {
        $textos = [];

        foreach (SeccionNarrativa::paraTipo($tipo) as $seccion) {
            $textos[$seccion->value] = self::para($tipo, $seccion);
        }

        return $textos;
    }

    private static function introduccion(TipoDocumento $tipo): string
    {
        return match ($tipo) {
            TipoDocumento::SoaIso => <<<'MD'
                Este documento es la Declaración de Aplicabilidad del sistema de gestión de la seguridad de la información, exigida por la cláusula 6.1.3 d) de ISO/IEC 27001:2022. Recoge, para cada uno de los controles del Anexo A, si la organización lo considera aplicable, por qué, en qué estado de implantación se encuentra y qué evidencia lo sostiene.

                Se genera desde el registro de implantaciones y refleja la situación en la fecha de extracción que figura al final del documento.
                MD,

            TipoDocumento::DdaEns => <<<'MD'
                Este documento es la Declaración de Aplicabilidad del sistema, exigida por el Esquema Nacional de Seguridad (Real Decreto 311/2022). Recoge las medidas del Anexo II que le son exigibles según su categoría, de dónde sale esa exigencia, en qué estado de implantación se encuentran y qué evidencia las sostiene.

                La categoría no se elige: se deriva de valorar el perjuicio en las cinco dimensiones de seguridad, y esa derivación se imprime entera en el apartado siguiente.
                MD,

            TipoDocumento::PlanAdecuacionEns => <<<'MD'
                Este documento es el plan de adecuación del sistema al Esquema Nacional de Seguridad (Real Decreto 311/2022). Recoge las medidas del Anexo II que le son exigibles y **todavía no están implantadas**, con la fecha en que se prevé tenerlas, quién responde de cada una y el trabajo que hay registrado para conseguirlo.

                No repite la Declaración de Aplicabilidad, que es el documento que dice qué se exige y cómo está cada medida: aquí sólo está lo que falta.
                MD,

            TipoDocumento::AnalisisContexto => <<<'MD'
                Este documento recoge el análisis del contexto de la organización: las cuestiones internas y externas pertinentes para la seguridad de la información —cláusula 4.1 de ISO/IEC 27001:2022— y las partes interesadas con sus requisitos —cláusula 4.2—, junto con el alcance declarado de cada sistema —cláusula 4.3—.

                Recoge además, como exige la enmienda 1:2024, la determinación de si el cambio climático es una cuestión pertinente para la organización.

                Refleja la revisión aprobada en la fecha que figura en la portada. Lo que aquí se imprime quedó congelado ese día: si el contexto ha cambiado desde entonces, lo recogerá la revisión siguiente.
                MD,

            TipoDocumento::ActaRevision => <<<'MD'
                Este documento es el acta de la revisión por la dirección del sistema de gestión de la seguridad de la información, exigida por la cláusula 9.3 de ISO/IEC 27001:2022. Recoge las siete entradas que esa cláusula declara obligatorias y las decisiones que la dirección tomó a partir de ellas.

                Las entradas se recogieron del registro de la herramienta el día en que se aprobó el acta y quedaron congeladas: lo que aquí figura es lo que la dirección tuvo delante, no lo que el sistema diga hoy.
                MD,

            // No llegan aquí: `para()` desvía los redactados a `redactado()`
            // antes, porque su introducción no es la de una declaración.
            TipoDocumento::Politica,
            TipoDocumento::Norma,
            TipoDocumento::Procedimiento,
            TipoDocumento::PlanContinuidad => '',
        };
    }

    /**
     * Los textos de partida de un documento que escribe la organización.
     *
     * Aquí Statera sí tiene algo que decir, a diferencia de `Conclusiones` o
     * `LimitacionesPropias`: son las frases que el ENS espera literalmente de una
     * política —`org.1` pide que declare los objetivos, el compromiso de la
     * dirección y a quién obliga— y que cualquier organización va a escribir casi
     * igual. Es lo que la § 4.5 llama «plantillas base personalizables»: un punto
     * de partida cierto, no un hueco en blanco ni un «sustituya este texto», que
     * es lo que acabaría impreso en el PDF que alguien aprueba sin mirar.
     */
    private static function redactado(TipoDocumento $tipo, SeccionNarrativa $seccion): string
    {
        return match ($seccion) {
            SeccionNarrativa::Introduccion => match ($tipo) {
                TipoDocumento::Politica => <<<'MD'
                    Esta política establece los objetivos y los principios de seguridad de la información que la organización adopta, y recoge el compromiso de la dirección con su cumplimiento y con la mejora continua del sistema de gestión.

                    Es de obligado cumplimiento para todo el personal de la organización y para los terceros que traten información de la organización o accedan a sus sistemas.
                    MD,

                TipoDocumento::Norma => <<<'MD'
                    Esta norma desarrolla lo que la política de seguridad de la información establece, y fija las reglas concretas que hay que cumplir en el ámbito que se declara más abajo.

                    Es de obligado cumplimiento para quienes figuran en su ámbito de aplicación. Lo que no esté recogido aquí se rige por la política de seguridad de la información.
                    MD,

                TipoDocumento::Procedimiento => <<<'MD'
                    Este procedimiento describe cómo se ejecuta una actividad concreta: quién la hace, cuándo, con qué medios y qué registro deja.

                    Los registros que genera su ejecución son la evidencia de que la actividad se realiza, y se conservan durante el plazo que aquí se indique.
                    MD,

                default => '',
            },

            /*
             * El alcance se queda vacío, y no por falta de ganas: a quién obliga
             * y sobre qué sistemas se aplica es lo más específico que tiene un
             * documento así, y cualquier frase de relleno que Statera pusiera
             * aquí saldría impresa en el PDF que alguien acaba aprobando. Un
             * hueco vacío no se pinta —ni él ni su título—, así que un documento
             * sin alcance escrito se nota; una frase genérica, no.
             *
             * Lo que sí hay es la ayuda del campo en el editor, que es donde se
             * orienta a quien escribe sin acabar dentro del documento.
             */
            default => '',
        };
    }

    /**
     * El objeto no es el mismo en una declaración que en un plan.
     *
     * Aquí no había `match` y el plan habría heredado en silencio «dejar
     * constancia de qué requisitos le son exigibles y en qué situación está cada
     * uno», que es la descripción de una declaración. No habría roto nada: habría
     * salido impreso.
     */
    private static function objetoYAlcance(TipoDocumento $tipo): string
    {
        $objeto = match ($tipo) {
            TipoDocumento::SoaIso, TipoDocumento::DdaEns => 'El objeto de este documento es dejar constancia, ante la dirección y ante un auditor, de qué requisitos le son exigibles al sistema y en qué situación está cada uno.',
            TipoDocumento::PlanAdecuacionEns => 'El objeto de este documento es dejar constancia, ante la dirección y ante un auditor, de qué le queda al sistema para cumplir el Esquema Nacional de Seguridad, en qué plazo se prevé conseguirlo y quién responde de cada medida.',
            TipoDocumento::AnalisisContexto => 'El objeto de este documento es dejar constancia de cuáles son las cuestiones internas y externas pertinentes para la seguridad de la información de la organización, quiénes son sus partes interesadas y qué exige o espera cada una. Es lo que piden las cláusulas 4.1 y 4.2 de ISO/IEC 27001:2022.',
            TipoDocumento::ActaRevision => 'El objeto de este documento es dejar constancia, ante un auditor, de que la dirección ha revisado el sistema de gestión de la seguridad de la información: qué tuvo delante, cuándo, quiénes estuvieron y qué decidió. Es lo que pide la cláusula 9.3 de ISO/IEC 27001:2022.',

            // No llegan aquí: `para()` desvía los redactados antes.
            TipoDocumento::Politica,
            TipoDocumento::Norma,
            TipoDocumento::Procedimiento,
            TipoDocumento::PlanContinuidad => '',
        };

        /*
         * El párrafo del alcance es de los documentos que cuelgan de un sistema.
         * El análisis del contexto es de la organización entera y recoge el
         * alcance **de todos** sus sistemas en un apartado propio: heredarlo aquí
         * dejaría impreso «el sistema identificado en la portada» en un documento
         * que no tiene ninguno.
         */
        $alcance = match ($tipo) {
            TipoDocumento::AnalisisContexto => 'El ámbito es la organización entera, no un sistema concreto. El alcance declarado de cada '
                .'sistema del que la organización responde figura en su propio apartado, tal como estaba el '
                .'día en que se aprobó esta revisión.',
            TipoDocumento::ActaRevision => 'El ámbito es el sistema de gestión entero, no un sistema concreto: lo que la dirección '
                .'revisa son los riesgos, las auditorías, los objetivos y las no conformidades de la '
                .'organización, y ésos cubren todos los marcos que se le apliquen. El periodo revisado '
                .'figura en la portada.',
            default => 'El alcance es el del sistema identificado en la portada, con el alcance declarado y las '
                .'exclusiones que allí figuran. Los activos, las evidencias y las tareas que lo sostienen '
                .'se gestionan en la herramienta y no se reproducen aquí.',
        };

        return $objeto."\n\n".$alcance;
    }

    private static function metodologia(TipoDocumento $tipo): string
    {
        $derivacion = match ($tipo) {
            TipoDocumento::SoaIso => 'Los controles del Anexo A se consideran aplicables de partida. Excluir uno exige registrar el motivo, y esa justificación se imprime en el apartado de controles excluidos: sin motivo, la herramienta no permite la exclusión.',
            TipoDocumento::DdaEns => 'El conjunto de medidas exigibles se deriva de la categoría del sistema, que a su vez es el máximo de los niveles asignados a las cinco dimensiones de seguridad. Ninguna medida se marca a mano.',
            TipoDocumento::PlanAdecuacionEns => 'El conjunto de medidas exigibles se deriva de la categoría del sistema, y de él entran en este plan las que no figuran como implantadas. La fecha objetivo y el responsable son los que consten en cada medida; el trabajo asociado son las tareas abiertas vinculadas a ella.',
            TipoDocumento::AnalisisContexto => 'Las cuestiones se clasifican en los cuatro cuadrantes de un análisis DAFO. El ámbito —interno o externo— y el signo —a favor o en contra— no se eligen: se derivan del cuadrante, que es lo que define la matriz.',
            TipoDocumento::ActaRevision => 'Las siete entradas de la cláusula 9.3.2 no se transcriben a mano: se recogen del registro de la herramienta en el momento de aprobar el acta y quedan congeladas con ella. Las auditorías se acotan al periodo revisado; lo que está abierto —no conformidades, riesgos, mejoras— se recoge tal como está, con independencia de cuándo se detectara.',

            // No llegan aquí: `SeccionNarrativa::aplicaA()` no ofrece este hueco
            // a un documento redactado, que no deriva nada de ninguna tabla.
            TipoDocumento::Politica,
            TipoDocumento::Norma,
            TipoDocumento::Procedimiento,
            TipoDocumento::PlanContinuidad => '',
        };

        /*
         * El segundo párrafo habla de estados de implantación, que en el análisis
         * del contexto no existen. Lo que se mantiene y se revisa aquí es otra
         * cosa: el propio análisis, entero y con su fecha.
         */
        $mantenimiento = match ($tipo) {
            TipoDocumento::AnalisisContexto => 'El contexto se revisa entero, no cuestión a cuestión: cada revisión se aprueba con su fecha '
                .'y su firma, y desde ese momento deja de poder modificarse. Lo que entra y lo que sale de '
                .'una revisión a la siguiente queda registrado con el motivo de cada baja.',
            TipoDocumento::ActaRevision => 'Las decisiones de la revisión se registran como tareas con responsable y plazo, y el estado '
                .'en que estén se recoge como primera entrada de la revisión siguiente. Es lo que permite '
                .'comprobar un año después si lo que se decidió se hizo.',
            default => 'El estado de implantación y el nivel de madurez los mantiene la persona responsable de cada requisito, y cada cambio queda registrado con su fecha y su autor.',
        };

        return $derivacion."\n\n".$mantenimiento;
    }

    /**
     * Migrado literal de `soa-iso.blade.php` y `dda-ens.blade.php`.
     */
    private static function notaTabla(TipoDocumento $tipo): string
    {
        return match ($tipo) {
            TipoDocumento::SoaIso => <<<'MD'
                Un control por fila, agrupados por los cuatro temas de ISO/IEC 27001:2022. La columna «Correspondencia ENS» indica qué medidas del Real Decreto 311/2022 cubren lo mismo: la misma prueba vale para los dos marcos.

                **Cómo leer «Origen de la inclusión».** Todo control del Anexo A se incluye de partida, y por eso todas las filas aplicables empiezan por «Anexo A»; lo que se justifica es la **exclusión**, no la inclusión. A continuación se añade, cuando existe, la exigencia **legal** que obliga además a ese control: la medida del ENS que le corresponde y que el sistema del Esquema tiene como exigible.
                MD,

            TipoDocumento::DdaEns => <<<'MD'
                Una medida por fila, agrupadas por el nodo del que cuelgan. «Origen de la exigencia» dice de dónde sale lo que se exige: de la categoría del sistema, de modular por el nivel de una dimensión concreta, de un perfil de cumplimiento o del propio catálogo.

                **Los refuerzos del Anexo II se acumulan**: «Refuerzo 2» significa «hasta el refuerzo 2», es decir, R1 y R2, no sólo R2.
                MD,

            TipoDocumento::PlanAdecuacionEns => <<<'MD'
                Una medida pendiente por fila, agrupadas por el nodo del que cuelgan y en el orden del Anexo II. «Trabajo planificado» son las tareas **abiertas** vinculadas a la medida, con su plazo; las cerradas y las descartadas no figuran.

                **El coste de la columna y el coste total no suman lo mismo, y es correcto.** Una misma tarea puede hacer avanzar varias medidas a la vez: la columna se la imputa a cada una de ellas y el total la cuenta una sola vez.
                MD,

            TipoDocumento::AnalisisContexto => <<<'MD'
                Las cuestiones van agrupadas por cuadrante: fortalezas y debilidades son **internas** —están en manos de la organización— y oportunidades y amenazas **externas**. La columna «Riesgos» indica los riesgos del análisis de riesgos que declaran venir de esa cuestión, que es lo que pide la cláusula 6.1.1 cuando dice que la apreciación de riesgos se hace considerando el contexto.

                En la tabla de partes interesadas, la naturaleza de cada requisito dice con qué fuerza ata: lo **legal** y lo **contractual** obligan, y una **expectativa** no. Una expectativa sin medida detrás no es una laguna.
                MD,

            TipoDocumento::ActaRevision => <<<'MD'
                Cada apartado es una de las siete entradas que la cláusula 9.3.2 declara obligatorias, en el orden en que la norma las enumera. Las cifras son las del día en que se aprobó el acta y no cambian después.

                **Un cero es una entrada recogida, no una entrada que falte.** Una organización puede llegar a su revisión sin no conformidades abiertas o sin auditorías en el periodo, y eso es lo que el acta tiene que decir.
                MD,

            // No llegan aquí: un documento redactado no tiene tabla que explicar.
            TipoDocumento::Politica,
            TipoDocumento::Norma,
            TipoDocumento::Procedimiento,
            TipoDocumento::PlanContinuidad => '',
        };
    }

    /** Migrado literal de `parciales/derivacion.blade.php`. */
    private static function notaDerivacion(): string
    {
        return 'La categoría es el máximo de las cinco dimensiones, y de ella se deriva el conjunto de '
            .'medidas exigibles del Anexo II. Ninguna medida se marca a mano: cambiar una valoración '
            .'recalcula el conjunto y queda registrado en el histórico de cada implantación.';
    }

    /** Migrado literal de `parciales/anexo-ii.blade.php`. */
    private static function notaMadurez(): string
    {
        return 'La escala L0–L5 del CCN, que es la que se reporta en INES. Cada media va con el número '
            .'de medidas sobre el que se calcula: una media sobre cuatro medidas de setenta y tres no '
            .'dice lo mismo que sobre las setenta y tres.';
    }
}
