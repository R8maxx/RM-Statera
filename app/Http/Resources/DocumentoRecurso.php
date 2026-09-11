<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\MetodoAccion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Los documentos que se le entregan al auditor.
 *
 * La columna que de verdad se mira es «Versión»: un documento sin ninguna
 * emitida es un documento que no se ha entregado nunca, por muchos borradores
 * que se hayan generado.
 *
 * @extends Recurso<Documento>
 */
final class DocumentoRecurso extends Recurso
{
    public function clave(): string
    {
        return 'documentos';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Documento',
            plural: 'Documentos',
            descripcion: 'Las declaraciones y los informes que se entregan. Se generan a partir del registro; no se mantienen a mano.',
            vacio: 'Todavía no hay ningún documento. La SoA y la DdA se generan desde aquí a partir de lo que ya está registrado.',
        );
    }

    /**
     * Las cifras de versión llegan por subconsulta y no por `join`.
     *
     * Un `join` contra `documento_versiones` multiplicaría las filas —un
     * documento con cuatro entregas saldría cuatro veces— y la paginación
     * contaría mal. Es el mismo razonamiento que llevó a `Filtro::porRelacion()`
     * en activos.
     *
     * @return Builder<Documento>
     */
    public function consulta(): Builder
    {
        /*
         * La subconsulta no pasa por el scope global de Eloquent, pero sí por
         * RLS: `documento_versiones` lleva `FORCE ROW LEVEL SECURITY`, así que
         * la base filtra por la organización de la sesión aunque el SQL sea
         * crudo. Es justo el caso para el que existe la tercera capa.
         */
        $versiones = fn (string $expresion): string => "(select {$expresion} from documento_versiones dv where dv.documento_id = documentos.id)";

        return Documento::query()
            ->with(['sistema.marco', 'responsable'])
            ->select('documentos.*')
            ->selectRaw($versiones('max(numero)').' as ultima_version')
            ->selectRaw($versiones('max(emitida_en)').' as ultima_emision')
            // `max()` sobre un texto sería un orden alfabético sin sentido, y aquí
            // da igual: el índice único parcial garantiza un solo borrador por
            // documento, así que agrega sobre una fila como mucho.
            ->selectRaw($versiones('max(case when dv.numero is null then dv.estado_generacion end)').' as estado_borrador');
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('8rem'),
            Columna::texto('titulo', 'Título')->ordenable(),
            Columna::badge('tipo', 'Tipo')
                ->ordenable()
                ->formato(fn (Documento $documento): ValorEtiquetado => new ValorEtiquetado(
                    $documento->tipo->value,
                    $documento->tipo->etiquetaCorta(),
                    $documento->tipo->tono(),
                )),
            Columna::texto('sistema', 'Sistema')
                ->formato(fn (Documento $documento): ?string => $documento->sistema?->nombre),
            // Un documento sin versión emitida no se ha entregado nunca, por
            // muchos borradores que se hayan generado. Se dice, no se deja en
            // blanco.
            Columna::badge('version', 'Versión')
                ->ordenable('ultima_version')
                ->ayuda('La última versión entregada. Los borradores no cuentan: se regeneran.')
                ->formato(fn (Documento $documento): ValorEtiquetado => $this->version($documento)),
            Columna::fechaHora('ultima_emision', 'Emitida')->ordenable(),
            Columna::badge('generacion', 'Generación')
                ->ayuda('En qué anda el borrador: si se está generando, si está listo para emitir o si falló.')
                ->formato(fn (Documento $documento): ValorEtiquetado => $this->generacion($documento)),
            Columna::badge('clasificacion', 'Clasificación')
                ->oculta()
                ->formato(fn (Documento $documento): ValorEtiquetado => new ValorEtiquetado(
                    $documento->clasificacion->value,
                    $documento->clasificacion->etiqueta(),
                    $documento->clasificacion->tono(),
                )),
            Columna::texto('responsable', 'Responsable')
                ->oculta()
                ->formato(fn (Documento $documento): ?string => $documento->responsable?->name),
            Columna::fechaHora('created_at', 'Alta')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', ['codigo' => 'codigo', 'titulo' => 'titulo'])
                ->placeholder('Buscar por código o título…'),
            Filtro::multiSelect('tipo', 'Tipo', array_map(
                static fn (TipoDocumento $tipo): Opcion => new Opcion($tipo->value, $tipo->etiqueta()),
                TipoDocumento::cases(),
            )),
            Filtro::select('sistema_id', 'Sistema', fn (): array => Sistema::query()
                ->orderBy('codigo')
                ->get()
                ->map(fn (Sistema $sistema): Opcion => new Opcion(
                    (string) $sistema->id,
                    "{$sistema->codigo} — {$sistema->nombre}",
                ))
                ->all())->enColumna('sistema'),
            Filtro::multiSelect('clasificacion', 'Clasificación', array_map(
                static fn (ClasificacionDocumental $c): Opcion => new Opcion($c->value, $c->etiqueta()),
                ClasificacionDocumental::cases(),
            )),
            Filtro::rangoFechas('created_at', 'Alta'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        /*
         * Sin «Eliminar»: que se pueda borrar o no depende de si hay versiones
         * emitidas, y ese matiz sólo se ve en la ficha. Un menú contextual que
         * ofrece borrar y luego se niega es peor que no ofrecerlo.
         */
        return [
            Accion::ver('/documentos/{id}'),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nuevo documento', '/documentos/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::DocumentosGenerar->value),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'codigo';
    }

    private function version(Documento $documento): ValorEtiquetado
    {
        $numero = $documento->getAttribute('ultima_version');

        if ($numero === null) {
            return new ValorEtiquetado(null, 'Sin emitir', 'no_iniciado');
        }

        return new ValorEtiquetado((string) $numero, "v{$numero}", 'implantado');
    }

    /**
     * El estado del borrador, que es distinto de «tener versión emitida».
     *
     * «Al día» significa que no hay ningún borrador pendiente: o no se ha pedido
     * ninguno, o el último ya se emitió.
     */
    private function generacion(Documento $documento): ValorEtiquetado
    {
        $estado = $documento->getAttribute('estado_borrador');

        if ($estado === null) {
            return new ValorEtiquetado(null, 'Al día', 'no_aplica');
        }

        $generacion = EstadoGeneracion::from((string) $estado);

        return new ValorEtiquetado(
            $generacion->value,
            $generacion === EstadoGeneracion::Generada ? 'Borrador listo' : $generacion->etiqueta(),
            $generacion->tono(),
        );
    }
}
