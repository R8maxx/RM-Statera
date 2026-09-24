<?php

declare(strict_types=1);

namespace App\Domain\Auditoria;

use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\Hallazgo;

/**
 * El resultado de una auditoría, contado sobre su checklist.
 *
 * **Con el denominador**, como toda cifra que acaba en un documento: «ninguna no
 * conformidad» no dice nada; «0 no conformes sobre 52 medidas» sí. Y con los
 * cinco resultados por separado, incluidos `pendiente` y `fuera_de_muestra`,
 * **que nunca se suman a conforme**: una medida que nadie revisó no es una medida
 * que esté bien.
 *
 * Lo imprimen dos documentos —la Declaración de Conformidad y el informe de
 * auditoría— y por eso vive aquí y no en ninguno de los dos: con dos copias, el
 * informe y la declaración que se apoya en él acabarían contando distinto la
 * misma checklist.
 */
final class ResultadoAuditoria
{
    /**
     * @return array{
     *     total: int,
     *     puntos: list<array{clave: string, etiqueta: string, tono: string, total: int}>,
     *     hallazgos: list<array{clave: string, etiqueta: string, tono: string, total: int}>
     * }
     */
    public function para(Auditoria $auditoria): array
    {
        /** @var array<string, int> $conteos */
        $conteos = $auditoria->puntos()
            ->selectRaw('resultado as clave, count(*) as total')
            ->groupBy('resultado')
            ->pluck('total', 'clave')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        /** @var array<string, int> $porTipo */
        $porTipo = Hallazgo::query()
            ->where('auditoria_id', $auditoria->id)
            ->selectRaw('tipo as clave, count(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'clave')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        $puntos = [];

        foreach (ResultadoPunto::cases() as $resultado) {
            $puntos[] = [
                'clave' => $resultado->value,
                'etiqueta' => $resultado->etiqueta(),
                'tono' => $resultado->tono(),
                'total' => $conteos[$resultado->value] ?? 0,
            ];
        }

        $hallazgos = [];

        foreach (TipoHallazgo::cases() as $tipo) {
            $hallazgos[] = [
                'clave' => $tipo->value,
                'etiqueta' => $tipo->etiqueta(),
                'tono' => $tipo->tono(),
                'total' => $porTipo[$tipo->value] ?? 0,
            ];
        }

        return [
            'total' => array_sum($conteos),
            'puntos' => $puntos,
            'hallazgos' => $hallazgos,
        ];
    }
}
