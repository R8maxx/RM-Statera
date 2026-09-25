<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Models\User;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * De qué es un vencimiento.
 *
 * § 4.16 —el calendario de obligaciones— enumera once cosas periódicas, y aquí
 * hay menos casos que eso: **las obligaciones que no salen de ningún registro
 * son UNA fuente**, `Obligacion`, porque son filas de una tabla y no código. El
 * informe INES, la renovación de conformidad y la auditoría de seguimiento son
 * tres filas del catálogo, no tres casos de este enum — y esa es la diferencia
 * que permite que una organización declare la suya sin un despliegue.
 *
 * **`PruebaContinuidad` y `Bia` llegaron con § 4.11**, y no antes: la
 * continuidad estaba en el catálogo con `categoria_minima: media` pero el
 * módulo mismo no existía, así que no había nada que este enum pudiera
 * consultar todavía.
 *
 * **Sin recuento en la cabecera, a propósito.** Decía «hoy son dos» y pasó a ser
 * falso el día que el § 4.5 añadió `Fuente::Documento`, sin que nadie lo notara
 * —el mismo descuido que la limitación del plan de adecuación, que enumeraba las
 * fuentes que el calendario sí recoge y se quedó corta a la vez—. Un recuento
 * dentro de un comentario envejece cada vez que el producto crece; `cases()` no.
 *
 * **Los títulos y los verbos viven aquí y no en el correo.** `VencimientosDelDia`
 * tenía un bloque escrito a mano por fuente y por mitad —lo pasado y lo próximo—:
 * dos literales por fuente, y una lista de literales que crece con cada módulo
 * es cómo se olvida uno.
 */
#[TypeScript]
enum Fuente: string
{
    case Tarea = 'tarea';
    case Evidencia = 'evidencia';

    /**
     * La tercera, y la primera que no es un registro con fecha propia.
     *
     * Lo que vence no es el documento sino **la revisión de su versión
     * aprobada**: la fecha se calcula al firmar, desde la periodicidad que
     * declara el documento, y se congela en la versión. Un documento sin
     * periodicidad no vence nunca, y es una respuesta legítima — una Declaración
     * de Aplicabilidad se rehace cuando cambia el alcance, no cuando pasa un año.
     */
    case Documento = 'documento';

    /**
     * La formación que toca renovar, **por persona y no por sesión**.
     *
     * Lo que vence no es la convocatoria de marzo: es que a Fulanita le toca
     * volver a formarse doce meses después de la última a la que asistió. Por eso
     * la fila es la persona y el enlace lleva a su ficha.
     *
     * Quien nunca ha recibido formación **no sale aquí**: no hay fecha que pintar
     * y `fecha_alta + 12` sería inventarle un plazo. Sale donde ya salía, en el
     * panel y en `/personas`.
     */
    case Formacion = 'formacion';

    /**
     * El periodo cerrado de un indicador que pasó sin medirse.
     *
     * Sólo el **último** periodo cerrado, que es lo que ya dice
     * `Indicador::periodoSinMedir()`. Ni los anteriores ni un aviso de que el
     * trimestre en curso va a cerrar: eso último sería inventar un plazo al que
     * nadie se comprometió, que es lo que este producto se niega a hacer con el
     * CCN-CERT y con el riesgo residual.
     */
    case Indicador = 'indicador';

    /** La fecha objetivo de una medida pendiente del plan de adecuación. */
    case Implantacion = 'implantacion';

    /** Un compromiso periódico del § 4.16 al que le toca. */
    case Obligacion = 'obligacion';

    /**
     * Una prueba de continuidad planificada: § 4.11 y `op.cont.3`.
     *
     * Sólo las `planificada`: `PruebaContinuidad::scopePrevistaEntre()` las
     * filtra, igual que `scopeVencidas()` y `scopePorVencer()`. Una realizada o
     * una cancelada son terminales y no tienen nada pendiente que anunciar.
     */
    case PruebaContinuidad = 'prueba_continuidad';

    /**
     * La revisión de un BIA aprobado: § 4.11.
     *
     * Igual que con los documentos, lo que vence no es el BIA sino su
     * revisión: un BIA aprobado sigue vigente hasta que se vuelva a aprobar o
     * se dé por obsoleto. Sólo los `aprobado`: uno en borrador no tiene
     * revisión que anunciar, aunque conserve la fecha de cuando lo estuvo.
     */
    case Bia = 'bia';

    /**
     * La reevaluación de un proveedor: § 4.9 y § 4.16.
     *
     * **Una `Fuente` y no una fila de `catalogo/obligaciones.yaml`**, que es lo
     * que el propio YAML dejó dicho: lo que vence sale de un registro —la última
     * evaluación y los meses que la organización fija por criticidad—, y una
     * obligación del catálogo es justo lo que no sale de ninguno. Sólo los que no
     * están retirados y ya tienen una evaluación: sin ella no hay fecha, y lo que
     * falta no es reevaluar sino evaluar por primera vez.
     */
    case Proveedor = 'proveedor';

