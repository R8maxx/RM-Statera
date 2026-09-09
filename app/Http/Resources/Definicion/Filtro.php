<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use App\Http\Resources\Enums\TipoFiltro;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un filtro de la tabla.
 *
 * Cada filtro se traduce a un `AllowedFilter` de spatie/laravel-query-builder:
 * lo que no está declarado aquí no filtra, por mucho que llegue en la query
 * string.
 *
 * Dónde se pinta lo decide `$columna`: un filtro cuya columna está visible baja
 * a la fila de filtros de la cabecera —debajo del dato que estrecha, que es
 * donde se busca— y el resto se agrupa en el desplegable «Filtros» de la barra.
 * La búsqueda es la excepción: cruza varios campos, no pertenece a ninguna
 * columna y por eso se queda siempre arriba.
 */
#[TypeScript]
final class Filtro
{
    public ?string $placeholder = null;

    /** @var list<Opcion> */
    public array $opciones = [];

    public bool $multiple = false;

    /**
     * La columna bajo la que se pinta el control, si hay alguna.
     *
     * Por defecto la propia clave: `estado` filtra la columna `estado`. Cuando
     * no coinciden —`marco_id` sobre la columna `marco`— se dice con
     * `enColumna()`.
     */
    public ?string $columna;

    /**
     * Las columnas donde se resalta la coincidencia.
     *
     * Sólo lo necesita la búsqueda: cruza campos de servidor —`requisitos.titulo`—
     * y el cliente no puede adivinar a qué columna corresponde cada uno. Un
     * filtro de texto resalta en su propia columna y no declara nada.
     *
     * @var list<string>
     */
    public array $resaltaEn = [];

    /** Columna real de la base, si no coincide con la clave. */
    private ?string $campo = null;

    /**
     * Los campos que cruza una búsqueda.
     *
     * @var list<string>
     */
    private array $campos = [];

    /** @var Closure(): list<Opcion>|null */
    private ?Closure $resolverOpciones = null;

