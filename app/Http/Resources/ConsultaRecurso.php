<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\MetaTabla;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Traduce la query string al conjunto de filas que pide la tabla.
 *
 * Lo que no está declarado en el `Recurso` no filtra ni ordena: los
 * `allowedFilters` y `allowedSorts` salen de la definición, nunca de lo que
 * llegue en la URL. Un parámetro de más se ignora, no se aplica a ciegas.
 *
 * @template TModel of Model
 */
final readonly class ConsultaRecurso
{
    /** @param  Recurso<TModel>  $recurso */
    public function __construct(private Recurso $recurso) {}

    /**
     * @return array{filas: list<array<string, mixed>>, meta: MetaTabla}
     */
    public function ejecutar(Request $request): array
    {
        $columnas = $this->recurso->columnas();
        $filtros = $this->recurso->filtros();

        $paginador = $this->paginador($request, $columnas, $filtros);

        return [
            'filas' => $this->filas($paginador, $columnas),
            'meta' => MetaTabla::desdePaginador(
                $paginador,
                $this->ordenAplicado($request, $columnas),
                $this->filtrosAplicados($request, $filtros),
            ),
        ];
    }

    /**
     * La consulta con los filtros de la URL aplicados, sin ordenar ni paginar.
     *
     * **Existe para las pantallas que no son tablas.** El tablero agrupa por
     * estado y el calendario acota por mes: ninguno de los dos pagina, pero los
     * dos tienen que filtrar exactamente igual que la tabla. Con la aplicación
     * de filtros pegada al `->paginate()`, la única salida era escribir los
     * filtros por segunda vez, y entonces la tabla enseña doce y el tablero
     * nueve.
     *
     * Lo que no está declarado en el `Recurso` sigue sin filtrar, aquí también.
     *
     * @return QueryBuilder<TModel>
     */
    public function consultaFiltrada(Request $request): QueryBuilder
    {
        return QueryBuilder::for($this->recurso->consulta(), $request)
            ->allowedFilters(...array_map(
                static fn (Filtro $filtro) => $filtro->allowedFilter(),
                $this->recurso->filtros(),
            ));
    }

    /**
     * Qué filtros se aplicaron de verdad, no los que se pidieron.
     *
     * Es lo que pinta los chips de la barra, y viaja igual en la tabla, en el
     * tablero y en el calendario.
     *
     * @param  list<Filtro>  $filtros
     * @return array<string, string|list<string>>
     */
    public function filtrosAplicados(Request $request, array $filtros): array
    {
        /** @var array<string, mixed> $recibidos */
        $recibidos = $request->array('filter');
        $aplicados = [];

        foreach ($filtros as $filtro) {
            $valor = $recibidos[$filtro->clave] ?? null;

            if ($valor === null || $valor === '' || $valor === []) {
                continue;
            }

            $aplicados[$filtro->clave] = is_array($valor)
                ? array_values(array_map(strval(...), $valor))
                : (string) $valor;
        }

        return $aplicados;
    }

    /**
     * @param  list<Columna>  $columnas
     * @param  list<Filtro>  $filtros
     * @return LengthAwarePaginator<int, TModel>
     */
    private function paginador(Request $request, array $columnas, array $filtros): LengthAwarePaginator
    {
        $ordenables = array_values(array_map(
            static fn (Columna $columna): AllowedSort => AllowedSort::field($columna->clave, $columna->campoOrden()),
            array_filter($columnas, static fn (Columna $columna): bool => $columna->ordenable),
        ));

        return $this->consultaFiltrada($request)
            ->allowedSorts(...$ordenables)
            ->defaultSort($this->ordenPorDefecto($columnas))
            ->paginate($this->porPagina($request))
            ->withQueryString();
    }

    /**
     * El orden por defecto se resuelve por la misma vía que uno pedido: si la
     * columna declara un campo de ordenación distinto de su clave, se usa.
     *
     * Sin esto, «Código» ordenaría por el texto del código al pulsar la
     * cabecera y por otra cosa al entrar en la página, que es peor que
     * cualquiera de las dos.
     *
     * @param  list<Columna>  $columnas
     */
    private function ordenPorDefecto(array $columnas): AllowedSort
    {
        $valor = $this->recurso->ordenPorDefecto();
        $clave = ltrim($valor, '-');

        $columna = array_find(
            $columnas,
            static fn (Columna $columna): bool => $columna->clave === $clave,
        );

        return AllowedSort::field($valor, $columna?->campoOrden());
    }

    /**
     * Sólo se aceptan los tamaños que el recurso declara: `por_pagina=100000` es
     * una denegación de servicio barata.
     */
    private function porPagina(Request $request): int
    {
        $pedido = (int) $request->integer('por_pagina');

        return in_array($pedido, $this->recurso->tamanosPagina(), true)
            ? $pedido
            : $this->recurso->porPagina();
    }

    /**
     * @param  LengthAwarePaginator<int, TModel>  $paginador
     * @param  list<Columna>  $columnas
     * @return list<array<string, mixed>>
     */
    private function filas(LengthAwarePaginator $paginador, array $columnas): array
    {
        $filas = [];

        foreach ($paginador->items() as $modelo) {
            $fila = ['id' => $modelo->getKey(), ...$this->recurso->extrasDeFila($modelo)];

            foreach ($columnas as $columna) {
                $fila[$columna->clave] = $columna->valorDe($modelo);
            }

            $filas[] = $fila;
        }

        return $filas;
    }

    /**
     * @param  list<Columna>  $columnas
     */
    private function ordenAplicado(Request $request, array $columnas): string
    {
        $pedido = $request->string('sort')->toString();
        $clave = ltrim($pedido, '-');

        $existe = array_any(
            $columnas,
            static fn (Columna $columna): bool => $columna->ordenable && $columna->clave === $clave,
        );

        return $existe ? $pedido : $this->recurso->ordenPorDefecto();
    }
}
