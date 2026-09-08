<?php

declare(strict_types=1);

namespace App\Http\Resources\Definicion;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Todo lo que el `DataTable` necesita saber de un recurso y que no cambia al
 * paginar, ordenar ni filtrar.
 *
 * Viaja como prop `once`: se envía en la primera visita y el cliente lo
 * conserva. Las recargas de tabla piden sólo `filas` y `meta`.
 */
#[TypeScript]
final class DefinicionRecurso
{
    /**
     * @param  list<Columna>  $columnas
     * @param  list<Filtro>  $filtros
     * @param  list<Accion>  $accionesFila
     * @param  list<Accion>  $accionesMasivas
     * @param  list<Accion>  $accionesGenerales
     * @param  list<int>  $tamanosPagina
     */
    public function __construct(
        public readonly string $clave,
        public readonly Etiquetas $etiquetas,
        public readonly array $columnas,
        public readonly array $filtros,
        public readonly array $accionesFila,
        public readonly array $accionesMasivas,
        public readonly array $accionesGenerales,
        public readonly string $ordenPorDefecto,
        public readonly array $tamanosPagina,
        public readonly bool $seleccionable,
    ) {}
}