    private function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly TipoFiltro $tipo,
    ) {
        $this->columna = $tipo === TipoFiltro::Busqueda ? null : $clave;
    }

    /**
     * La búsqueda de la barra: un solo cuadro sobre varios campos, en OR.
     *
     * Los campos se declaran aquí y no se negocian con la query string, igual
     * que el resto de filtros. Se aceptan campos de una tabla unida —
     * `requisitos.codigo`— porque la consulta del recurso ya trae el join.
     *
     * Admite dos formas. Una lista de campos busca y no resalta nada. Un mapa
     * `campo => clave de columna` busca igual y además dice en qué columnas se
     * resalta la coincidencia, que es lo único que el cliente no puede deducir
     * por su cuenta:
     *
     * ```php
     * Filtro::busqueda('q', 'Buscar', ['requisitos.codigo' => 'codigo', 'requisitos.titulo' => 'requisito'])
     * ```
     *
     * @param  list<string>|array<string, string>  $campos
     */
    public static function busqueda(string $clave, string $etiqueta, array $campos): self
    {
        $filtro = new self($clave, $etiqueta, TipoFiltro::Busqueda);
        $esMapa = ! array_is_list($campos);

        $filtro->campos = $esMapa ? array_keys($campos) : $campos;
        $filtro->resaltaEn = $esMapa ? array_values(array_unique($campos)) : [];

        return $filtro;
    }

    /** Búsqueda parcial, insensible a mayúsculas. */
    public static function texto(string $clave, string $etiqueta): self
    {
        return new self($clave, $etiqueta, TipoFiltro::Texto);
    }

    /**
     * @param  list<Opcion>|Closure(): list<Opcion>  $opciones
     */
    public static function select(string $clave, string $etiqueta, array|Closure $opciones): self
    {
        $filtro = new self($clave, $etiqueta, TipoFiltro::Select);

        return $filtro->conOpciones($opciones);
    }

    /**
     * @param  list<Opcion>|Closure(): list<Opcion>  $opciones
     */
    public static function multiSelect(string $clave, string $etiqueta, array|Closure $opciones): self
    {
        $filtro = new self($clave, $etiqueta, TipoFiltro::MultiSelect);
        $filtro->multiple = true;

        return $filtro->conOpciones($opciones);
    }

    public static function booleano(string $clave, string $etiqueta): self
    {
        return new self($clave, $etiqueta, TipoFiltro::Booleano);
    }

    public static function rangoFechas(string $clave, string $etiqueta): self
    {
        return new self($clave, $etiqueta, TipoFiltro::RangoFechas);
    }

    public function campo(string $campo): self
    {
        $this->campo = $campo;

        return $this;
    }

    /** La columna bajo la que se pinta, cuando no se llama como la clave. */
    public function enColumna(string $columna): self
    {
        $this->columna = $columna;

        return $this;
    }

    /** Lo saca de la fila de la cabecera y lo deja sólo en «Filtros». */
    public function sinColumna(): self
    {
        $this->columna = null;

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /** Resuelve las opciones diferidas. Se llama al serializar la definición. */
    public function resolver(): self
    {
        if ($this->resolverOpciones !== null) {
            $this->opciones = ($this->resolverOpciones)();
        }

        return $this;
    }

    /** La traducción a spatie/laravel-query-builder. */
    public function allowedFilter(): AllowedFilter
    {
        $campo = $this->campo ?? $this->clave;

        return match ($this->tipo) {
            TipoFiltro::Busqueda => AllowedFilter::callback(
                $this->clave,
                function (Builder $query, mixed $valor): void {
                    $texto = trim(is_array($valor) ? implode(' ', $valor) : (string) $valor);

                    if ($texto === '') {
                        return;
                    }

                    // Los comodines del término son texto, no sintaxis: sin
                    // escaparlos, un `%` suelto devuelve la tabla entera.
                    $patron = '%'.addcslashes($texto, '%_\\').'%';

                    $query->where(function (Builder $anidada) use ($patron): void {
                        foreach ($this->campos as $campo) {
                            $anidada->orWhereLike($campo, $patron, caseSensitive: false);
                        }
                    });
                },
            ),
            TipoFiltro::Texto => AllowedFilter::partial($this->clave, $campo),
            TipoFiltro::Select, TipoFiltro::MultiSelect => AllowedFilter::exact($this->clave, $campo),
            TipoFiltro::Booleano => AllowedFilter::callback(
                $this->clave,
                // "false" llega como cadena por la query string y `filter_var`
                // es lo único que la interpreta como el usuario espera.
                fn (Builder $query, mixed $valor) => $query->where(
                    $campo,
                    filter_var($valor, FILTER_VALIDATE_BOOL),
                ),
            ),
            TipoFiltro::RangoFechas => AllowedFilter::callback(
                $this->clave,
                function (Builder $query, mixed $valor) use ($campo): void {
                    // Llega como `desde,hasta`; cualquiera de los dos extremos
                    // puede venir vacío.
                    $extremos = is_array($valor) ? $valor : explode(',', (string) $valor);
                    [$desde, $hasta] = [$extremos[0] ?? '', $extremos[1] ?? ''];

                    // Un extremo que no es una fecha se ignora, no revienta la
                    // petición: la query string es del usuario y estas URL se
                    // guardan y se comparten. PostgreSQL responde a
                    // `>= '2026'` con un error de sintaxis, y eso era un 500.
                    if (self::esFecha($desde)) {
                        $query->whereDate($campo, '>=', $desde);
                    }

                    if (self::esFecha($hasta)) {
                        $query->whereDate($campo, '<=', $hasta);
                    }
                },
            ),
        };
    }

    /** Una fecha del calendario en el formato que envía un `<input type="date">`. */
    private static function esFecha(string $valor): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes) !== 1) {
            return false;
        }

        return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]);
    }

    /**
     * @param  list<Opcion>|Closure(): list<Opcion>  $opciones
     */
    private function conOpciones(array|Closure $opciones): self
    {
        if ($opciones instanceof Closure) {
            $this->resolverOpciones = $opciones;
        } else {
            $this->opciones = $opciones;
        }

        return $this;
    }
}
