<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Auditoria\Enums\EstadoAuditoria;
use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\AuditoriaPunto;
use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Auditoria\ResultadoAuditoria;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Excepciones\DocumentoNoGenerable;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Documento\Narrativa\ResolverNarrativa;

/**
 * El informe de auditoría interna: § 4.18 y la cláusula 9.2.2.
 *
 * **Es el séptimo documento calculado**, y como el acta y la Declaración de
 * Conformidad implementa `GeneradorDocumento` directamente: no imprime la tabla
 * de requisitos del sistema —ésa es la Declaración de Aplicabilidad— sino lo que
 * la auditoría revisó y lo que encontró.
 *
 * ### Se construye desde lo congelado
 *
 * Lo que dice el informe de una auditoría de marzo tiene que ser lo de marzo. Por
 * eso **sólo se genera con la auditoría cerrada** —desde el cierre, un trigger
 * blinda la fila, la checklist y los hallazgos— y por eso cada punto imprime la
 * exigencia y el estado **congelados** al cerrar, no los de la implantación hoy.
 * Revalorar el sistema en octubre no cambia nada de lo que aquí se lee.
 *
 * **La excepción, declarada**: el tratamiento de cada hallazgo —la no
 * conformidad o la mejora que se abrió— se imprime como está **al generar**. Es
 * lo que cambia después del cierre por diseño, igual que las decisiones del acta,
 * y la limitación lo dice.
 *
 * ### Su fuente se nombra
 *
 * A diferencia del acta —que imprime la última revisión aprobada—, el informe
 * cuelga de **su** auditoría por `documentos.auditoria_id`: dos auditorías
 * cerradas del mismo sistema son dos informes, no dos versiones del mismo.
 */
final class InformeAuditoriaInterna implements GeneradorDocumento
{
    use Concerns\ArmaContenidoComun;

    public function __construct(
        private readonly ResolverNarrativa $narrativa,
        private readonly MarkdownDocumento $markdown,
        private readonly ResultadoAuditoria $resultado,
    ) {}

