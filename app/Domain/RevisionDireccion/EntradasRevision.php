<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion;

use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Contexto\AnalisisEnCurso;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Mejora\Models\Mejora;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Objetivo\Avance;
use App\Domain\Objetivo\Models\Objetivo;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Support\Carbon;

/**
 * Las siete entradas obligatorias de la cláusula 9.3.2, recogidas del producto.
 *
 * Es lo que paga el módulo, y es el motivo por el que el § 4.15 llevaba bloqueado
 * desde el principio: la norma cierra la lista de entradas y Statera tiene que
 * poder enseñarlas todas. Hasta el § 6.2 y el § 10.1 faltaban dos.
 *
 * | 9.3.2 | De dónde sale |
 * |---|---|
 * | a) Estado de las acciones de revisiones previas | `revision_tarea` de la revisión anterior |
 * | b) Cambios en cuestiones internas y externas | `analisis_contexto` (§ 4.1) |
 * | c) Necesidades de las partes interesadas | `partes_interesadas` (§ 4.1) |
 * | d) Desempeño: no conformidades, seguimiento y medición, auditorías, objetivos | § 4.13, § 4.14, § 4.12 y la 6.2 |
 * | e) Retroalimentación de las partes interesadas | `partes_interesadas`, **con limitación declarada** |
 * | f) Resultados de la apreciación de riesgos y estado del tratamiento | § 4.3 |
 * | g) Oportunidades de mejora | § 10.1 |
 *
 * **Devuelve arrays y cadenas, nunca modelos**, que es el mismo contrato que tiene
 * `InstantaneaContexto` y que el generador de documentos tiene con sus plantillas:
 * lo que se pinta y lo que se congela tienen que ser literalmente lo mismo. Sin
 * eso, la instantánea y la pantalla podrían divergir.
 *
 * **Y no lee los resúmenes del panel.** Podría —`RegistroNoConformidades::paraElPanel()`
 * cuenta casi lo mismo— y sería acoplar un acta que se entrega a un auditor a la
 * forma que hoy tiene una tarjeta. Lo que sí comparte son los **scopes**, que es
 * donde vive la regla: `NoConformidad::pendientesDeVerificar()` cuenta aquí lo
 * mismo que en el panel y que en la tabla, por construcción.
 */
