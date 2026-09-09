<?php

declare(strict_types=1);

namespace App\Http\Resources\Valoracion;

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Implantacion\ResultadoGeneracion;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Lo que pasaría al guardar una valoración, antes de guardarla.
 *
 * Es el `--dry-run` del importador del catálogo llevado a la pantalla: el
 * recálculo no modifica nada en silencio, así que quien cambia una valoración ve
 * primero a cuántas medidas afecta y a cuáles.
 *
 * Los códigos viajan enteros y no sólo contados porque «catorce medidas dejan de
 * aplicar» no es una frase sobre la que nadie pueda decidir.
 */
#[TypeScript]
final class PrevisualizacionValoracion
{
    /**
     * @param  list<string>  $creadas
     * @param  list<string>  $reactivadas
     * @param  list<string>  $dejanDeAplicar
     * @param  list<CambioExigencia>  $cambianExigencia
     */
    public function __construct(
        public readonly ?string $categoria,
        public readonly bool $enAmbitoEns,
        public readonly bool $hayCambios,
        public readonly array $creadas,
        public readonly array $reactivadas,
        public readonly array $dejanDeAplicar,
        public readonly array $cambianExigencia,
        public readonly int $sinCambios,
    ) {}

    public static function desde(ResultadoGeneracion $resultado): self
    {
        $categoria = $resultado->categoria === null
            ? null
            : CategoriaEns::from($resultado->categoria);

        return new self(
            categoria: $categoria?->etiqueta(),
            enAmbitoEns: $categoria !== null,
            hayCambios: $resultado->hayCambios(),
            creadas: $resultado->creadas,
            reactivadas: $resultado->reactivadas,
            dejanDeAplicar: $resultado->dejanDeAplicar,
            cambianExigencia: array_map(
                static fn (array $cambio): CambioExigencia => new CambioExigencia(
                    codigo: $cambio['codigo'],
                    anterior: $cambio['anterior'],
                    nueva: $cambio['nueva'],
                ),
                $resultado->cambianExigencia,
            ),
            sinCambios: $resultado->sinCambios,
        );
    }
}
