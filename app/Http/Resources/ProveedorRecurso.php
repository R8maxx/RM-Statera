<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Proveedor\Enums\EstadoProveedor;
use App\Domain\Proveedor\Models\Proveedor;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\Alineacion;
use App\Http\Resources\Enums\MetodoAccion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Los proveedores y terceros (§ 4.9).
 *
 * **El rojo es de la reevaluación y de nada más**: una próxima evaluación ya
 * pasada es una prueba de que el tercero cumple que ha dejado de valer. Un
 * proveedor rechazado o sin evaluar no se pinta de alarma.
 *
 * Los activos que presta llegan por subconsulta y no por join, por lo mismo que
 * los ocupantes de un puesto: el join repetiría el proveedor.
 *
 * @extends Recurso<Proveedor>
 */
final class ProveedorRecurso extends Recurso
{
    public function clave(): string
    {
        return 'proveedores';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Proveedor',
            plural: 'Proveedores',
            descripcion: 'Con qué terceros trabaja la organización, qué le prestan, cuándo se comprobó su contrato por última vez y cuándo toca volver a hacerlo.',
            vacio: 'No hay proveedores registrados. A.5.19 y op.ext piden saber con quién se trabaja antes de poder evaluarlo.',
        );
    }

    /** @return Builder<Proveedor> */
    public function consulta(): Builder
    {
        return Proveedor::query()
            ->select('proveedores.*')
            ->selectRaw('(select count(*) from activos a where a.proveedor_id = proveedores.id) as activos_prestados')
            ->with('responsable:id,name');
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('7rem'),

            Columna::texto('nombre', 'Proveedor')->ordenable(),

            Columna::badge('estado', 'Estado')
                ->ancho('10rem')
                ->formato(fn (Proveedor $fila): ValorEtiquetado => new ValorEtiquetado(
                    $fila->estado->value,
                    $fila->estado->etiqueta(),
                    $fila->estado->tono(),
                    $fila->estado->icono(),
                )),

            Columna::badge('criticidad', 'Criticidad')
                ->ancho('8rem')
                ->ayuda('La mayor entre la que dan los activos que presta y la declarada. Decide cada cuánto se reevalúa.')
                ->formato(function (Proveedor $fila): ValorEtiquetado {
                    $criticidad = $fila->criticidad();

                    return new ValorEtiquetado($criticidad->value, $criticidad->etiqueta(), $criticidad->tono());
                }),

            Columna::texto('servicio_prestado', 'Servicio')->oculta(),

            Columna::texto('nube', 'Nube')
                ->ancho('9rem')
                ->formato(fn (Proveedor $fila): string => $fila->es_nube ? (string) $fila->modelo_nube?->etiqueta() : 'No'),

            Columna::texto('ubicacion_datos', 'Datos en')
                ->oculta()
                ->formato(fn (Proveedor $fila): string => $fila->ubicacion_datos->etiqueta()),

            Columna::texto('activos_prestados', 'Activos')
                ->alinear(Alineacion::Derecha)
                ->ancho('6rem')
                ->ayuda('Activos del inventario que presta. De su valoración sale el mínimo de criticidad.')
                ->formato(fn (Proveedor $fila): string => (string) (int) $fila->getAttribute('activos_prestados')),

            Columna::badge('proxima_evaluacion', 'Próxima evaluación')
                ->ordenable()
                ->ancho('11rem')
                ->formato(fn (Proveedor $fila): ?ValorEtiquetado => $this->plazo($fila)),

            Columna::texto('responsable', 'Responsable')
                ->formato(fn (Proveedor $fila): ?string => $fila->responsable?->name),
        ];
    }

    /**
     * La próxima evaluación con su tono: rojo si ya pasó, y el texto de «sin
     * evaluar» cuando no la hay, que no es lo mismo que no tener que hacerla.
     */
    private function plazo(Proveedor $fila): ?ValorEtiquetado
    {
        if (! $fila->estado->seReevalua()) {
            return null;
        }

        if ($fila->proxima_evaluacion === null) {
            return new ValorEtiquetado('sin_evaluar', 'Sin evaluar', 'no_iniciado', 'Circle');
        }

        $vencida = $fila->proxima_evaluacion->lt(Carbon::today());

        return new ValorEtiquetado(
            $fila->proxima_evaluacion->toDateString(),
            $fila->proxima_evaluacion->format('d/m/Y'),
            $vencida ? 'caducada' : 'planificado',
            $vencida ? 'TriangleAlert' : 'CalendarClock',
        );
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'codigo' => 'codigo',
                'nombre' => 'nombre',
                'cif' => 'nombre',
                'servicio_prestado' => 'servicio_prestado',
            ])->placeholder('Buscar por código, nombre, CIF o servicio…'),

            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoProveedor $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoProveedor::cases(),
            )),

            Filtro::booleano('es_nube', 'Servicio en la nube')->enColumna('nube'),

            // Por scope y con la clave del indicador del panel que cuenta lo mismo.
            Filtro::porScope('reevaluacion_vencida', 'Reevaluación vencida', 'reevaluacionVencida')->enColumna('proxima_evaluacion'),
            Filtro::porScope('sin_evaluar', 'Sin evaluar', 'sinEvaluar')->enColumna('proxima_evaluacion'),
            Filtro::porScope('certificacion_caducada', 'Certificación caducada', 'conCertificacionCaducada'),
            Filtro::porScope('condicionados', 'Condicionados', 'condicionados')->enColumna('estado'),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'codigo';
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/proveedores/{id}'),
            Accion::editar('/proveedores/{id}/editar')->permiso(Permiso::ProveedoresGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nuevo proveedor', '/proveedores/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::ProveedoresGestionar->value),
        ];
    }
}