final readonly class EntradasRevision
{
    public function __construct(private AnalisisEnCurso $contexto) {}

    /**
     * @return array<string, mixed>
     */
    public function para(RevisionDireccion $revision): array
    {
        return [
            'recogidasEn' => Carbon::now()->toIso8601String(),
            'periodo' => [
                'desde' => $revision->periodo_desde->toDateString(),
                'hasta' => $revision->periodo_hasta->toDateString(),
            ],
            'accionesPrevias' => $this->accionesPrevias($revision),
            'contexto' => $this->contexto(),
            'partesInteresadas' => $this->partesInteresadas(),
            'desempeno' => $this->desempeno($revision),
            'riesgos' => $this->riesgos(),
            'mejoras' => $this->mejoras(),
        ];
    }

    /**
     * a) El estado de las acciones de la revisión anterior.
     *
     * **Es la entrada que hace que la serie de actas signifique algo**: sin ella,
     * cada revisión empieza de cero y las decisiones de la anterior no se
     * comprueban nunca. Por eso las salidas son tareas y no texto: una decisión
     * con responsable y plazo se puede volver a mirar un año después.
     *
     * @return array<string, mixed>
     */
    private function accionesPrevias(RevisionDireccion $revision): array
    {
        $anterior = $revision->anterior();

        if (! $anterior instanceof RevisionDireccion) {
            return ['revision' => null, 'acciones' => [], 'abiertas' => 0];
        }

        $tareas = $anterior->tareas()->with('responsable')->get();

        return [
            'revision' => [
                'codigo' => $anterior->codigo,
                'fecha' => $anterior->fecha->format('d/m/Y'),
            ],
            'acciones' => $tareas
                ->map(fn (Tarea $tarea): array => [
                    'titulo' => $tarea->titulo,
                    'estado' => $tarea->estado->etiqueta(),
                    'tono' => $tarea->estado->tono(),
                    'responsable' => $tarea->responsable?->name,
                    'fecha' => $tarea->fecha_limite?->format('d/m/Y'),
                ])
                ->values()
                ->all(),
            'abiertas' => $tareas->filter(fn (Tarea $tarea): bool => ! $tarea->estado->esCerrada())->count(),
        ];
    }

    /**
     * b) Los cambios en las cuestiones internas y externas.
     *
     * Sale del análisis **aprobado**, no del borrador: lo que la dirección revisa
     * es lo que la organización declaró, y un DAFO a medio escribir no es un
     * cambio de contexto, es trabajo en curso.
     *
     * @return array<string, mixed>
     */
    private function contexto(): array
    {
        $analisis = $this->contexto->vigente();

        if (! $analisis instanceof AnalisisContexto) {
            return ['analisis' => null, 'cuestiones' => 0, 'clima' => null];
        }

        $instantanea = $analisis->instantanea ?? [];
        $dafo = is_array($instantanea['dafo'] ?? null) ? $instantanea['dafo'] : [];

        $cuestiones = 0;

        foreach ($dafo as $cuadrante) {
            $cuestiones += is_array($cuadrante) ? count($cuadrante) : 0;
        }

        return [
            'analisis' => [
                'etiqueta' => $analisis->etiqueta(),
                'fecha' => $analisis->fecha_analisis->format('d/m/Y'),
            ],
            'cuestiones' => $cuestiones,
            'dafo' => $dafo,
            'clima' => is_array($instantanea['clima'] ?? null) ? $instantanea['clima'] : null,
        ];
    }

    /**
     * c) y e) Las necesidades y expectativas de las partes interesadas.
     *
     * **Las dos entradas se recogen del mismo sitio, y eso es una limitación
     * declarada.** La 9.3.2 c) pide los cambios en sus necesidades y la e) pide la
     * retroalimentación —quejas, satisfacción, resultados de encuestas—, y Statera
     * sólo tiene lo primero: `requisitos_interesados` registra qué exige cada
     * parte, no qué ha dicho últimamente. El acta lo dice por escrito en vez de
     * rellenar el hueco con lo que hay al lado.
     *
     * @return array<string, mixed>
     */
    private function partesInteresadas(): array
    {
        $partes = ParteInteresada::query()
            ->vigentes()
            ->withCount('requisitos')
            ->orderBy('nombre')
            ->get();

        return [
            'total' => $partes->count(),
            'partes' => $partes
                ->map(fn (ParteInteresada $parte): array => [
                    'nombre' => $parte->nombre,
                    'tipo' => $parte->tipo->etiqueta(),
                    'ambito' => $parte->ambito->etiqueta(),
                    'requisitos' => (int) $parte->getAttribute('requisitos_count'),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * d) El desempeño y la eficacia del SGSI.
     *
     * Es la entrada más larga porque la norma la desglosa en cuatro: no
     * conformidades y acciones correctivas, seguimiento y medición, resultados de
     * auditoría y **cumplimiento de los objetivos de seguridad**. Ésta última es
     * una de las dos que faltaban hasta el § 6.2.
     *
     * Las auditorías se acotan al periodo revisado y el resto no: una no
     * conformidad abierta lo está hoy, independientemente de cuándo se detectara,
     * y acotarla escondería justo las que llevan años abiertas.
     *
     * @return array<string, mixed>
     */
    private function desempeno(RevisionDireccion $revision): array
    {
        $objetivos = Objetivo::query()->with('indicadores.ultimaMedicion')->orderBy('codigo')->get();

        return [
            'noConformidades' => [
                'total' => NoConformidad::query()->count(),
                'abiertas' => NoConformidad::query()->abiertas()->count(),
                'vencidas' => NoConformidad::query()->vencidas()->count(),
                'sinVerificar' => NoConformidad::query()->pendientesDeVerificar()->count(),
                'sinCausaRaiz' => NoConformidad::query()->sinCausaRaiz()->count(),
            ],
            'indicadores' => [
                'activos' => Indicador::query()->activos()->count(),
                'fueraDeObjetivo' => Indicador::query()->fueraDeObjetivo()->count(),
                'periodoSinMedir' => Indicador::query()->periodoSinMedir()->count(),
                'sinMedir' => Indicador::query()->sinMedir()->count(),
            ],
            'auditorias' => $this->auditorias($revision),
            'objetivos' => [
                'total' => $objetivos->count(),
                'vivos' => Objetivo::query()->vivos()->count(),
                'vencidos' => Objetivo::query()->vencidos()->count(),
                'sinIndicador' => Objetivo::query()->sinIndicador()->count(),
                'detalle' => $objetivos
                    ->map(function (Objetivo $objetivo): array {
                        $avance = Avance::de($objetivo->indicadores);

                        return [
                            'codigo' => $objetivo->codigo,
                            'titulo' => $objetivo->titulo,
                            'estado' => $objetivo->estado->etiqueta(),
                            'tono' => $objetivo->estado->tono(),
                            'avance' => $avance->etiqueta(),
                            'fecha' => $objetivo->fecha_objetivo?->format('d/m/Y'),
                        ];
                    })
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * Las auditorías celebradas dentro del periodo revisado.
     *
     * **Acotadas por fecha y no por estado**: una auditoría que se celebró en el
     * periodo y sigue abierta es exactamente lo que la dirección tiene que saber,
     * y filtrarla dejaría el acta diciendo que no hubo auditorías.
     *
     * @return array<string, mixed>
     */
    private function auditorias(RevisionDireccion $revision): array
    {
        $auditorias = Auditoria::query()
            ->whereBetween('fecha', [$revision->periodo_desde, $revision->periodo_hasta])
            ->withCount('hallazgos')
            ->orderBy('fecha')
            ->get();

        return [
            'total' => $auditorias->count(),
            'detalle' => $auditorias
                ->map(fn (Auditoria $auditoria): array => [
                    'codigo' => $auditoria->codigo,
                    'tipo' => $auditoria->tipo->etiqueta(),
                    'fecha' => $auditoria->fecha->format('d/m/Y'),
                    'estado' => $auditoria->estado->etiqueta(),
                    'tono' => $auditoria->estado->tono(),
                    'hallazgos' => (int) $auditoria->getAttribute('hallazgos_count'),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * f) Los resultados de la apreciación de riesgos y el estado del tratamiento.
     *
     * `sobreUmbral` resuelve el umbral él mismo desde la metodología vigente, que
     * es lo que garantiza que esta cifra y la del registro de riesgos digan lo
     * mismo.
     *
     * @return array<string, mixed>
     */
    private function riesgos(): array
    {
        return [
            'total' => Riesgo::query()->count(),
            'sobreUmbral' => Riesgo::query()->sobreUmbral()->count(),
            'sinValorar' => Riesgo::query()->sinValorar()->count(),
            'sinAceptar' => Riesgo::query()->sinAceptar()->count(),
            'residualSinRespaldo' => Riesgo::query()->residualSinRespaldo()->count(),
            'revisionVencida' => Riesgo::query()->revisionVencida()->count(),
        ];
    }

    /**
     * g) Las oportunidades de mejora continua.
     *
     * La última entrada que faltaba, y la que llegó con el § 10.1. Antes de él
     * sólo existían dentro de una auditoría, así que esta fila del acta se habría
     * quedado en «las que algún auditor escribió», que no es lo que pide la 9.3.
     *
     * @return array<string, mixed>
     */
    private function mejoras(): array
    {
        $abiertas = Mejora::query()
            ->abiertas()
            ->with('responsable')
            ->orderBy('codigo')
            ->get();

        return [
            'total' => Mejora::query()->count(),
            'abiertas' => $abiertas->count(),
            'sinEmpezar' => Mejora::query()->sinEmpezar()->count(),
            'detalle' => $abiertas
                ->map(fn (Mejora $mejora): array => [
                    'codigo' => $mejora->codigo,
                    'titulo' => $mejora->titulo,
                    'estado' => $mejora->estado->etiqueta(),
                    'tono' => $mejora->estado->tono(),
                    'origen' => $mejora->origen->etiqueta(),
                    'responsable' => $mejora->responsable?->name,
                ])
                ->values()
                ->all(),
        ];
    }
}
