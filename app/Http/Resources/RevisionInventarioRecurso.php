<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Activo\Models\RevisionInventario;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\MetodoAccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * El registro de que el inventario se revisa.
 *
 * La columna que importa es «Hallazgos»: una revisión sin desviaciones anotadas
 * puede ser una revisión limpia o una que nadie terminó, y las dos cosas se
 * parecen demasiado en una lista de fechas. El badge las separa.
 *
 * @extends Recurso<RevisionInventario>
 */
final class RevisionInventarioRecurso extends Recurso
{
    public function clave(): string
    {
        return 'revisiones';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Revisión',
            plural: 'Revisiones del inventario',
            descripcion: 'A.5.9 de ISO y op.exp.1 del ENS no piden un inventario, piden un inventario mantenido. Esta es la diferencia entre las dos cosas.',
            vacio: 'Todavía no hay ninguna revisión registrada. Sin ella, el inventario no consta como mantenido por muy completo que esté.',
        );
    }

    /** @return Builder<RevisionInventario> */
    public function consulta(): Builder
    {
        return RevisionInventario::query()->with('responsable');
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::fecha('fecha', 'Fecha')->ordenable()->anclada()->ancho('8rem'),
            Columna::texto('alcance', 'Alcance revisado')->ordenable(),
            Columna::texto('responsable', 'Responsable')
                ->formato(fn (RevisionInventario $revision): ?string => $revision->responsable?->name),
            Columna::numero('altas', 'Altas')->ordenable(),
            Columna::numero('bajas', 'Bajas')->ordenable(),
            Columna::badge('hallazgos', 'Hallazgos')
                ->ayuda('Una revisión sin desviaciones anotadas puede ser una revisión limpia o una que nadie terminó. Conviene que se distingan.')
                ->formato(fn (RevisionInventario $revision): ValorEtiquetado => $revision->tieneHallazgos()
                    ? new ValorEtiquetado('si', 'Con desviaciones', 'en_progreso')
                    : new ValorEtiquetado('no', 'Sin desviaciones', 'implantado')),
            Columna::fechaHora('created_at', 'Registrada')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'alcance' => 'alcance',
                'desviaciones' => 'alcance',
                'acciones' => 'alcance',
            ])->placeholder('Buscar por alcance, desviación o acción…'),
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->enColumna('responsable'),
            Filtro::rangoFechas('fecha', 'Fecha'),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::editar('/revisiones/{id}/editar')->permiso(Permiso::ActivosGestionar->value),
            Accion::eliminar(
                '/revisiones/{id}',
                '¿Eliminar la revisión? Es evidencia de que el inventario se mantiene, y borrarla deja un hueco en el registro que el auditor sí mira.',
            )->permiso(Permiso::ActivosGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Registrar revisión', '/revisiones/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::ActivosGestionar->value),
        ];
    }

    /** La última arriba: lo que se pregunta es cuándo se revisó por última vez. */
    public function ordenPorDefecto(): string
    {
        return '-fecha';
    }

    /**
     * Aquí no hay ficha: una revisión es fecha, alcance y dos textos, y se lee
     * en el mismo formulario en el que se escribe. El doble clic abre eso.
     */
    public function accionPorDefecto(): string
    {
        return 'editar';
    }

    public function accionAlternativa(): ?string
    {
        return null;
    }
}