    /**
     * El plazo de remediación de una vulnerabilidad: invariante 8 y `op.exp.4`.
     *
     * Sólo las que siguen sin arreglo —abiertas o en remediación—: una mitigada
     * ya cumplió el plazo, aunque le falte la verificación, y ésa la cuenta el
     * panel aparte.
     */
    case Vulnerabilidad = 'vulnerabilidad';

    /**
     * Las fuentes que esta cuenta puede ver.
     *
     * **La rejilla enseña nueve registros con una sola llave**, así que el
     * permiso de la pantalla no basta: quien no tenga `indicadores.ver` no puede
     * enterarse por el calendario de qué indicadores hay. Es la misma regla que
     * `AlertasDelPanel` aplica a cada tarjeta, y el mismo motivo por el que no se
     * resuelve con un `v-if`: esconder el chip dejaría la consulta hecha.
     *
     * @return list<self>
     */
    public static function visiblesPara(User $usuario): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $fuente): bool => $usuario->can($fuente->permiso()->value),
        ));
    }

    /** El permiso del módulo dueño, que es el que decide si su chip se pinta. */
    public function permiso(): Permiso
    {
        return match ($this) {
            self::Tarea => Permiso::TareasVer,
            self::Evidencia => Permiso::EvidenciasVer,
            self::Documento => Permiso::DocumentosVer,
            self::Formacion => Permiso::PersonasVer,
            self::Indicador => Permiso::IndicadoresVer,
            self::Implantacion => Permiso::ImplantacionesVer,
            self::Obligacion => Permiso::ObligacionesVer,
            self::PruebaContinuidad, self::Bia => Permiso::ContinuidadVer,
            self::Proveedor => Permiso::ProveedoresVer,
            self::Vulnerabilidad => Permiso::VulnerabilidadesVer,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Tarea => 'Tarea',
            self::Evidencia => 'Evidencia',
            self::Documento => 'Documento',
            self::Formacion => 'Formación',
            self::Indicador => 'Indicador',
            self::Implantacion => 'Medida del plan',
            self::Obligacion => 'Obligación',
            self::PruebaContinuidad => 'Prueba de continuidad',
            self::Bia => 'BIA',
            self::Proveedor => 'Proveedor',
            self::Vulnerabilidad => 'Vulnerabilidad',
        };
    }

    /**
     * El icono con el que se distingue de un vistazo.
     *
     * En la rejilla el icono es **el único canal que identifica la fuente** —el
     * color dice cómo va, no qué es—, y la fila de chips que hace de clave sólo
     * existe en `/calendario`: en el popover de un día, en la agenda de móvil y
     * en el correo el icono va solo. Así que lo que manda aquí no es parecerse al
     * sidebar: es **que las nueve siluetas se separen a 14 px**.
     *
     * Cinco coinciden con el icono de su módulo en `lib/navegacion.ts`, que es lo
     * cómodo cuando además se distinguen. Dos no, y las dos tienen motivo:
     *
     * - `Documento` usa `FileCheck` y no `FileText`, porque lo que vence es la
     *   revisión de lo firmado. Es anterior a este módulo.
     * - `Implantacion` usa `Target` y no `ClipboardCheck`. Junto con `FileCheck` y
     *   el `CalendarCheck` que llevaba `Obligacion`, eran **la misma palomita en la
     *   misma posición** —`m9 14 2 2 4-4` y `m9 15 2 2 4-4`— sobre tres
     *   cuadriláteros de 18×18: a 14 px lo que los separaba medía un píxel.
     *   Círculos concéntricos no se parecen a nada de eso.
     *
     * `Obligacion` sí coincide con el suyo, porque **el sidebar se movió a `Repeat`
     * con este arreglo**: `CalendarCheck` compartía marco con el `CalendarDays` de
     * Calendario, que es la entrada de encima.
     *
     * `Target` es además el icono de Objetivos en el sidebar, y se asume: en el
     * calendario un `Target` sólo puede ser una medida, porque los objetivos están
     * declarados fuera de él —tienen tareas detrás y sus plazos ya pintan chip—.
     *
     * `Proveedor` y `Vulnerabilidad` coinciden con su entrada del sidebar,
     * `Truck` y `Bug`: no se parecen a ninguna de las demás.
     *
     * `PruebaContinuidad` y `Bia` tampoco coinciden con su módulo: el sidebar
     * lleva **una sola** entrada, «Continuidad», con `LifeBuoyIcon`, porque BIA
     * y pruebas comparten pantalla de arranque —lo mismo que ya pasa con
     * Personas / Puestos / Formación—, así que aquí no hay icono de módulo que
     * tomar prestado y hacía falta uno nuevo para cada fuente. `FlaskConical`
     * es el ensayo —ni `Target` (ya es Implantación) ni `ClipboardCheck` (ya
     * era la misma palomita descartada arriba)—; `Timer` es la cuenta atrás
     * del BIA, y no `Gauge` (ya es Indicador) ni `CalendarClock` (icono de
     * respaldo del tono `planificado` en `lib/tonos.ts`: lo prohíbe
     * `FuentesConIconoDistintoTest`).
     *
     * Lo fija `FuentesConIconoDistintoTest`, que es la comprobación que faltaba:
     * `IconosTest` sólo mira colisiones dentro de un mismo enum y por tono.
     */
    public function icono(): string
    {
        return match ($this) {
            self::Tarea => 'ListTodo',
            self::Evidencia => 'Paperclip',
            self::Documento => 'FileCheck',
            self::Formacion => 'GraduationCap',
            self::Indicador => 'Gauge',
            self::Implantacion => 'Target',
            self::Obligacion => 'Repeat',
            self::PruebaContinuidad => 'FlaskConical',
            self::Bia => 'Timer',
            self::Proveedor => 'Truck',
            self::Vulnerabilidad => 'Bug',
        };
    }

    public function url(int $id): string
    {
        return match ($this) {
            self::Tarea => "/tareas/{$id}",
            self::Evidencia => "/evidencias/{$id}",
            self::Documento => "/documentos/{$id}",
            self::Formacion => "/personas/{$id}",
            self::Indicador => "/indicadores/{$id}",
            self::Implantacion => "/implantaciones/{$id}",
            self::Obligacion => "/obligaciones/{$id}",
            self::PruebaContinuidad => "/continuidad/pruebas/{$id}",
            self::Bia => "/continuidad/bia/{$id}",
            self::Proveedor => "/proveedores/{$id}",
            self::Vulnerabilidad => "/vulnerabilidades/{$id}",
        };
    }

    /** El título del bloque de lo que ya se pasó, en el correo diario. */
    public function tituloPasados(): string
    {
        return match ($this) {
            self::Tarea => 'Tareas vencidas',
            self::Evidencia => 'Evidencias caducadas',
            // «Sin revisar a tiempo» y no «vencidos»: lo que se ha pasado es la
            // revisión, no el documento, que sigue aprobado y en vigor hasta que
            // haya otro. La redacción es la que ya tenía el correo.
            self::Documento => 'Documentos sin revisar a tiempo',
            self::Formacion => 'Formación caducada',
            self::Indicador => 'Periodos sin medir',
            self::Implantacion => 'Medidas fuera de su fecha objetivo',
            self::Obligacion => 'Obligaciones fuera de plazo',
            self::PruebaContinuidad => 'Pruebas de continuidad sin realizar',
            self::Bia => 'BIA sin revisar a tiempo',
            self::Proveedor => 'Proveedores sin reevaluar a tiempo',
            self::Vulnerabilidad => 'Vulnerabilidades fuera de plazo',
        };
    }

    /** El título del bloque de lo que viene. */
    public function tituloProximos(): string
    {
        return match ($this) {
            self::Tarea => 'Tareas próximas a vencer',
            self::Evidencia => 'Evidencias próximas a caducar',
            self::Documento => 'Documentos por revisar',
            self::Formacion => 'Formación que toca renovar',
            self::Indicador => 'Periodos que cierran',
            self::Implantacion => 'Medidas que llegan a su fecha objetivo',
            self::Obligacion => 'Obligaciones que tocan',
            self::PruebaContinuidad => 'Pruebas de continuidad previstas',
            self::Bia => 'BIA por revisar',
            self::Proveedor => 'Proveedores por reevaluar',
            self::Vulnerabilidad => 'Vulnerabilidades por remediar',
        };
    }

    /** «vence» / «caduca»: el verbo con el que se lee una línea del correo. */
    public function verbo(): string
    {
        return match ($this) {
            self::Evidencia, self::Formacion => 'caduca',
            self::Documento => 'toca revisarlo',
            self::Indicador, self::Obligacion, self::PruebaContinuidad, self::Bia, self::Proveedor => 'toca',
            self::Tarea, self::Implantacion, self::Vulnerabilidad => 'vence',
        };
    }

    public function verboPasado(): string
    {
        return match ($this) {
            self::Evidencia, self::Formacion => 'caducó',
            self::Documento => 'tocaba revisarlo',
            self::Indicador, self::Obligacion, self::PruebaContinuidad, self::Bia, self::Proveedor => 'tocaba',
            self::Tarea, self::Implantacion, self::Vulnerabilidad => 'venció',
        };
    }
}
