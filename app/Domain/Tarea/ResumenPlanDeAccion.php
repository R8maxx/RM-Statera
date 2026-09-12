<?php

declare(strict_types=1);

namespace App\Domain\Tarea;

use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Http\Resources\Panel\Indicador;
use App\Http\Resources\Panel\Reparto;
use App\Http\Resources\Panel\ResumenPlanPanel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cómo va el plan de acción, sin abrirlo.
 *
 * Vive en el dominio y no en el controlador por el mismo motivo que
 * `ResumenCumplimiento` y `ResumenInventario`: son las preguntas que contestará
 * el informe de estado, y las que se hacen en voz alta delante de un auditor.
 *
 * **Se cuenta sobre tareas abiertas**, igual que el cumplimiento se cuenta sobre
 * lo exigible y el inventario sobre lo vigente. Una tarea descartada no está
 * pendiente: está cerrada, con su motivo en el histórico.
 *
 * El reparto de papeles es el del inventario: **el panel contesta cómo va la
 * cosa y la tabla contesta qué pide acción hoy**. Por eso `alertas()` y
 * `pendientesDeCompletar()` no entran en `paraElPanel()`.
 */
final class ResumenPlanDeAccion
{
    /**
     * Lo que va mal hoy.
     *
     * Una tarea vencida es una fecha que se comprometió y pasó; una bloqueada es
     * alguien parado esperando a otro. Lo que falta por decidir —responsable,
     * plazo— no entra aquí: es otra clase de deuda y en tarjetas competiría en
     * peso con lo que sí arde.
     *
     * @return list<Indicador>
     */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'vencidas',
                'Vencidas',
                'vencidas',
                'caducada',
                'Abiertas con la fecha límite pasada. Lo cerrado no vence: está cerrado.',
            ),
            $this->indicador(
                'bloqueadas',
                'Bloqueadas',
                'bloqueadas',
                'en_progreso',
                'Alguien las cogió y no puede avanzar. No se mueven solas.',
            ),
            $this->indicador(
                'por_vencer',
                'Vencen en 30 días',
                'porVencer',
                'en_progreso',
            ),
        ];
    }

    /**
     * Lo que está a medias de decidir.
     *
     * Va en una línea de texto y no en tarjetas: la tarea existe y no hay nada
     * roto, sencillamente le falta un dato. Una tarea sin responsable no la hace
     * nadie y una sin plazo no vence nunca — no saltará en ningún aviso.
     *
     * @return list<Indicador>
     */
    public function pendientesDeCompletar(): array
    {
        return [
            $this->indicador('sin_responsable', 'responsable', 'sinResponsable', 'no_iniciado'),
            $this->indicador('sin_plazo', 'fecha límite', 'sinPlazo', 'no_iniciado'),
        ];
    }

    /**
     * El plan, tal y como lo lee el panel.
     *
     * **Sin porcentaje de tareas hechas, a propósito.** Esa cifra sube sola al
     * cerrar y baja sola al apuntar trabajo nuevo: mide actividad, no salud, y un
     * indicador que castiga por apuntar lo que falta enseña a no apuntarlo. Lo
     * que abre la tarjeta es cuántas quedan abiertas, con su denominador.
     */
    public function paraElPanel(): ResumenPlanPanel
    {
        return new ResumenPlanPanel(
            total: Tarea::query()->count(),
            abiertas: Tarea::query()->abiertas()->count(),
            vencidas: Tarea::query()->vencidas()->count(),
            sinResponsable: Tarea::query()->sinResponsable()->count(),
            porEstado: $this->porEstado(),
            porPrioridad: $this->porPrioridad(),
        );
    }

    /**
     * El reparto de lo abierto por estado.
     *
     * En el orden del tablero —pendiente, en curso, bloqueada— y no en el del
     * enum: es el orden en el que el trabajo avanza, y el mismo que verá quien
     * abra `/tareas/tablero`. Lo cerrado no entra: el reparto contesta «en qué
     * punto está lo que queda».
     *
     * @return list<Reparto>
     */
    private function porEstado(): array
    {
        $conteos = $this->contar('estado', fn (Builder $consulta) => $consulta->abiertas());

        $tramos = [];

        foreach ([EstadoTarea::Pendiente, EstadoTarea::EnCurso, EstadoTarea::Bloqueada] as $estado) {
            $tramos[] = new Reparto(
                clave: $estado->value,
                etiqueta: $estado->etiqueta(),
                valor: $conteos[$estado->value] ?? 0,
                tono: $estado->tono(),
                filtro: "filter[estado]={$estado->value}",
            );
        }

        return $this->conValor($tramos);
    }

    /**
     * El reparto de lo abierto por prioridad, de lo que más corre a lo que menos.
     *
     * @return list<Reparto>
     */
    private function porPrioridad(): array
    {
        $conteos = $this->contar('prioridad', fn (Builder $consulta) => $consulta->abiertas());

        $prioridades = PrioridadTarea::cases();

        usort(
            $prioridades,
            static fn (PrioridadTarea $a, PrioridadTarea $b): int => $b->peso() <=> $a->peso(),
        );

        return $this->conValor(array_map(
            fn (PrioridadTarea $prioridad): Reparto => new Reparto(
                clave: $prioridad->value,
                etiqueta: $prioridad->etiqueta(),
                valor: $conteos[$prioridad->value] ?? 0,
                tono: $this->tonoDePrioridad($prioridad),
                filtro: "filter[prioridad]={$prioridad->value}",
            ),
            $prioridades,
        ));
    }

    /**
     * La prioridad es ordinal, así que sube en énfasis en vez de cambiar de
     * significado. Mismo criterio que la categoría del ENS, y sin rojo: el rojo
     * es del plazo.
     */
    private function tonoDePrioridad(PrioridadTarea $prioridad): string
    {
        return match ($prioridad) {
            PrioridadTarea::Baja => 'basica',
            PrioridadTarea::Media => 'exigible',
            PrioridadTarea::Alta => 'media',
            PrioridadTarea::Critica => 'alta',
        };
    }

    /**
     * Un solo `GROUP BY` en vez de una consulta por valor.
     *
     * @param  callable(Builder<Tarea>): Builder<Tarea>  $acotar
     * @return array<string, int>
     */
    private function contar(string $columna, callable $acotar): array
    {
        /** @var array<string, int> $conteos */
        $conteos = $acotar(Tarea::query())
            ->selectRaw("{$columna} as clave, count(*) as total")
            ->groupBy($columna)
            ->pluck('total', 'clave')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        return $conteos;
    }

    /**
     * Los tramos a cero no se pintan: cuatro barras de las que tres están vacías
     * no son un reparto, son una lista de valores posibles.
     *
     * @param  list<Reparto>  $tramos
     * @return list<Reparto>
     */
    private function conValor(array $tramos): array
    {
        return array_values(array_filter($tramos, static fn (Reparto $tramo): bool => $tramo->valor > 0));
    }

    /**
     * Cada indicador cuenta con **el mismo scope** que usa su filtro de la tabla.
     *
     * No es una comodidad: es lo que garantiza que pulsar una cifra enseñe
     * exactamente esa cifra. Con la condición escrita dos veces, el día que una
     * de las dos cambie el panel dirá 12 y la lista enseñará 9, y a partir de ahí
     * nadie vuelve a fiarse del panel.
     */
    private function indicador(
        string $clave,
        string $etiqueta,
        string $scope,
        string $tono,
        ?string $ayuda = null,
    ): Indicador {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Tarea::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/tareas',
            ayuda: $ayuda,
        );
    }
}
