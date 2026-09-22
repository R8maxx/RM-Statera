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
 * hay siete casos y no once: **las obligaciones que no salen de ningún registro
 * son UNA fuente**, `Obligacion`, porque son filas de una tabla y no código. El
 * informe INES, la renovación de conformidad y la auditoría de seguimiento son
 * tres filas del catálogo, no tres casos de este enum — y esa es la diferencia
 * que permite que una organización declare la suya sin un despliegue.
 *
 * **Sin recuento en la cabecera, a propósito.** Decía «hoy son dos» y pasó a ser
 * falso el día que el § 4.5 añadió `Fuente::Documento`, sin que nadie lo notara
 * —el mismo descuido que la limitación del plan de adecuación, que enumeraba las
 * fuentes que el calendario sí recoge y se quedó corta a la vez—. Un recuento
 * dentro de un comentario envejece cada vez que el producto crece; `cases()` no.
 *
 * **Los títulos y los verbos viven aquí y no en el correo.** `VencimientosDelDia`
 * tenía un bloque escrito a mano por fuente y por mitad —lo pasado y lo próximo—:
 * con siete fuentes serían catorce literales, y catorce literales es cómo se
 * olvida uno.
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
     * Las fuentes que esta cuenta puede ver.
     *
     * **La rejilla enseña siete registros con una sola llave**, así que el
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
        };
    }

    /**
     * El icono con el que se distingue de un vistazo en el calendario.
     *
     * **Es el de su módulo en `lib/navegacion.ts`**, y eso no es casualidad: en
     * la rejilla el icono es el único canal que identifica la fuente —el color
     * dice cómo va, no qué es— y quien lo aprende lo aprende del sidebar. Dos
     * excepciones, las dos anteriores a este módulo: `Documento` usa `FileCheck`
     * y no `FileText` porque lo que vence es la revisión de lo firmado.
     */
    public function icono(): string
    {
        return match ($this) {
            self::Tarea => 'ListTodo',
            self::Evidencia => 'Paperclip',
            self::Documento => 'FileCheck',
            self::Formacion => 'GraduationCap',
            self::Indicador => 'Gauge',
            self::Implantacion => 'ClipboardCheck',
            self::Obligacion => 'CalendarCheck',
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
        };
    }

    /** «vence» / «caduca»: el verbo con el que se lee una línea del correo. */
    public function verbo(): string
    {
        return match ($this) {
            self::Evidencia, self::Formacion => 'caduca',
            self::Documento => 'toca revisarlo',
            self::Indicador, self::Obligacion => 'toca',
            self::Tarea, self::Implantacion => 'vence',
        };
    }

    public function verboPasado(): string
    {
        return match ($this) {
            self::Evidencia, self::Formacion => 'caducó',
            self::Documento => 'tocaba revisarlo',
            self::Indicador, self::Obligacion => 'tocaba',
            self::Tarea, self::Implantacion => 'venció',
        };
    }
}
