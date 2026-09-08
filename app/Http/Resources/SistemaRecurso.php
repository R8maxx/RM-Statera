<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Sistema\Enums\EstadoSistema;
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
 * Los sistemas del alcance: un SGSI de ISO o un sistema del ENS.
 *
 * La categoría no es una columna de la base — se deriva de las cinco
 * dimensiones — así que se calcula al serializar y no es ordenable.
 *
 * @extends Recurso<Sistema>
 */
final class SistemaRecurso extends Recurso
{
    public function clave(): string
    {
        return 'sistemas';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Sistema',
            plural: 'Sistemas',
            descripcion: 'La unidad de alcance y de certificación. La categoría se deriva de la valoración de las cinco dimensiones.',
            vacio: 'Todavía no hay ningún sistema en el alcance.',
        );
    }

    /** @return Builder<Sistema> */
    public function consulta(): Builder
    {
        return Sistema::query()
            ->with(['marco', 'valoraciones'])
            ->withCount([
                'implantaciones as aplicables_count' => fn (Builder $query) => $query->where('aplica', true),
            ]);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('7rem'),
            Columna::texto('nombre', 'Nombre')->ordenable(),
            Columna::texto('marco', 'Marco')
                ->formato(fn (Sistema $sistema): ?string => $sistema->marco?->nombre),
            Columna::badge('categoria', 'Categoría')
                ->ayuda('Derivada del máximo de las cinco dimensiones. No se elige a mano.')
                ->formato(function (Sistema $sistema): ?ValorEtiquetado {
                    $categoria = $sistema->categoria();

                    return $categoria === null
                        ? null
                        : new ValorEtiquetado($categoria->value, $categoria->etiqueta(), $categoria->value);
                }),
            Columna::badge('estado', 'Estado')
                ->ordenable()
                ->formato(fn (Sistema $sistema): ValorEtiquetado => new ValorEtiquetado(
                    $sistema->estado->value,
                    $sistema->estado->etiqueta(),
                    $sistema->estado->value,
                )),
            Columna::numero('aplicables', 'Requisitos')
                ->formato(fn (Sistema $sistema): int => (int) $sistema->getAttribute('aplicables_count')),
            Columna::fechaHora('created_at', 'Alta')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::texto('nombre', 'Nombre')->placeholder('Buscar por nombre…'),
            Filtro::select('marco_id', 'Marco', fn (): array => Marco::query()
                ->orderBy('nombre')
                ->get()
                ->map(fn (Marco $marco): Opcion => new Opcion((string) $marco->id, $marco->nombre))
                ->all()),
            Filtro::multiSelect('estado', 'Estado', array_map(
                static fn (EstadoSistema $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoSistema::cases(),
            )),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::editar('/sistemas/{id}/editar'),
            (new Accion('implantaciones', 'Ver implantaciones', '/implantaciones?filter[sistema_id]={id}'))
                ->icono('ListChecks'),
            Accion::eliminar('/sistemas/{id}', '¿Eliminar el sistema y todas sus implantaciones? La traza de estados se pierde con él.'),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nuevo sistema', '/sistemas/crear', MetodoAccion::Get))->icono('Plus'),
        ];
    }

    public function ordenPorDefecto(): string
    {
        return 'codigo';
    }
}
