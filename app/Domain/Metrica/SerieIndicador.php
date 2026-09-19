<?php

declare(strict_types=1);

namespace App\Domain\Metrica;

use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;
use App\Http\Resources\Metrica\PuntoSerie;
use Illuminate\Support\Collection;

/**
 * La serie histórica de un indicador, que es lo que el § 4.14 pide y el panel
 * nunca ha tenido.
 *
 * Va **de lo antiguo a lo reciente**, al revés que la relación del modelo: una
 * tabla se lee mejor con lo último arriba y una gráfica se dibuja de izquierda a
 * derecha en el tiempo. Se ordena aquí en vez de en dos sitios.
 *
 * **Cada punto se juzga contra el objetivo congelado en su propia fila**, no
 * contra el del indicador de hoy. Sin eso, subir el listón en marzo repintaría
 * de ámbar todos los trimestres anteriores y la serie contaría una historia que
 * no ocurrió.
 */
final readonly class SerieIndicador
{
    /** @return list<PuntoSerie> */
    public function de(Indicador $indicador, int $puntos = 12): array
    {
        /** @var Collection<int, Medicion> $mediciones */
        $mediciones = $indicador->mediciones()->limit($puntos)->get();

        return $mediciones
            ->sortBy(static fn (Medicion $medicion): string => $medicion->periodo_inicio->toDateString())
            ->map(function (Medicion $medicion) use ($indicador): PuntoSerie {
                $cumplimiento = $indicador->cumplimiento($medicion);

                return new PuntoSerie(
                    periodo: $medicion->periodo_inicio->toDateString(),
                    etiqueta: $indicador->periodicidad->etiquetaDe($medicion->periodo_inicio),
                    valor: (float) $medicion->valor,
                    valorEscrito: $indicador->unidad->escribir((float) $medicion->valor),
                    objetivo: $medicion->objetivo === null ? null : (float) $medicion->objetivo,
                    objetivoEscrito: $medicion->objetivo === null
                        ? null
                        : $indicador->sentido->comparador().' '.$indicador->unidad->escribir((float) $medicion->objetivo),
                    fraccion: $medicion->fraccion(),
                    cumplimiento: $cumplimiento->value,
                    cumplimientoEtiqueta: $cumplimiento->etiqueta(),
                    tono: $cumplimiento->tono(),
                    icono: $cumplimiento->icono(),
                    origen: $medicion->origen->value,
                    nota: $medicion->nota,
                );
            })
            ->values()
            ->all();
    }
}