    public function tipo(): TipoDocumento
    {
        return TipoDocumento::InformeAuditoria;
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    public function construir(Documento $documento, DocumentoVersion $version, array $parametros = []): ContenidoDocumento
    {
        $auditoria = $this->auditoriaDe($documento);
        $auditoria->loadMissing(['cerradaPor', 'sistema']);

        return new ContenidoDocumento(
            titulo: $documento->titulo,
            subtitulo: $auditoria->tipo->etiqueta().' · '.$auditoria->codigo,

            portada: [
                ...$this->portadaBase($documento, $version),
                'alcance' => $auditoria->alcance,
            ],

            // Sin cifras de implantación ni tabla de requisitos: lo que se cuenta
            // es la checklist de la auditoría, y va en `extras`.
            resumen: [],
            filas: [],

            limitaciones: [
                ...$this->limitacionesPropias(),
                ...$this->limitacionesBase($version),
            ],
            historial: $this->historialDe($documento),
            extras: [
                'auditoria' => $this->ficha($auditoria),
                'resultado' => $this->resultado->para($auditoria),
                'puntos' => $this->puntosConHallazgo($auditoria),
                'hallazgos' => $this->hallazgos($auditoria),
                'conclusiones' => $auditoria->conclusiones,
            ],
            textos: TextosDocumento::desdeMarkdown(
                $this->narrativa->paraDocumento($documento),
                $this->markdown,
            ),
        );
    }

    /**
     * La auditoría del informe, cerrada.
     *
     * Se vuelve a comprobar aquí aunque `PrepararInformeAuditoria` ya lo hiciera:
     * entre preparar y generar, la auditoría se puede reabrir, y un informe de una
     * auditoría abierta imprimiría una checklist que todavía cambia.
     */
    private function auditoriaDe(Documento $documento): Auditoria
    {
        $auditoria = $documento->auditoria;

        if (! $auditoria instanceof Auditoria) {
            throw DocumentoNoGenerable::sinAuditoria();
        }

        if ($auditoria->estado !== EstadoAuditoria::Cerrada) {
            throw DocumentoNoGenerable::auditoriaSinCerrar($auditoria->codigo);
        }

        return $auditoria;
    }

    /**
     * Lo que la cláusula 9.2.2 pide saber de la auditoría: quién, cuándo, sobre qué,
     * contra qué y cómo.
     *
     * @return array<string, mixed>
     */
    private function ficha(Auditoria $auditoria): array
    {
        return [
            'codigo' => $auditoria->codigo,
            'tipo' => $auditoria->tipo->etiqueta(),
            'sistemaCodigo' => $auditoria->sistema->codigo,
            'sistemaNombre' => $auditoria->sistema->nombre,
            'fecha' => $auditoria->fecha->format('d/m/Y'),
            'fechaCierre' => $auditoria->fecha_cierre?->format('d/m/Y'),
            'cerradaPor' => $auditoria->cerradaPor?->name,
            'auditor' => $auditoria->auditor,
            'equipo' => $auditoria->equipo,
            'alcance' => $auditoria->alcance,
            'criterios' => $auditoria->criterios,
            'metodo' => $auditoria->metodo,
        ];
    }

    /**
     * Las medidas que la auditoría no dio por buenas: no conformes y con
     * observación, en el orden del marco.
     *
     * Con la exigencia y el estado **congelados**: son los que la medida tenía el
     * día del cierre, y es contra ellos contra lo que se juzgó.
     *
     * @return list<array<string, mixed>>
     */
    private function puntosConHallazgo(Auditoria $auditoria): array
    {
        return AuditoriaPunto::query()
            ->select('auditoria_puntos.*')
            ->join('implantaciones', 'implantaciones.id', '=', 'auditoria_puntos.implantacion_id')
            ->join('requisitos', 'requisitos.id', '=', 'implantaciones.requisito_id')
            ->where('auditoria_puntos.auditoria_id', $auditoria->id)
            ->whereIn('auditoria_puntos.resultado', [
                ResultadoPunto::NoConforme->value,
                ResultadoPunto::Observacion->value,
            ])
            ->orderBy('requisitos.orden')
            ->orderBy('requisitos.codigo')
            ->with('implantacion.requisito')
            ->get()
            ->map(static fn (AuditoriaPunto $punto): array => [
                'codigo' => $punto->implantacion?->requisito?->codigo,
                'titulo' => $punto->implantacion?->requisito?->titulo,
                'resultado' => $punto->resultado->etiqueta(),
                'exigencia' => $punto->exigencia_congelada,
                'estado' => $punto->estado_congelado?->etiqueta(),
                'nota' => $punto->nota,
            ])
            ->values()
            ->all();
    }

    /**
     * Los hallazgos, con su tratamiento.
     *
     * **El tratamiento es el de hoy, no el del cierre**, y se declara: la no
     * conformidad se abre y avanza después de cerrar la auditoría. Un hallazgo que
     * exige tratamiento y no lo tiene se imprime como tal, porque es lo primero que
     * mira quien audite la siguiente.
     *
     * @return list<array<string, mixed>>
     */
    private function hallazgos(Auditoria $auditoria): array
    {
        return $auditoria->hallazgos()
            ->with(['punto.implantacion.requisito', 'noConformidad', 'mejora'])
            ->get()
            ->map(static function (Hallazgo $hallazgo): array {
                $tratamiento = match (true) {
                    $hallazgo->noConformidad !== null => $hallazgo->noConformidad->codigo
                        .' · '.$hallazgo->noConformidad->estado->etiqueta(),
                    $hallazgo->mejora !== null => $hallazgo->mejora->codigo
                        .' · '.$hallazgo->mejora->estado->etiqueta(),
                    $hallazgo->tipo->exigeNoConformidad() => 'Sin tratamiento abierto',
                    default => null,
                };

                return [
                    'tipo' => $hallazgo->tipo->etiqueta(),
                    'medida' => $hallazgo->punto?->implantacion?->requisito?->codigo,
                    'descripcion' => $hallazgo->descripcion,
                    'tratamiento' => $tratamiento,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Lo que este informe no puede afirmar.
     *
     * @return list<string>
     */
    private function limitacionesPropias(): array
    {
        return [
            'La herramienta **no comprueba que el alcance auditado cubra lo exigible**. La checklist se '
            .'genera desde las medidas exigibles al sistema, pero lo que se revisó es lo que el auditor '
            .'marcó: una medida **fuera de muestra** no se revisó y este informe **no afirma que esté '
            .'conforme**, y una **sin revisar** tampoco.',

            'Statera **no mantiene un programa anual de auditoría**. Este informe recoge una auditoría '
            .'concreta; la planificación del ciclo —frecuencia, rotación del alcance, importancia de los '
            .'procesos— que pide la cláusula 9.2.2 se lleva fuera de la herramienta.',

            'El resultado de cada medida se registra sin traza de **quién lo marcó**: la auditoría la firma '
            .'quien figura como auditor, no cada casilla de la checklist. La herramienta **no comprueba la '
            .'independencia ni la competencia** de quien audita.',

            'El **tratamiento de cada hallazgo** —la no conformidad o la mejora que se abrió— es el que '
            .'consta en la fecha de extracción de este documento, no el del cierre de la auditoría: el '
            .'tratamiento avanza después de cerrarla.',
        ];
    }
}
