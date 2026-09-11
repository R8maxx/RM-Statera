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
        return $this->paraRequisitos([$requisitoId])[$requisitoId] ?? [];
    }

    /**
     * Lo mismo para muchos requisitos a la vez, en dos consultas.
     *
     * Existe por la Declaración de Aplicabilidad: llamar a `paraRequisito()` en
     * un bucle sobre los noventa y tres controles del Anexo A son ciento ochenta
     * y seis consultas, y eso convierte un documento en un trabajo que se come
     * el tiempo de la cola. Aquí los mapeos de todos los requisitos salen en una
     * consulta y sus implantaciones en otra.
     *
     * @param  list<int>  $requisitoIds
     * @return array<int, list<Correspondencia>> Indexado por requisito; sólo aparecen los que tienen alguna.
     */
    public function paraRequisitos(array $requisitoIds): array
    {
        if ($requisitoIds === []) {
            return [];
        }

        $mapeos = Mapeo::query()
            ->with(['origen.marco', 'destino.marco'])
            ->whereIn('requisito_origen_id', $requisitoIds)
            ->orWhereIn('requisito_destino_id', $requisitoIds)
            ->get();

        $buscados = array_flip($requisitoIds);

        /** @var array<int, array<int, Requisito>> $otrosPorRequisito */
        $otrosPorRequisito = [];
        /** @var array<int, array<int, Mapeo>> $mapeoPorPareja */
        $mapeoPorPareja = [];
        /** @var array<int, true> $necesarios */
        $necesarios = [];

        foreach ($mapeos as $mapeo) {
            /*
             * Un mapeo puede tocar dos requisitos buscados a la vez —los dos
             * extremos están en la lista—, y entonces cuenta para los dos: es
             * justo lo que pasa en una SoA que lleva la columna de
             * correspondencia ENS y en una DdA que lleva la de ISO.
             */
            foreach ([[$mapeo->requisito_origen_id, $mapeo->destino], [$mapeo->requisito_destino_id, $mapeo->origen]] as [$ladoId, $otro]) {
                if (! isset($buscados[$ladoId]) || $otro === null || $otro->id === $ladoId) {
                    continue;
                }

                $otrosPorRequisito[$ladoId][$otro->id] = $otro;
                $mapeoPorPareja[$ladoId][$otro->id] = $mapeo;
                $necesarios[$otro->id] = true;
            }
        }

        if ($necesarios === []) {
            return [];
        }

        $implantaciones = Implantacion::query()
            ->with('sistema')
            ->whereIn('requisito_id', array_keys($necesarios))
            ->get()
            ->groupBy('requisito_id');

        $resultado = [];

        foreach ($otrosPorRequisito as $requisitoId => $otros) {
            $correspondencias = [];

            foreach ($otros as $id => $requisito) {
                $mapeo = $mapeoPorPareja[$requisitoId][$id];

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

            $resultado[$requisitoId] = $correspondencias;
        }

        return $resultado;
    }
}
