<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Tarea\Coste;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Plazo;
use App\Http\Resources\Implantacion\Correspondencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * El plan de adecuación del ENS: lo que falta, quién lo lleva y para cuándo.
 *
 * **Hace la pregunta contraria a una declaración.** La DdA dice qué medidas se
 * exigen y en qué estado está cada una; el plan lista **sólo las que no están
 * implantadas** y, al lado de cada una, el trabajo que hay apuntado detrás. Es
 * el documento que junta el § 4.4 con el § 4.7, y el que la dirección lee cuando
 * hay que decidir presupuesto.
 *
 * **Las cifras no se heredan de las declaraciones**, y por eso `resumen()` es
 * abstracto en `DocumentoCalculado`: sobre filas que son todas pendientes por
 * construcción, «porcentaje implantado» daría siempre cero y «excluidos» también,
 * dos números que no dicen nada de la realidad y que saldrían impresos al lado de
 * una tabla que sí la cuenta.
 *
 * **Cuántas se exigen y cuántas están hechas son el denominador**, no una cifra
 * de otro alcance. Se cuentan sobre `consultaDeRequisitos()`, que es exactamente
 * el universo del que sale la tabla, y se imprimen juntas: «de 52 exigidas hay 19
 * implantadas; este plan cubre las 33 restantes». Sin el denominador, una tabla
 * de 33 filas se lee como si al sistema se le exigieran 33 medidas.
 *
 * **Y el coste no se suma por columnas.** `implantacion_tarea` es N:M, así que la
 * misma tarea puede aparecer en varias medidas; sumar la columna presupuestaría
 * tres veces una tarea que cubre tres medidas. El total lo calcula
 * `Tarea\Coste::total()`, sobre tareas distintas, y la diferencia entre ese total
 * y la suma de la columna va **declarada en las limitaciones**: dos números que
 * no cuadran y nadie explica se leen como un error de la herramienta.
 */
final class PlanAdecuacionEns extends DocumentoCalculado
{
    use Concerns\ArmaFilaDelAnexoII;

    public function tipo(): TipoDocumento
    {
        return TipoDocumento::PlanAdecuacionEns;
    }

    protected function tipoDeRequisito(): string
    {
        return 'medida';
    }

    /**
     * Sólo lo exigible que no está implantado, con su trabajo detrás.
     *
     * Por el scope `pendientes()` y no repitiendo la condición: es el mismo que
     * cuenta la cifra del panel y el que aplica el filtro de la tabla.
     *
     * El eager load de tareas va **acotado a las abiertas**, y por el scope
     * `abiertas()`: sin acotar traería también las hechas y las descartadas para
     * descartarlas luego en PHP, y un plan que presupuestara trabajo ya cerrado
     * pediría dinero por algo que ya se hizo.
     *
     * @return Collection<int, Implantacion>
     */
    protected function implantaciones(Documento $documento): Collection
    {
        return $this->consultaDeRequisitos($documento)
            ->pendientes()
            ->with([
                'requisito.padre',
                'requisito.marco',
                'responsable',
                'evidencias',
                'riesgos',
                'tareas' => fn ($tareas) => $tareas->abiertas(),
                'tareas.responsable',
            ])
            ->orderBy('requisitos.orden')
            ->orderBy('requisitos.id')
            ->get();
    }

    public function construir(Documento $documento, DocumentoVersion $version, array $parametros = []): ContenidoDocumento
    {
        $implantaciones = $this->implantaciones($documento);
        $correspondencias = $this->correspondenciasDe($implantaciones);

        $filas = $implantaciones
            ->map(fn (Implantacion $i): FilaRequisito => $this->fila($i, $correspondencias))
            ->values()
            ->all();

        $sistema = $documento->sistema;

        return new ContenidoDocumento(
            titulo: $documento->titulo,
            subtitulo: 'Esquema Nacional de Seguridad — RD 311/2022, plan de adecuación',
            portada: [
                ...$this->portadaBase($documento, $version),
                'alcance' => $sistema?->alcance_declarado,
                'categoria' => $sistema?->categoria()?->etiqueta(),
            ],
            resumen: $this->resumen($documento, $filas),
            filas: $filas,
            limitaciones: [
                'Este plan recoge **lo que el registro sabe**: la fecha objetivo de cada medida y las '
                .'tareas abiertas vinculadas a ella. Una medida sin fecha o sin trabajo apuntado figura '
                .'como tal, y eso **no significa que no se esté haciendo nada**: significa que no consta.',

                'El coste de cada medida es la suma de sus tareas abiertas con coste estimado. **Una '
                .'misma tarea puede cubrir varias medidas** —una sola actuación hace avanzar a la vez '
                .'un control de ISO y varias medidas del ENS—, así que la columna la imputa a cada una '
                .'y el total la cuenta **una sola vez**: la suma de la columna es, por tanto, mayor que '
                .'el total. Las tareas sin coste estimado no suman, y su número se indica junto al total.',

                'La herramienta **no comprueba que el plan sea viable**: no contrasta plazos contra '
                .'capacidad, no ordena las medidas por dependencia entre ellas y no exige que toda '
                .'medida pendiente tenga fecha o responsable.',

                /*
                 * Esta frase enumeraba lo que el calendario SÍ recoge —«tareas y
                 * evidencias»— y se quedó corta en cuanto el § 4.5 añadió
                 * `Fuente::Documento`: pasó a ser falsa en el PDF que se le
                 * entrega al auditor, que es el fallo que este proyecto ya ha
                 * pagado cinco veces. Una enumeración dentro de una limitación
                 * envejece cada vez que el producto crece, así que se quita: lo
                 * que hay que declarar es lo que **falta**, que es estable.
                 * Mismo tratamiento que recibió la justificación de inclusión de
                 * la SoA cuando empezó a imprimir un origen más.
                 */
                /*
                 * **Reescrita, no borrada.** El § 4.16 la volvió falsa: el
                 * calendario ya recoge la fecha objetivo de cada medida
                 * pendiente. Lo que se declara ahora es lo que sigue faltando,
                 * que es lo estable — y sin enumerar lo que el calendario SÍ
                 * recoge, que es la lección que la versión anterior de esta
                 * misma frase dejó escrita.
                 *
                 * **Y reescrita otra vez con el § 4.11**, que la volvió falsa
                 * por segunda vez: las pruebas de continuidad ya entran en el
                 * calendario. De lo que faltaba sólo queda un módulo.
                 */
                'El **calendario de obligaciones** (§ 4.16) recoge la fecha objetivo de cada medida '
                .'pendiente, así que lo que aquí figura como fuera de plazo aparece también en el '
                .'aviso diario y en la vista de mes. Lo que ese calendario **todavía no recoge** es '
                .'la reevaluación de proveedores (§ 4.9), cuyo módulo no existe.',

                ...$this->limitacionesBase($version, $filas),
            ],
            historial: $this->historialDe($documento),
            textos: $this->textosDe($documento),
        );
    }

