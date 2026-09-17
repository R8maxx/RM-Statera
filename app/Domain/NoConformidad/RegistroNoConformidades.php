<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad;

use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Http\Resources\Panel\Indicador;
use App\Http\Resources\Panel\Reparto;
use App\Http\Resources\Panel\ResumenNoConformidadesPanel;

/**
 * Lo que pide atención en el registro de no conformidades.
 *
 * **Cada indicador cuenta con el mismo scope que usa su filtro de la tabla**, y
 * la clave del indicador es la del filtro: eso es lo que garantiza que pulsar la
 * cifra enseñe exactamente esa cifra. Con la condición escrita dos veces, el día
 * que cambie una el registro dirá 3 y la lista enseñará 1, y a partir de ahí
 * nadie se fía del número.
 *
 * Igual que `RegistroAuditorias` y `RegistroRiesgos`, vive en el dominio y no en
 * `Http/Resources`: son preguntas del negocio, no de una pantalla.
 */
final readonly class RegistroNoConformidades
{
    public function total(): int
    {
        return NoConformidad::query()->count();
    }

    /**
     * Lo que va mal de verdad.
     *
     * **Las dos gastan rojo, y son las únicas del módulo que lo hacen.** Una no
     * conformidad que se pasó de la fecha prevista es el mismo caso que una tarea
     * vencida, que es donde el rojo ya estaba. Y una **pendiente de verificar** lo
     * gasta por un motivo que conviene decir: es la cláusula 10.2 e) sin hacer, y
     * es el paso que el auditor comprueba **precisamente porque es el que todo el
     * mundo se salta** — una no conformidad cerrada y sin verificar se lee como
     * resuelta y no lo está.
     *
     * @return list<Indicador>
     */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'vencidas',
                'Fuera de plazo',
                'vencidas',
                'caducada',
                'Abiertas cuya fecha prevista de tratamiento ya pasó.',
            ),
            $this->indicador(
                'pendientes_de_verificar',
                'Sin verificar',
                'pendientesDeVerificar',
                'caducada',
                'Tratadas y sin comprobar que la corrección sirvió: la cláusula 10.2 e) pide esa comprobación.',
            ),
        ];
    }

    /**
     * Lo que está a medias o mal montado.
     *
     * Ninguna gasta rojo: no tener todavía causa raíz escrita no es un
     * incumplimiento, es trabajo sin terminar. El día que se pase de plazo, lo
     * dirá el indicador de arriba.
     *
     * @return list<Indicador>
     */
    public function pendientes(): array
    {
        return [
            $this->indicador(
                'abiertas',
                'Abiertas',
                'abiertas',
                'en_progreso',
                'Registradas y todavía sin cerrar el tratamiento.',
            ),
            $this->indicador(
                'sin_accion',
                'Sin acción correctiva',
                'sinAccion',
                'planificado',
                'Abiertas sin ninguna tarea viva detrás: nadie las está tratando.',
            ),
            $this->indicador(
                'sin_causa_raiz',
                'Sin causa raíz',
                'sinCausaRaiz',
                'no_iniciado',
                'La cláusula 10.2 b) pide analizar por qué pasó; sin eso, la corrección trata el síntoma.',
            ),
        ];
    }

    /**
     * El registro, tal y como lo lee el panel (§ 4.14).
     *
     * Mismo reparto de papeles que en el inventario y en el plan de acción: **el
     * panel contesta cómo va la cosa y la tabla contesta qué pide acción hoy**,
     * así que `alertas()` y `pendientes()` no entran aquí. Lo que sí sube es
     * `sinVerificar`, porque no es una alerta de trabajo: es un requisito de la
     * norma sin cumplir, y en el cuadro de mando se mira al lado de las abiertas.
     */
    public function paraElPanel(): ResumenNoConformidadesPanel
    {
        return new ResumenNoConformidadesPanel(
            total: $this->total(),
            abiertas: NoConformidad::query()->abiertas()->count(),
            vencidas: NoConformidad::query()->vencidas()->count(),
            sinVerificar: NoConformidad::query()->pendientesDeVerificar()->count(),
            sinAccion: NoConformidad::query()->sinAccion()->count(),
            porEstado: $this->porEstado(),
        );
    }

    /**
     * El reparto por estado, en el orden en el que el trabajo avanza.
     *
     * **Aquí sí entran los estados cerrados**, a diferencia del reparto del plan
     * de acción, que sólo cuenta lo abierto. El motivo es la pregunta: en tareas
     * se pregunta «en qué punto está lo que queda» y aquí se pregunta «cuántas de
     * las que hemos encontrado hemos llegado a verificar». Sin `verificada` en la
     * barra, la única cifra que la norma pide no se vería.
     *
     * Los tramos a cero no se pintan: cinco barras de las que cuatro están vacías
     * no son un reparto, son la lista de valores posibles.
     *
     * @return list<Reparto>
     */
    private function porEstado(): array
    {
        /** @var array<string, int> $conteos */
        $conteos = NoConformidad::query()
            ->selectRaw('estado as clave, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'clave')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        $tramos = array_map(
            static fn (EstadoNoConformidad $estado): Reparto => new Reparto(
                clave: $estado->value,
                etiqueta: $estado->etiqueta(),
                valor: $conteos[$estado->value] ?? 0,
                tono: $estado->tono(),
                filtro: "filter[estado]={$estado->value}",
            ),
            EstadoNoConformidad::cases(),
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
            valor: NoConformidad::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/no-conformidades',
            ayuda: $ayuda,
        );
    }
}
