<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\DefinicionRecurso;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * La declaración de un recurso: qué columnas tiene, por qué se puede filtrar y
 * ordenar, y qué se puede hacer con él.
 *
 * Existe para que los diecinueve módulos hablen el mismo dialecto. Un `Recurso`
 * no consulta ni autoriza: describe. De consultar se encarga
 * `ConsultaRecurso`, y de autorizar, la ruta y la política — lo que se declara
 * aquí sólo decide qué se pinta.
 *
 * El aislamiento multi-tenant no se toca desde aquí: `consulta()` parte de un
 * modelo con `PerteneceAOrganizacion`, así que el scope global ya está puesto.
 * Nunca `withoutGlobalScopes()`.
 *
 * @template TModel of Model
 */
abstract class Recurso
{
    /** Identifica el recurso en la URL y en la caché de props `once`. */
    abstract public function clave(): string;

    abstract public function etiquetas(): Etiquetas;

    /** @return Builder<TModel> */
    abstract public function consulta(): Builder;

    /** @return list<Columna> */
    abstract public function columnas(): array;

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [];
    }

    /** @return list<Accion> */
    public function accionesMasivas(): array
    {
        return [];
    }

    /**
     * Botones que no dependen de una fila: «Nuevo sistema», «Exportar»…
     *
     * @return list<Accion>
     */
    public function accionesGenerales(): array
    {
        return [];
    }

    /** Formato de spatie/laravel-query-builder: `codigo` o `-creado_en`. */
    public function ordenPorDefecto(): string
    {
        return 'id';
    }

    public function porPagina(): int
    {
        return 25;
    }

    /** @return list<int> */
    public function tamanosPagina(): array
    {
        return [10, 25, 50, 100];
    }

    public function seleccionable(): bool
    {
        return $this->accionesMasivas() !== [];
    }

    /**
     * Valores extra de cada fila que no son columnas: identificadores para
     * enlaces, banderas que condicionan una acción.
     *
     * @return array<string, mixed>
     */
    public function extrasDeFila(Model $modelo): array
    {
        return [];
    }

    /** Lo que viaja al frontend como prop `recurso`. */
    public function definicion(): DefinicionRecurso
    {
        return new DefinicionRecurso(
            clave: $this->clave(),
            etiquetas: $this->etiquetas(),
            columnas: $this->columnas(),
            filtros: array_map(
                static fn (Filtro $filtro): Filtro => $filtro->resolver(),
                $this->filtros(),
            ),
            accionesFila: $this->permitidas($this->accionesFila()),
            accionesMasivas: $this->permitidas($this->accionesMasivas()),
            accionesGenerales: $this->permitidas($this->accionesGenerales()),
            ordenPorDefecto: $this->ordenPorDefecto(),
            tamanosPagina: $this->tamanosPagina(),
            seleccionable: $this->seleccionable(),
        );
    }

    /**
     * @param  list<Accion>  $acciones
     * @return list<Accion>
     */
    private function permitidas(array $acciones): array
    {
        $usuario = Auth::user();

        return array_values(array_filter($acciones, static function (Accion $accion) use ($usuario): bool {
            $permiso = $accion->permisoRequerido();

            if ($permiso === null) {
                return true;
            }

            return $usuario !== null && $usuario->can($permiso);
        }));
    }
}
