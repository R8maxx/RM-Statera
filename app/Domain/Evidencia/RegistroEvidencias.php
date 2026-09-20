<?php

declare(strict_types=1);

namespace App\Domain\Evidencia;

use App\Domain\Evidencia\Models\Evidencia;
use App\Http\Resources\Panel\Indicador;

/**
 * Lo que pide atención en el repositorio de pruebas.
 *
 * **Llega tarde, y con el panel por pestañas.** Las evidencias caducadas eran
 * de los tres rojos originales del producto y eran la única cifra roja que
 * **no se podía pulsar**: vivían en la tarjeta «Pruebas» como un número suelto,
 * y llegar a esa lista exigía filtrar a mano por un rango de fechas. Una cifra
 * que no lleva a su lista sólo se mira, que es literalmente el argumento con el
 * que nació `Indicador`.
 *
 * **Cada indicador cuenta con el mismo scope que usa su filtro de la tabla**, y
 * la clave del indicador es la del filtro: eso es lo que garantiza que pulsar la
 * cifra enseñe exactamente esa cifra.
 */
final readonly class RegistroEvidencias
{
    public function total(): int
    {
        return Evidencia::query()->count();
    }

    /**
     * Lo que va mal de verdad.
     *
     * **Una sola, y es de las tres rojas originales del producto**, junto a la
     * tarea fuera de plazo y el riesgo por encima del umbral: una evidencia
     * caducada es una prueba que ya no prueba, así que la medida que sostenía
     * pasa a estar declarada y no demostrada sin que nadie haya tocado nada.
     *
     * @return list<Indicador>
     */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'caducadas',
                'Caducadas',
                'caducadas',
                'caducada',
                'Pruebas cuya fecha de caducidad ya pasó: la medida que sostenían vuelve a estar sin demostrar.',
            ),
        ];
    }

    /**
     * Lo que está a punto.
     *
     * **«Por caducar» no es rojo**, y conviene decirlo: todavía prueba. Es
     * trabajo con fecha, no un incumplimiento — el mismo reparto que separa
     * «fuera de plazo con la AEPD» de «pendiente de notificar».
     *
     * @return list<Indicador>
     */
    public function pendientes(): array
    {
        return [
            $this->indicador(
                'por_caducar',
                'Por caducar',
                'porCaducar',
                'en_progreso',
                'Vencen en los próximos treinta días. Renovarlas antes es más barato que explicar por qué no se hizo.',
            ),
        ];
    }

    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, string $ayuda): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Evidencia::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/evidencias',
            ayuda: $ayuda,
        );
    }
}
