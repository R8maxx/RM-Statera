<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\AuditoriaPunto;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\Alineacion;
use App\Http\Resources\Enums\MetodoAccion;
use Illuminate\Database\Eloquent\Builder;

/**
 * La checklist de una auditoría: § 4.12, «checklists generadas desde el catálogo».
 *
 * **Es el primer `Recurso` acotado a un padre del producto**, y por eso la
 * auditoría entra por el constructor. `Recurso::consulta()` no recibe argumentos
 * y sólo lo llama `ConsultaRecurso`: cambiar esa firma contaminaría las once
 * implementaciones existentes para que la use una. No se declara inyectable
 * —`ActivoRecurso` sí lo es— porque con un parámetro de modelo el contenedor no
 * lo resuelve; se instancia con `new`, como `TareaRecurso` y `DocumentoRecurso`.
 *
 * **El aislamiento tiene aquí un eje que no existía.** Las tres capas de siempre
 * tapan el cruce entre organizaciones; entre dos auditorías de la misma
 * organización no hay nada, así que el `where` de la consulta no es una comodidad
 * de filtrado: es la frontera. Lo mismo vale para la acción masiva, que acota por
 * `auditoria_id` además de por los ids que le llegan.
 *
 * **Y por eso esta pantalla es una ruta propia y no un bloque de la ficha.** Son
 * 52 medidas en un sistema de categoría básica y unas 122 en uno de ISO —los 93
 * controles del Anexo A más las cláusulas 4 a 10, que es el mismo 122-contra-93
 * que ya mordió a la SoA—. A ese tamaño hacen falta filtros, orden y sobre todo
 * la acción masiva, que es como se recorre una auditoría de verdad. Precedente de
 * forma: `/tareas/tablero` y `/activos/etiquetas`.
 *
 * @extends Recurso<AuditoriaPunto>
 */
class ChecklistRecurso extends Recurso
{
    public function __construct(private readonly Auditoria $auditoria) {}

