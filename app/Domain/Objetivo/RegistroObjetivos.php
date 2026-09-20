<?php

declare(strict_types=1);

namespace App\Domain\Objetivo;

use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Models\Objetivo;
use App\Http\Resources\Panel\Indicador;
use App\Http\Resources\Panel\Reparto;
use App\Http\Resources\Panel\ResumenObjetivosPanel;

/**
 * Lo que pide atención en el registro de objetivos de seguridad.
 *
 * **Cada indicador cuenta con el mismo scope que usa su filtro de la tabla**, y
 * la clave del indicador es la del filtro: eso es lo que garantiza que pulsar la
 * cifra enseñe exactamente esa cifra. Con la condición escrita dos veces, el día
 * que cambie una el registro dirá 3 y la lista enseñará 1, y a partir de ahí
 * nadie se fía del número.
 *
 * Igual que `RegistroNoConformidades` y `RegistroRiesgos`, vive en el dominio y
 * no en `Http/Resources`: son preguntas del negocio, no de una pantalla.
 */
final readonly class RegistroObjetivos
{
    public function total(): int
    {
        return Objetivo::query()->count();
    }

    /**
     * Lo que va mal de verdad.
     *
     * **Una sola, y gasta el único rojo del módulo.** Un objetivo aprobado cuya
     * fecha pasó y que nadie ha cerrado es la 6.2 sin terminar: la organización
     * se comprometió por escrito a algo para una fecha y esa fecha ya pasó sin
     * que nadie diga si se consiguió. Es lo primero que encuentra la revisión por
     * la dirección.
     *
     * **Quedarse corto respecto a la cifra NO está aquí**, y es deliberado: es la
     * distancia que queda, y pintarla de alarma castiga por ponerse objetivos
     * ambiciosos. Mismo criterio que los cuatro veredictos del § 4.14.
     *
     * @return list<Indicador>
     */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'vencidos',
                'Fuera de plazo',
                'vencidos',
                'caducada',
                'Aprobados cuya fecha objetivo ya pasó y que nadie ha cerrado.',
            ),
        ];
    }

    /**
     * Lo que está a medias o mal montado.
     *
     * Ninguno gasta rojo: un objetivo recién propuesto al que todavía no se le ha
     * colgado un indicador no es un incumplimiento, es trabajo sin terminar.
     *
     * @return list<Indicador>
     */
    public function pendientes(): array
    {
        return [
            $this->indicador(
                'vivos',
                'En curso',
                'vivos',
                'en_progreso',
                'Propuestos y aprobados: los que siguen vivos.',
            ),
            $this->indicador(
                'sin_indicador',
                'Sin indicador',
                'sinIndicador',
                'planificado',
                'La cláusula 6.2 exige que el objetivo sea medible y decir cómo se evaluarán los resultados.',
            ),
            $this->indicador(
                'sin_actuacion',
                'Sin actuación',
                'sinActuacion',
                'no_iniciado',
                'Vivos sin ninguna tarea abierta detrás: nadie los está empujando.',
            ),
        ];
    }

    /**
     * El registro, tal y como lo lee el panel (§ 4.14).
     *
     * Mismo reparto de papeles que en el resto del producto: **el panel contesta
     * cómo va la cosa y la tabla contesta qué pide acción hoy**, así que
     * `alertas()` y `pendientes()` no entran aquí. Lo que sí sube es
     * `sinIndicador`, porque no es una alerta de trabajo: es un requisito de la
     * norma sin cumplir.
     */
    public function paraElPanel(): ResumenObjetivosPanel
    {
        return new ResumenObjetivosPanel(
            total: $this->total(),
            vivos: Objetivo::query()->vivos()->count(),
            vencidos: Objetivo::query()->vencidos()->count(),
            sinIndicador: Objetivo::query()->sinIndicador()->count(),
            sinActuacion: Objetivo::query()->sinActuacion()->count(),
            porEstado: $this->porEstado(),
        );
    }

    /**
     * El reparto por estado, en el orden en el que el objetivo avanza.
     *
     * **Con los estados cerrados dentro**, como el de no conformidades y a
     * diferencia del plan de acción. La pregunta aquí es «de los objetivos que
     * nos pusimos, cuántos alcanzamos», que es literalmente una de las siete
     * entradas de la revisión por la dirección; sin `alcanzado` y `no_alcanzado`
     * en la barra, esa entrada no se vería.
     *
     * Los tramos a cero no se pintan: cinco barras de las que cuatro están vacías
     * no son un reparto, son la lista de valores posibles.
     *
     * @return list<Reparto>
     */
    private function porEstado(): array
    {
        /** @var array<string, int> $conteos */
        $conteos = Objetivo::query()
            ->selectRaw('estado as clave, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'clave')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        $tramos = array_map(
            static fn (EstadoObjetivo $estado): Reparto => new Reparto(
                clave: $estado->value,
                etiqueta: $estado->etiqueta(),
                valor: $conteos[$estado->value] ?? 0,
                tono: $estado->tono(),
                filtro: "filter[estado]={$estado->value}",
            ),
            EstadoObjetivo::cases(),
        );

        return array_values(array_filter($tramos, static fn (Reparto $tramo): bool => $tramo->valor > 0));
    }

    /**
     * La clave del indicador **es** la del filtro, y el scope **es** el tercer
     * argumento de su `Filtro::porScope()`. No es una coincidencia que haya que
     * mantener: es lo que hace que la cifra y la lista no puedan divergir.
     */
    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, ?string $ayuda = null): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Objetivo::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/objetivos',
            ayuda: $ayuda,
        );
    }
}