    /** @param  array<int, list<Correspondencia>>  $correspondencias */
    protected function fila(Implantacion $implantacion, array $correspondencias): FilaRequisito
    {
        $tareas = $implantacion->tareas;

        return $this->filaDelAnexoII(
            $implantacion,
            $correspondencias,
            fechaObjetivo: $implantacion->fecha_objetivo?->format('d/m/Y'),
            tareas: $tareas
                ->map(static fn (Tarea $tarea): string => $tarea->titulo.' ('.Plazo::de($tarea)->etiqueta.')')
                ->values()
                ->all(),
            // Nulo y no «0,00 €» cuando nadie ha estimado nada: un cero dice que
            // sale gratis, y lo que pasa es que no se sabe lo que cuesta.
            costeEstimado: Coste::total($tareas) > 0.0 ? Coste::escribir(Coste::total($tareas)) : null,
            riesgos: $implantacion->riesgos
                ->map(static fn (Riesgo $riesgo): string => $riesgo->codigo)
                ->values()
                ->all(),
        );
    }

    /**
     * Las cifras del plan, con el denominador delante.
     *
     * @param  list<FilaRequisito>  $filas
     * @return array<string, mixed>
     */
    protected function resumen(Documento $documento, array $filas): array
    {
        $exigibles = $this->consultaDeRequisitos($documento)->aplicables()->count();

        /*
         * Por resta y no por una segunda consulta con la condición contraria:
         * `pendientes()` es exactamente «aplicable y no implantada», así que lo
         * implantado es lo que queda. Escribir `where estado = implantado` aquí
         * sería la misma regla por segunda vez, y el día que una cambiara las
         * dos cifras dejarían de sumar el total.
         */
        $implantadas = max(0, $exigibles - count($filas));

        $tareas = $this->tareasDelPlan($documento);

        return [
            'total' => count($filas),
            'enElMarco' => $this->requisitosDelMarco($documento),
            'exigibles' => $exigibles,
            'implantadas' => $implantadas,
            'sinFecha' => count(array_filter(
                $filas,
                static fn (FilaRequisito $fila): bool => $fila->fechaObjetivo === null,
            )),
            'fueraDePlazo' => $this->consultaDeRequisitos($documento)->objetivoVencido()->count(),
            'sinTrabajo' => count(array_filter(
                $filas,
                static fn (FilaRequisito $fila): bool => $fila->tareas === [],
            )),
            'tareas' => $tareas->count(),
            'costeTotal' => Coste::escribir(Coste::total($tareas)),
            'costeSinEstimar' => Coste::sinEstimar($tareas),
            // Sin el tramo «Implantado», que sobre esta tabla es un cero fijo.
            'segmentos' => $this->segmentosDe($filas, [
                EstadoImplantacion::Planificado,
                EstadoImplantacion::EnProgreso,
                EstadoImplantacion::NoIniciado,
            ]),
        ];
    }

    /**
     * Las tareas abiertas que sostienen el plan, **cada una una sola vez**.
     *
     * Aquí está el fallo caro del documento y por eso la consulta es ésta y no
     * una suma de las columnas: la deduplicación la hace SQL sobre las tareas
     * vinculadas a **alguna** medida pendiente de este sistema. Una tarea que
     * cubre tres medidas es una fila, no tres.
     *
     * El subconsulta sale de `consultaDeRequisitos()` con el scope `pendientes()`
     * aplicado, que es literalmente el mismo conjunto del que salen las filas: no
     * puede colarse trabajo que el documento no enseñe.
     *
     * @return Collection<int, Tarea>
     */
    private function tareasDelPlan(Documento $documento): Collection
    {
        return Tarea::query()
            ->abiertas()
            ->whereHas('implantaciones', fn (Builder $consulta) => $consulta->whereIn(
                'implantaciones.id',
                $this->consultaDeRequisitos($documento)->pendientes()->select('implantaciones.id'),
            ))
            ->get();
    }
}
