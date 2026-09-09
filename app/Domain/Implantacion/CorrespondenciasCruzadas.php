<?php

declare(strict_types=1);

namespace App\Domain\Implantacion;

use App\Domain\Catalogo\Models\Mapeo;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Http\Resources\Implantacion\Correspondencia;
use App\Http\Resources\Implantacion\EstadoCorrespondencia;

/**
 * Qué requisitos de otros marcos cubren lo mismo que éste, y qué tiene hecho la
 * organización en ellos.
 *
 * Es el mapeo cruzado: el problema que el producto resuelve. Hoy eso son dos
 * hojas de cálculo que nadie sincroniza, y la consecuencia es trabajo hecho dos
 * veces o un marco que se queda atrás sin que nadie lo note.
 *
 * Los mapeos del catálogo son dirigidos (`origen` → `destino`), pero la
 * correspondencia no lo es: si `A.8.13` mapea a `mp.info.9`, desde `mp.info.9`
 * hay que ver `A.8.13`. Por eso se recorren las dos direcciones.
 */
final class CorrespondenciasCruzadas
{
    /**
     * Las implantaciones que se leen aquí pasan por el scope de organización,
     * así que lo que se ve es siempre lo de la organización activa. El catálogo
     * —requisitos y mapeos— es global y no lo lleva.
     *
     * @return list<Correspondencia>
     */
    public function paraRequisito(int $requisitoId): array
    {
        $mapeos = Mapeo::query()
            ->with(['origen.marco', 'destino.marco'])
            ->where('requisito_origen_id', $requisitoId)
            ->orWhere('requisito_destino_id', $requisitoId)
            ->get();

        /** @var array<int, Requisito> $otros */
        $otros = [];
        /** @var array<int, Mapeo> $porRequisito */
        $porRequisito = [];

        foreach ($mapeos as $mapeo) {
            $otro = $mapeo->requisito_origen_id === $requisitoId ? $mapeo->destino : $mapeo->origen;

            if ($otro === null || $otro->id === $requisitoId) {
                continue;
            }

            $otros[$otro->id] = $otro;
            $porRequisito[$otro->id] = $mapeo;
        }

        if ($otros === []) {
            return [];
        }

        $implantaciones = Implantacion::query()
            ->with('sistema')
            ->whereIn('requisito_id', array_keys($otros))
            ->get()
            ->groupBy('requisito_id');

        $correspondencias = [];

        foreach ($otros as $id => $requisito) {
            $mapeo = $porRequisito[$id];

            $correspondencias[] = new Correspondencia(
                requisitoId: $id,
                codigo: $requisito->codigo,
                titulo: $requisito->titulo,
                marco: $requisito->marco?->codigo,
                tipo: $mapeo->tipo_correspondencia->etiqueta(),
                cubreDelTodo: $mapeo->tipo_correspondencia->cubreDelTodo(),
                nota: $mapeo->nota,
                implantaciones: $implantaciones->get($id, collect())
                    ->map(fn (Implantacion $fila): EstadoCorrespondencia => new EstadoCorrespondencia(
                        implantacionId: $fila->id,
                        sistema: $fila->sistema->codigo,
                        estado: $fila->estado->value,
                        estadoEtiqueta: $fila->estado->etiqueta(),
                        aplica: $fila->aplica,
                    ))
                    ->values()
                    ->all(),
            );
        }

        // Por marco y código, que es como se lee una tabla de correspondencias.
        usort(
            $correspondencias,
            static fn (Correspondencia $a, Correspondencia $b): int => [$a->marco, $a->codigo] <=> [$b->marco, $b->codigo],
        );

        return $correspondencias;
    }
}
