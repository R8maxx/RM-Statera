<?php

declare(strict_types=1);

namespace App\Domain\Obligacion;

use App\Domain\Obligacion\Enums\ReferenciaCumplimiento;
use App\Domain\Obligacion\Models\CompromisoCumplimiento;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A qué registro del producto apunta un cumplimiento, ya resuelto.
 *
 * Es lo que permite que la ficha pinte «Auditoría interna 2026» con su enlace
 * **sin importar los tres módulos**: `ReferenciaCumplimiento` sabe la etiqueta,
 * el icono y la URL, y esto sólo lee las tres columnas y elige la que viene.
 *
 * Escalares y nada más, como `Aviso\Vencimiento`: viaja a Inertia, que serializa
 * lo público y no llama a nada.
 */
#[TypeScript]
final readonly class Referencia
{
    public function __construct(
        public ReferenciaCumplimiento $tipo,
        public int $id,
        public string $etiqueta,
        public string $icono,
        public string $url,
    ) {}

    /**
     * La referencia de un cumplimiento, o nada si no apunta a ninguna.
     *
     * **El título sale del registro apuntado sólo si está cargado.** Sin la
     * relación, se cae a la etiqueta del tipo: pintar «Auditoría» es menos que
     * pintar su nombre, pero mucho mejor que una consulta por fila desde una
     * lista de veinte cumplimientos.
     */
    public static function de(CompromisoCumplimiento $cumplimiento): ?self
    {
        $apuntado = $cumplimiento->referencia();

        if ($apuntado === null) {
            return null;
        }

        $tipo = $apuntado['referencia'];

        return new self(
            tipo: $tipo,
            id: $apuntado['id'],
            etiqueta: self::titulo($cumplimiento, $tipo),
            icono: $tipo->icono(),
            url: $tipo->url($apuntado['id']),
        );
    }

    private static function titulo(CompromisoCumplimiento $cumplimiento, ReferenciaCumplimiento $tipo): string
    {
        $relacion = match ($tipo) {
            ReferenciaCumplimiento::Auditoria => 'auditoria',
            ReferenciaCumplimiento::RevisionDireccion => 'revisionDireccion',
            ReferenciaCumplimiento::Documento => 'documento',
            ReferenciaCumplimiento::PruebaContinuidad => 'pruebaContinuidad',
        };

        if (! $cumplimiento->relationLoaded($relacion)) {
            return $tipo->etiqueta();
        }

        $modelo = $cumplimiento->{$relacion};

        if ($modelo === null) {
            return $tipo->etiqueta();
        }

        // Cada uno nombra su fila de forma distinta y ninguno comparte interfaz:
        // se prueban los dos campos que existen en el producto y se cae a la
        // etiqueta del tipo, que siempre es cierta.
        return (string) ($modelo->titulo ?? $modelo->codigo ?? $tipo->etiqueta());
    }
}
