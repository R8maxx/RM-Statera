<?php

declare(strict_types=1);

namespace App\Domain\Metrica;

use App\Domain\Metrica\Enums\CumplimientoIndicador;
use App\Domain\Metrica\Models\Indicador;
use App\Http\Resources\Panel\Indicador as IndicadorPanel;
use App\Http\Resources\Panel\Reparto;
use App\Http\Resources\Panel\ResumenMetricasPanel;
use Illuminate\Support\Collection;

/**
 * Lo que pide atención en el cuadro de indicadores.
 *
 * **Aquí se ve la colisión de nombre que el módulo lleva declarada**: el `use`
 * de arriba trae `App\Http\Resources\Panel\Indicador` con alias porque en este
 * fichero conviven las dos cosas. Aquél es una baldosa del panel —una cifra con
 * el camino para ir a verla—; éste es el indicador de la 9.1, con objetivo,
 * periodicidad y serie. Se anotan y no se renombran, como `ContextoOrganizacion`.
 *
 * Igual que `RegistroNoConformidades`, `RegistroAuditorias` y `RegistroRiesgos`,
 * vive en el dominio y no en `Http/Resources`: son preguntas del negocio.
 */
final readonly class RegistroIndicadores
{
    public function total(): int
    {
        return Indicador::query()->count();
    }

    /**
     * Lo que va mal de verdad.
     *
     * **Sólo una gasta rojo, y no es «fuera de objetivo».** Quedarse por debajo
     * de una cifra que la propia organización se puso es la distancia que queda,
     * y pintarla de alarma castiga por ponerse objetivos ambiciosos. Lo que sí
     * es un incumplimiento es haberse comprometido a medir cada trimestre y no
     * haber medido: eso es la cláusula 9.1 sin hacer.
     *
     * @return list<IndicadorPanel>
     */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'periodo_sin_medir',
                'Periodo sin medir',
                'periodoSinMedir',
                'caducada',
                'Activos cuyo último periodo cerrado pasó sin registrar medición. La cláusula 9.1 c) pide medir cuando se dijo que se iba a medir.',
            ),
            $this->indicador(
                'fuera_de_objetivo',
                'Fuera de objetivo',
                'fueraDeObjetivo',
                'en_progreso',
                'Su última medición no alcanzó el objetivo que estaba puesto al cerrar ese periodo.',
            ),
        ];
    }

    /**
     * Lo que está declarado a medias.
     *
     * Ninguna gasta rojo: un indicador sin objetivo sigue siendo seguimiento
     * válido —la 9.1 pide medir, y es la 6.2 la que pide comprometerse a una
     * cifra—, y uno recién creado todavía no ha tenido un periodo que cerrar.
     *
     * @return list<IndicadorPanel>
     */
    public function pendientes(): array
    {
        return [
            $this->indicador(
                'sin_medir',
                'Nunca medidos',
                'sinMedir',
                'no_iniciado',
                'Declarados y sin ninguna medición todavía: una promesa, no un seguimiento.',
            ),
            $this->indicador(
                'sin_objetivo',
                'Sin objetivo',
                'sinObjetivo',
                'no_aplica',
                'Se siguen, pero la organización no ha declarado contra qué cifra se juzgan.',
            ),
        ];
    }

    /** El cuadro de indicadores, tal y como lo lee el panel. */
    public function paraElPanel(): ResumenMetricasPanel
    {
        return new ResumenMetricasPanel(
            total: $this->total(),
            activos: Indicador::query()->activos()->count(),
            periodoSinMedir: Indicador::query()->periodoSinMedir()->count(),
            nuncaMedidos: Indicador::query()->activos()->sinMedir()->count(),
            fueraDeObjetivo: Indicador::query()->activos()->fueraDeObjetivo()->count(),
            porCumplimiento: $this->porCumplimiento(),
        );
    }

    /**
     * El reparto por veredicto.
     *
     * **Se resuelve en PHP y no en SQL, a propósito.** El veredicto se deriva de
     * tres columnas de dos tablas y la comparación depende del sentido de cada
     * fila; expresarlo como un `group by` sería escribir por tercera vez la regla
     * que ya están `SentidoIndicador::alcanza()` y el scope. Aquí se puede: los
     * indicadores de una organización son decenas, no decenas de miles, y la
     * consulta trae la última medición de cada uno de una vez.
     *
     * Los tramos a cero no se pintan: cuatro barras de las que tres están vacías
     * no son un reparto, son la lista de valores posibles.
     *
     * @return list<Reparto>
     */
    private function porCumplimiento(): array
    {
        /** @var Collection<int, Indicador> $indicadores */
        $indicadores = Indicador::query()->activos()->with('ultimaMedicion')->get();

        $conteos = $indicadores
            ->countBy(static fn (Indicador $indicador): string => $indicador->cumplimiento()->value)
            ->all();

        $tramos = array_map(
            static fn (CumplimientoIndicador $caso): Reparto => new Reparto(
                clave: $caso->value,
                etiqueta: $caso->etiqueta(),
                valor: $conteos[$caso->value] ?? 0,
                tono: $caso->tono(),
                filtro: null,
            ),
            CumplimientoIndicador::cases(),
        );

        return array_values(array_filter($tramos, static fn (Reparto $tramo): bool => $tramo->valor > 0));
    }

    /**
     * La clave del indicador **es** la del filtro, y el scope **es** el tercer
     * argumento de su `Filtro::porScope()`. Es lo que hace que la cifra y la
     * lista no puedan divergir.
     */
    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, ?string $ayuda = null): IndicadorPanel
    {
        return new IndicadorPanel(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Indicador::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/indicadores',
            ayuda: $ayuda,
        );
    }
}
