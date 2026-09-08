<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use App\Http\Resources\Enums\TipoFiltro;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un filtro de la barra superior de la tabla.
 *
 * Cada filtro se traduce a un `AllowedFilter` de spatie/laravel-query-builder:
 * lo que no está declarado aquí no filtra, por mucho que llegue en la query
 * string.
 */
#[TypeScript]
final class Filtro
{
    public ?string $placeholder = null;

    /** @var list<Opcion> */
    public array $opciones = [];

    public bool $multiple = false;

    /** Columna real de la base, si no coincide con la clave. */
    private ?string $campo = null;

    /** @var Closure(): list<Opcion>|null */
    private ?Closure $resolverOpciones = null;

    private function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly TipoFiltro $tipo,
    ) {}

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

                    if ($desde !== '') {
                        $query->whereDate($campo, '>=', $desde);
                    }

                    if ($hasta !== '') {
                        $query->whereDate($campo, '<=', $hasta);
                    }
                },
            ),
        };
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