    public function clave(): string
    {
        /*
         * Estable, sin el id de la auditoría dentro. Es el nombre con el que la
         * vista de columnas se guarda en el navegador y con el que se nombra el
         * CSV: una clave dinámica guardaría una vista por auditoría y quien
         * ordena sus columnas las perdería en la siguiente. Quien distingue la
         * caché de la definición es el sufijo que pasa el controlador.
         */
        return 'checklist';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Medida revisada',
            plural: 'Checklist',
            descripcion: 'Una línea por medida exigible. Lo que no se revisa se queda «sin revisar», que no es «conforme».',
            vacio: 'La checklist está vacía. Genérala desde la ficha de la auditoría.',
        );
    }

    /** @return Builder<AuditoriaPunto> */
    public function consulta(): Builder
    {
        /*
         * El código y el orden del requisito se traen por join, como en
         * `ImplantacionRecurso` y por el mismo motivo: son la ordenación natural
         * —`orden` es la secuencia del marco, y ordenar por el código en texto
         * pondría `op.acc.10` antes que `op.acc.2`— y viven en el catálogo.
         *
         * El `where` de la auditoría es la frontera, no un filtro.
         */
        return AuditoriaPunto::query()
            ->select('auditoria_puntos.*')
            ->addSelect([
                'requisitos.codigo as codigo',
                'requisitos.orden as orden_requisito',
            ])
            ->join('implantaciones', 'implantaciones.id', '=', 'auditoria_puntos.implantacion_id')
            ->join('requisitos', 'requisitos.id', '=', 'implantaciones.requisito_id')
            ->where('auditoria_puntos.auditoria_id', $this->auditoria->id)
            ->with(['implantacion.requisito.padre', 'implantacion.responsable'])
            ->withCount('hallazgos');
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Medida')
                ->ordenable('orden_requisito')
                ->anclada()
                ->ancho('8rem')
                ->formato(fn (AuditoriaPunto $fila): ?string => $fila->implantacion?->requisito?->codigo),

            Columna::texto('requisito', 'Título')
                ->formato(fn (AuditoriaPunto $fila): ?string => $fila->implantacion?->requisito?->titulo),

            Columna::texto('grupo', 'Grupo')
                ->oculta()
                ->formato(fn (AuditoriaPunto $fila): ?string => $fila->implantacion?->requisito?->padre?->codigo),

            Columna::badge('resultado', 'Resultado')
                ->ordenable()
                ->formato(fn (AuditoriaPunto $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->resultado->value,
                    $fila->resultado->etiqueta(),
                    $fila->resultado->tono(),
                    $fila->resultado->icono(),
                )),

            /*
             * El estado que cuenta para esta auditoría, no el de hoy: si está
             * cerrada sale el congelado. Sin eso, la checklist de marzo diría en
             * octubre lo que la medida es ahora y no lo que el auditor vio.
             */
            Columna::badge('estado', 'Estado de la medida')
                ->ayuda('El que tenía la medida cuando se cerró la auditoría.')
                ->formato(function (AuditoriaPunto $fila): ?ValorEtiquetado {
                    $estado = $fila->estadoAuditado();

                    return $estado === null ? null : new ValorEtiquetado(
                        $estado->value,
                        $estado->etiqueta(),
                        $estado->tono(),
                        $estado->icono(),
                    );
                }),

            Columna::numero('hallazgos', 'Hallazgos')
                ->alinear(Alineacion::Derecha)
                ->ancho('6rem')
                ->formato(fn (AuditoriaPunto $fila): int => (int) $fila->getAttribute('hallazgos_count')),

            Columna::texto('responsable', 'Responsable')
                ->oculta()
                ->formato(fn (AuditoriaPunto $fila): ?string => $fila->implantacion?->responsable?->name),

            Columna::texto('nota', 'Nota')->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'requisitos.codigo' => 'codigo',
                'requisitos.titulo' => 'requisito',
            ])->placeholder('Buscar por código o título…'),

            Filtro::multiSelect('resultado', 'Resultado', array_map(
                static fn (ResultadoPunto $resultado): Opcion => new Opcion(
                    $resultado->value,
                    $resultado->etiqueta(),
                ),
                ResultadoPunto::cases(),
            )),

            /*
             * El grupo sale del catálogo y no de las filas de esta auditoría: una
             * lista de opciones derivada de las filas viajaría dentro de la
             * definición, que es justo lo que se cachea, y acabaría enseñando los
             * grupos de otra auditoría.
             */
            Filtro::texto('grupo', 'Grupo')->campo('requisitos.codigo'),

            Filtro::porScope('sin_hallazgo', 'Sin hallazgo que lo explique', 'sinHallazgo')
                ->enColumna('hallazgos'),

            Filtro::porScope('revisados', 'Ya revisadas', 'revisados')
                ->enColumna('resultado'),
        ];
    }

    /**
     * La acción masiva marca **conforme**, y sólo conforme.
     *
     * «No conforme» y «observación» piden un hallazgo detrás que las explique
     * —lo dice `ResultadoPunto::exigeHallazgo()` y lo cuenta
     * `AuditoriaPunto::scopeSinHallazgo()`—, así que marcar cuarenta de golpe
     * fabricaría cuarenta huecos. Es el mismo argumento que dejó `descartada`
     * fuera de la acción masiva de tareas: un motivo escrito una vez para
     * cincuenta filas no es un motivo.
     *
     * La URL no lleva el id de la auditoría: la página la compone, como ya hacen
     * `implantaciones/Index.vue` y `activos/Index.vue`. Meterlo aquí sería meter
     * el padre dentro de la definición, que es lo que la clave de caché existe
     * para tolerar y no para invitar.
     *
     * @return list<Accion>
     */
    public function accionesMasivas(): array
    {
        return [
            (new Accion('conformes', 'Marcar conformes', 'checklist/resultado', MetodoAccion::Post))
                ->icono('CircleCheck')
                ->permiso(Permiso::AuditoriasGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [];
    }

    public function ordenPorDefecto(): string
    {
        return 'codigo';
    }

    /**
     * La checklist entera en una página, y por un motivo concreto.
     *
     * `DataTable` limpia la selección cada vez que cambia `meta`, así que
     * paginar la borra: si la checklist no cabe entera, «marcar veinte conformes
     * de golpe» obliga a empezar de nuevo en cada página. 52 medidas en categoría
     * básica caben en 100; las ~122 de un sistema ISO necesitan el escalón de
     * 200, que no existe en el resto del producto y aquí sí.
     */
    public function porPagina(): int
    {
        return 100;
    }

    /** @return list<int> */
    public function tamanosPagina(): array
    {
        return [25, 50, 100, 200];
    }
}
