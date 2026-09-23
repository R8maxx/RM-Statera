<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\UmbralTolerable;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\MetodoAccion;
use Illuminate\Database\Eloquent\Builder;

/**
 * El BIA de cada servicio: § 4.11.
 *
 * **Sin filtro de búsqueda.** A diferencia del resto de tablas del producto,
 * lo que se busca aquí es un servicio y no un texto libre: el inventario ya es
 * donde se busca por nombre, y esta tabla es corta —un servicio, un BIA— y se
 * recorre entera antes de que un cuadro de texto compre nada.
 *
 * **`rto_incoherente` y `revision_vencida` son los mismos scopes del
 * modelo**, no una condición aparte escrita aquí: si el filtro divergiera de
 * `BiaServicio::rtoIncoherente()`, la tabla y la comprobación del dominio
 * dirían cosas distintas de la misma fila.
 *
 * @extends Recurso<BiaServicio>
 */
final class BiaServicioRecurso extends Recurso
{
    public function clave(): string
    {
        return 'continuidad_bia';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'BIA',
            plural: 'BIA de servicios',
            descripcion: 'El análisis de impacto en el negocio de cada servicio: RTO, RPO y el umbral tolerable que se deriva de sus cinco tramos de impacto. § 4.11.',
            vacio: 'No hay ningún BIA registrado. Sin él no hay RTO que planificar ni umbral con el que compararlo.',
        );
    }

    /** @return Builder<BiaServicio> */
    public function consulta(): Builder
    {
        // El join es sólo para poder ordenar por el nombre del servicio: la
        // columna se sigue pintando desde la relación cargada con `with()`,
        // como en el resto del producto.
        return BiaServicio::query()
            ->select('bia_servicios.*')
            ->join('activos', 'activos.id', '=', 'bia_servicios.activo_id')
            ->with(['activo', 'responsable']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('servicio', 'Servicio')
                ->ordenable('activos.nombre')
                ->anclada()
                ->formato(fn (BiaServicio $fila): ?string => $fila->activo?->nombre),

            Columna::numero('rto', 'RTO (h)')
                ->ancho('7rem')
                ->ayuda('El tiempo de recuperación objetivo que se ha declarado, en horas.')
                ->formato(fn (BiaServicio $fila): int => $fila->rto_horas),

            Columna::numero('rpo', 'RPO (h)')
                ->ancho('7rem')
                ->ayuda('La pérdida de datos objetivo, en horas.')
                ->formato(fn (BiaServicio $fila): int => $fila->rpo_horas),

            Columna::texto('umbral', 'Umbral tolerable')
                ->ancho('10rem')
                ->ayuda('El MTPD: el primer horizonte en el que el impacto se vuelve intolerable.')
                ->formato(fn (BiaServicio $fila): string => UmbralTolerable::de($fila)?->etiqueta()
                    ?? 'Sin umbral intolerable'),

            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->ancho('9rem')
                ->formato(fn (BiaServicio $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->tono(),
                    $fila->estado->icono(),
                )),

            Columna::fecha('revision', 'Revisión')
                ->ordenable('fecha_revision')
                ->formato(fn (BiaServicio $fila): ?string => $fila->fecha_revision?->toDateString()),

            Columna::texto('responsable', 'Responsable')
                ->oculta()
                ->formato(fn (BiaServicio $fila): ?string => $fila->responsable?->name),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoBia $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoBia::cases(),
            )),

            /*
             * Por scope, con la misma condición que `rtoIncoherente()`: es lo
             * que garantiza que pulsar la cifra del panel enseñe exactamente
             * esa cifra, y que el filtro nunca diverja de la comprobación del
             * dominio.
             */
            Filtro::porScope('rto_incoherente', 'RTO incoherente', 'rtoIncoherente')->enColumna('rto'),
            Filtro::porScope('revision_vencida', 'Revisión vencida', 'revisionVencida')->enColumna('revision'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/continuidad/bia/{id}'),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Registrar BIA', '/continuidad/bia/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::ContinuidadGestionar->value),
        ];
    }

    /**
     * La CLAVE de la columna, `revision`, y no `fecha_revision`.
     *
     * `ConsultaRecurso::ordenPorDefecto()` busca esta cadena entre las claves
     * de `columnas()` para resolver el campo real de ordenación
     * (`campoOrden()`); con el nombre de la columna en la base en vez de con
     * su clave, la búsqueda no encuentra nada, la flecha de la cabecera no se
     * enciende nunca y, en cuanto se pagina o se filtra, spatie descarta el
     * `sort=fecha_revision` que llega por la URL por no ser un
     * `allowedSort` declarado —el `ORDER BY` desaparece en silencio—. Mismo
     * fallo que ya evita el docblock de `ordenPorDefecto()` en
     * `ConsultaRecurso`.
     */
    public function ordenPorDefecto(): string
    {
        return 'revision';
    }
}
