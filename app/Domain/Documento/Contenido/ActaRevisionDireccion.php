<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Excepciones\DocumentoNoGenerable;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Documento\Narrativa\ResolverNarrativa;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Domain\Tarea\Models\Tarea;

/**
 * El acta de la revisión por la dirección: § 4.15 y la cláusula 9.3.
 *
 * **Es el quinto documento calculado y el segundo de ámbito organizativo**, detrás
 * del análisis del contexto. Implementa `GeneradorDocumento` directamente y no
 * hereda de `DocumentoCalculado` por lo mismo que aquél: esa clase es la tubería
 * de la tabla larga de requisitos —la consulta por tipo, el agrupado por el nodo
 * padre, las correspondencias cruzadas— y aquí no hay ninguna de las tres cosas.
 * Lo común —portada, historial y limitaciones— sale de `ArmaContenidoComun`.
 *
 * ### Se construye desde la instantánea, nunca de una consulta nueva
 *
 * Es el fallo más caro que este módulo podía tener, y el repositorio ya lo ha
 * evitado cuatro veces: si el acta consultara las tablas, el documento de la
 * revisión de marzo enseñaría las no conformidades, los riesgos y los objetivos de
 * octubre — y lo haría **bajo la fecha y la firma de marzo**, que es la definición
 * de un documento que miente.
 *
 * Sin revisión aprobada no hay acta, y se dice: generar una desde una reunión que
 * no se ha celebrado sería entregar un acta que nadie ha firmado.
 *
 * ### La revisión que se imprime es la última aprobada
 *
 * Igual que el análisis del contexto imprime el vigente. Un documento es una
 * **serie**, y cada generación entrega la foto más reciente que existe; el
 * histórico de lo anterior vive en `documento_versiones`, que es donde tiene que
 * estar.
 */
final class ActaRevisionDireccion implements GeneradorDocumento
{
    use Concerns\ArmaContenidoComun;

    public function __construct(
        private readonly ResolverNarrativa $narrativa,
        private readonly MarkdownDocumento $markdown,
    ) {}

    public function tipo(): TipoDocumento
    {
        return TipoDocumento::ActaRevision;
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    public function construir(Documento $documento, DocumentoVersion $version, array $parametros = []): ContenidoDocumento
    {
        $revision = RevisionDireccion::query()
            ->aprobadas()
            ->with(['aprobadaPor', 'tareas.responsable'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        if (! $revision instanceof RevisionDireccion) {
            throw DocumentoNoGenerable::sinRevisionAprobada();
        }

        return new ContenidoDocumento(
            titulo: $documento->titulo,
            subtitulo: $revision->codigo.' · '.$revision->fecha->format('d/m/Y'),

            portada: [
                ...$this->portadaBase($documento, $version),
                /*
                 * Sin alcance en la cabecera: un documento de ámbito organizativo
                 * no tiene uno que imprimir ahí. Lo que sí tiene y es lo que el
                 * auditor busca —de qué periodo habla el acta— va en la ficha de
                 * la revisión, dentro del cuerpo.
                 */
                'alcance' => null,
            ],

            // Sin cifras de implantación y sin filas de requisitos: este documento
            // no cuenta medidas. Lo que cuenta va en `extras`.
            resumen: [],
            filas: [],

            limitaciones: [
                ...$this->limitacionesPropias(),
                ...$this->limitacionesBase($version),
            ],
            historial: $this->historialDe($documento),
            extras: $this->extras($revision),
            textos: TextosDocumento::desdeMarkdown(
                $this->narrativa->paraDocumento($documento),
                $this->markdown,
            ),
        );
    }

    /**
     * Lo que este documento en concreto no puede afirmar.
     *
     * Van **antes** de las comunes, como en el resto: lo específico primero,
     * porque es lo que el auditor no se sabe de memoria.
     *
     * @return list<string>
     */
    private function limitacionesPropias(): array
    {
        return [
            'Este acta recoge las siete entradas de la cláusula 9.3.2 **tal como estaban el día en que se '
            .'aprobó**, no como están hoy en la herramienta. Los registros de los que salen —riesgos, '
            .'auditorías, no conformidades, objetivos y mejoras— siguen vivos; lo que aquí figura quedó '
            .'congelado, y los cambios posteriores los recogerá la revisión siguiente.',

            'La entrada de **retroalimentación de las partes interesadas** (9.3.2 e) se recoge del registro '
            .'de partes interesadas y de sus requisitos, que es lo que Statera tiene: **qué exige cada una**. '
            .'La herramienta **no registra quejas, encuestas de satisfacción ni comunicaciones recibidas**, '
            .'así que esa entrada se aporta fuera de este documento.',

            'Los **asistentes** son texto libre y Statera no comprueba que quien figura como asistente tenga '
            .'potestad para revisar el sistema de gestión, ni que la dirección estuviera representada. '
            .'Tampoco guarda firma electrónica cualificada del acta.',

            /*
             * **Reescrita con el § 4.16.** El hueco se cerró: «celebrar la
             * revisión por la dirección» es una obligación del catálogo, y el
             * calendario avisa de la que falta. Lo que queda por declarar es que
             * la periodicidad hay que declararla — Statera no la impone, porque
             * ni ISO ni el ENS ponen un número.
             */
            'La **periodicidad comprometida** de la revisión es un compromiso que la organización declara '
            .'en el calendario de obligaciones (§ 4.16), y de ahí sale el aviso de la revisión que falta. '
            .'Lo que Statera **no hace** es imponerla: si nadie ha declarado esa periodicidad, no hay nada '
            .'contra lo que avisar.',
        ];
    }

    /**
     * Lo que leen los bloques calculados del cuerpo.
     *
     * **Sale de la instantánea y no de las tablas.** La única excepción son las
     * decisiones: se leen de la pivote porque son las **salidas** del acta y
     * pueden crecer después de firmarla —una decisión se ejecuta en las semanas
     * siguientes, y el trigger de inmutabilidad blinda el acta pero no lo que
     * cuelga de ella—. Que el acta enseñe el estado de hoy de sus propias
     * decisiones es lo que se quiere: lo que se congeló es lo que la dirección
     * **tuvo delante**, no lo que mandó hacer.
     *
     * @return array<string, mixed>
     */
    private function extras(RevisionDireccion $revision): array
    {
        $entradas = $revision->instantanea ?? [];

        return [
            'revision' => [
                'codigo' => $revision->codigo,
                'fecha' => $revision->fecha->format('d/m/Y'),
                'periodo' => $revision->periodo(),
                'asistentes' => $revision->asistentes,
                'conclusiones' => $revision->conclusiones,
                'aprobadaPor' => $revision->aprobadaPor?->name,
                'aprobadaEn' => $revision->aprobada_en?->format('d/m/Y'),
            ],
            'entradas' => $entradas,
            'decisiones' => $revision->tareas
                ->map(fn (Tarea $tarea): array => [
                    'titulo' => $tarea->titulo,
                    'estado' => $tarea->estado->etiqueta(),
                    'tono' => $tarea->estado->tono(),
                    'responsable' => $tarea->responsable?->name,
                    'fecha' => $tarea->fecha_limite?->format('d/m/Y'),
                ])
                ->values()
                ->all(),
        ];
    }
}
