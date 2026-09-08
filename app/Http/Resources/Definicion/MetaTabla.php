<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * El estado de la consulta que produjo las filas actuales.
 *
 * Es lo que el `DataTable` devuelve al servidor en la siguiente petición, así
 * que aquí viaja siempre lo que se aplicó de verdad, no lo que se pidió.
 */
#[TypeScript]
final class MetaTabla
{
    /**
     * @param  array<string, string|list<string>>  $filtros
     */
    public function __construct(
        public readonly int $pagina,
        public readonly int $porPagina,
        public readonly int $total,
        public readonly int $ultimaPagina,
        public readonly ?int $desde,
        public readonly ?int $hasta,
        public readonly string $orden,
        public readonly array $filtros,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, covariant \Illuminate\Database\Eloquent\Model>  $paginador
     * @param  array<string, string|list<string>>  $filtros
     */
    public static function desdePaginador(
        LengthAwarePaginator $paginador,
        string $orden,
        array $filtros,
    ): self {
        return new self(
            pagina: $paginador->currentPage(),
            porPagina: $paginador->perPage(),
            total: $paginador->total(),
            ultimaPagina: $paginador->lastPage(),
            desde: $paginador->firstItem(),
            hasta: $paginador->lastItem(),
            orden: $orden,
            filtros: $filtros,
        );
    }
}
