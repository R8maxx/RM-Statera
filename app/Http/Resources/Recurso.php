<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\DefinicionRecurso;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Enums\MetodoAccion;
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

    /**
     * La acción que abre el doble clic sobre una fila.
     *
     * Se declara y no se adivina en el cliente: en un recurso sin ficha —las
     * implantaciones se leen en la suya, los sistemas no tienen— la acción que
     * toca no es la misma, y dejar que el navegador elija «la primera que
     * parezca de lectura» convierte una convención en una casualidad.
     *
     * Devuelve la CLAVE de una acción de fila, o `null` para que el doble clic
     * no haga nada.
     */
    public function accionPorDefecto(): ?string
    {
        return 'ver';
    }

    /** La que abre el doble clic con Ctrl o ⌘. */
    public function accionAlternativa(): ?string
    {
        return 'editar';
    }

    /** Lo que viaja al frontend como prop `recurso`. */
    public function definicion(): DefinicionRecurso
    {
        // Se resuelven antes de construir: la acción por defecto se busca entre
        // las PERMITIDAS, y hacerlo con una asignación dentro de la llamada
        // dejaría el orden de evaluación decidiendo si funciona.
        $accionesFila = $this->permitidas($this->accionesFila());

        return new DefinicionRecurso(
            clave: $this->clave(),
            etiquetas: $this->etiquetas(),
            columnas: $this->columnas(),
            filtros: array_map(
                static fn (Filtro $filtro): Filtro => $filtro->resolver(),
                $this->filtros(),
            ),
            accionesFila: $accionesFila,
            accionesMasivas: $this->permitidas($this->accionesMasivas()),
            accionesGenerales: $this->permitidas($this->accionesGenerales()),
            ordenPorDefecto: $this->ordenPorDefecto(),
            tamanosPagina: $this->tamanosPagina(),
            seleccionable: $this->seleccionable(),
            accionPorDefecto: $this->navegable($this->accionPorDefecto(), $accionesFila),
            accionAlternativa: $this->navegable($this->accionAlternativa(), $accionesFila),
        );
    }

    /**
     * Comprueba que la acción declarada existe, está permitida y se puede abrir
     * de un doble clic.
     *
     * Tres cribas, y ninguna sobra:
     *
     * 1. **Sólo entre las permitidas.** A un auditor, que no tiene `editar`, el
     *    Ctrl + doble clic le llevaría a un formulario que el servidor le va a
     *    negar con un 403. Mejor que no haga nada.
     * 2. **Sólo `GET`.** Un doble clic no puede disparar un `DELETE` ni un
     *    `POST`. La regla se aplica aquí y se vuelve a aplicar en el cliente,
     *    porque cuesta dos líneas y lo que evita es irreversible.
     * 3. **Nunca destructiva.** Redundante con lo anterior mientras `eliminar`
     *    sea `DELETE`, y a propósito: es la comprobación que sigue en pie el día
     *    que alguien declare un borrado por `GET`.
     *
     * @param  list<Accion>  $permitidas
     */
    private function navegable(?string $clave, array $permitidas): ?string
    {
        if ($clave === null) {
            return null;
        }

        $accion = array_find($permitidas, static fn (Accion $accion): bool => $accion->clave === $clave);

        if ($accion === null || $accion->destructiva || $accion->metodo !== MetodoAccion::Get) {
            return null;
        }

        return $accion->clave;
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
