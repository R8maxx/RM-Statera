<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use App\Http\Resources\Enums\Alineacion;
use App\Http\Resources\Enums\TipoColumna;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Una columna de la tabla.
 *
 * Sólo las propiedades públicas cruzan a TypeScript y viajan en el prop
 * `recurso`; el `formato` y el `campoOrden` son detalle de servidor y se quedan
 * aquí.
 */
#[TypeScript]
final class Columna
{
    public bool $ordenable = false;

    public bool $ocultaPorDefecto = false;

    public bool $anclada = false;

    public Alineacion $alineacion = Alineacion::Izquierda;

    public ?string $ancho = null;

    public ?string $ayuda = null;

    /**
     * El modelo concreto lo conoce cada `Recurso`, no esta clase; el genérico
     * de `formato()` lo infiere de la propia closure que se le pasa.
     */
    private ?Closure $formato = null;

    /** Columna real por la que se ordena, si no coincide con la clave. */
    private ?string $campoOrden = null;

    private function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly TipoColumna $tipo,
    ) {}

    public static function texto(string $clave, string $etiqueta): self
    {
        return new self($clave, $etiqueta, TipoColumna::Texto);
    }

    public static function numero(string $clave, string $etiqueta): self
    {
        return (new self($clave, $etiqueta, TipoColumna::Numero))->alinear(Alineacion::Derecha);
    }

    public static function fecha(string $clave, string $etiqueta): self
    {
        return new self($clave, $etiqueta, TipoColumna::Fecha);
    }

    public static function fechaHora(string $clave, string $etiqueta): self
    {
        return new self($clave, $etiqueta, TipoColumna::FechaHora);
    }

    public static function booleano(string $clave, string $etiqueta): self
    {
        return (new self($clave, $etiqueta, TipoColumna::Booleano))->alinear(Alineacion::Centro);
    }

    /** Estados, categorías, niveles: todo lo que se pinta como etiqueta de color. */
    public static function badge(string $clave, string $etiqueta): self
    {
        return new self($clave, $etiqueta, TipoColumna::Badge);
    }

    public static function enlace(string $clave, string $etiqueta): self
    {
        return new self($clave, $etiqueta, TipoColumna::Enlace);
    }

    public static function progreso(string $clave, string $etiqueta): self
    {
        return new self($clave, $etiqueta, TipoColumna::Progreso);
    }

    /** El `$campo` es la columna real de la base cuando no coincide con la clave. */
    public function ordenable(?string $campo = null): self
    {
        $this->ordenable = true;
        $this->campoOrden = $campo;

        return $this;
    }

    public function oculta(): self
    {
        $this->ocultaPorDefecto = true;

        return $this;
    }

    public function anclada(): self
    {
        $this->anclada = true;

        return $this;
    }

    public function alinear(Alineacion $alineacion): self
    {
        $this->alineacion = $alineacion;

        return $this;
    }

    public function ancho(string $ancho): self
    {
        $this->ancho = $ancho;

        return $this;
    }

    public function ayuda(string $ayuda): self
    {
        $this->ayuda = $ayuda;

        return $this;
    }

    /**
     * @template TModel of Model
     *
     * @param  Closure(TModel): mixed  $formato
     */
    public function formato(Closure $formato): self
    {
        $this->formato = $formato;

        return $this;
    }

    /** El valor que se envía para esta columna en una fila concreta. */
    public function valorDe(Model $modelo): mixed
    {
        if ($this->formato !== null) {
            return ($this->formato)($modelo);
        }

        return data_get($modelo, $this->clave);
    }

    /** Nombre con el que la ordenación llega por la query string. */
    public function campoOrden(): string
    {
        return $this->campoOrden ?? $this->clave;
    }
}
