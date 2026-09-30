<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

export interface ServicioComparado {
    id: number;
    codigo: string;
    nombre: string;
    rtoObjetivo: number | null;
    rtoAlcanzado: number | null;
    rpoObjetivo: number | null;
    rpoAlcanzado: number | null;
    /** `true` = se pasó, `false` = se cumplió, `null` = falta un dato para decirlo. */
    excedeRto: boolean | null;
    /** El BIA del servicio, para enlazarlo; `null` si el servicio no tiene. */
    biaId: number | null;
}

/**
 * Por servicio, lo que el BIA prometía frente a lo que la prueba alcanzó de
 * verdad, en barras sobre un mismo eje de horas. § 4.11.
 *
 * **Un eje para todos los servicios**, no uno por fila: lo que se compara es
 * cuánto tardó cada uno contra su propia marca, y con escalas distintas una
 * barra corta de un servicio lento parecería igual que una larga de uno
 * rápido. El eje se redondea a días enteros.
 *
 * **`destructive` sólo en el tramo que pasa del objetivo.** Es el único rojo de
 * toda la ficha de una prueba, a propósito: un RTO alcanzado que se queda
 * dentro del objetivo no es una alerta, y uno que todavía no se ha registrado
 * —`null`, mientras la prueba sigue planificada— tampoco lo es. El rojo es
 * para el incumplimiento medido, no para lo que falta por medir, y por eso la
 * barra se parte: hasta la marca en teal, lo que sobra en rojo.
 *
 * **El RPO va en texto bajo la barra.** Es otra magnitud —datos perdidos, no
 * tiempo caído— y en el mismo eje saldría como una raya de cuatro píxeles.
 */
const props = defineProps<{ servicios: ServicioComparado[] }>();

const escala = computed(() => {
    const valores = props.servicios.flatMap((servicio) => [servicio.rtoObjetivo ?? 0, servicio.rtoAlcanzado ?? 0]);

    return Math.max(24, Math.ceil(Math.max(...valores, 0) / 24) * 24);
});

/** Marcas del eje: en días enteros cuando caben tres, en cuartos si no. */
const marcas = computed(() => {
    const divisiones = (escala.value / 3) % 24 === 0 ? 3 : 4;

    return Array.from({ length: divisiones + 1 }, (_, indice) => (escala.value / divisiones) * indice);
});

function porcentaje(horas: number): number {
    return (horas / escala.value) * 100;
}

/** La parte de la barra que queda dentro del objetivo y la que lo pasa. */
function tramos(servicio: ServicioComparado): { dentro: number; fuera: number } | null {
    if (servicio.rtoAlcanzado === null) {
        return null;
    }

    const objetivo = servicio.rtoObjetivo ?? servicio.rtoAlcanzado;
    const dentro = Math.min(servicio.rtoAlcanzado, objetivo);

    return { dentro: porcentaje(dentro), fuera: porcentaje(servicio.rtoAlcanzado - dentro) };
}

function descripcion(servicio: ServicioComparado): string {
    const objetivo = servicio.rtoObjetivo === null ? 'sin RTO objetivo' : `frente a un objetivo de ${servicio.rtoObjetivo} horas`;

    return servicio.rtoAlcanzado === null
        ? `RTO sin medir, ${objetivo}`
        : `RTO alcanzado ${servicio.rtoAlcanzado} horas ${objetivo}`;
}

function rpo(servicio: ServicioComparado): string {
    if (servicio.rpoAlcanzado === null) {
        return servicio.rpoObjetivo === null ? 'RPO sin objetivo ni medida' : `RPO sin medir · objetivo ${servicio.rpoObjetivo} h`;
    }

    if (servicio.rpoObjetivo === null) {
        return `RPO ${servicio.rpoAlcanzado} h`;
    }

    const coletilla =
        servicio.rpoAlcanzado === servicio.rpoObjetivo
            ? ' · en el límite'
            : servicio.rpoAlcanzado > servicio.rpoObjetivo
              ? ' · por encima del objetivo'
              : '';

    return `RPO ${servicio.rpoAlcanzado} h de ${servicio.rpoObjetivo} h${coletilla}`;
}
</script>

<template>
    <div class="space-y-4">
        <ul class="divide-y divide-border border-b">
            <li
                v-for="servicio in servicios"
                :key="servicio.id"
                class="grid items-center gap-x-4 gap-y-2 py-4 sm:grid-cols-[12rem_minmax(0,1fr)_6.5rem]"
            >
                <div class="flex min-w-0 flex-col">
                    <Link
                        v-if="servicio.biaId"
                        :href="`/continuidad/bia/${servicio.biaId}`"
                        class="truncate text-sm font-medium underline-offset-4 hover:underline"
                    >
                        {{ servicio.nombre }}
                    </Link>
                    <span v-else class="truncate text-sm font-medium">{{ servicio.nombre }}</span>
                    <span class="cifra text-xs text-muted-foreground">{{ servicio.codigo }}</span>
                </div>

                <div class="space-y-2">
                    <div role="img" :aria-label="descripcion(servicio)" class="relative h-5 rounded-md bg-superficie">
                        <template v-if="tramos(servicio)">
                            <div
                                class="absolute inset-y-0 left-0 bg-marca-500"
                                :class="tramos(servicio)!.fuera > 0 ? 'rounded-l-md' : 'rounded-md'"
                                :style="{ width: `${tramos(servicio)!.dentro}%` }"
                            />
                            <div
                                v-if="tramos(servicio)!.fuera > 0"
                                class="absolute inset-y-0 rounded-r-md bg-destructive"
                                :style="{ left: `calc(${tramos(servicio)!.dentro}% + 2px)`, width: `calc(${tramos(servicio)!.fuera}% - 2px)` }"
                            />
                        </template>
                        <div
                            v-if="servicio.rtoObjetivo !== null"
                            class="absolute -top-1.5 h-8 w-0.5 -translate-x-1/2 bg-foreground"
                            :style="{ left: `${porcentaje(servicio.rtoObjetivo)}%` }"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">{{ rpo(servicio) }}</p>
                </div>

                <div class="cifra flex flex-col text-sm sm:items-end">
                    <span v-if="servicio.rtoAlcanzado !== null">
                        <span class="font-medium" :class="servicio.excedeRto ? 'text-destructive' : undefined">{{ servicio.rtoAlcanzado }} h</span>
                        <span v-if="servicio.rtoObjetivo !== null" class="text-muted-foreground"> / {{ servicio.rtoObjetivo }} h</span>
                    </span>
                    <span v-else class="text-muted-foreground">Sin medir</span>
                    <span
                        v-if="servicio.excedeRto && servicio.rtoAlcanzado !== null && servicio.rtoObjetivo !== null"
                        class="text-xs font-medium text-destructive"
                    >
                        +{{ servicio.rtoAlcanzado - servicio.rtoObjetivo }} h
                    </span>
                    <span
                        v-else-if="servicio.excedeRto === false && servicio.rtoAlcanzado !== null && servicio.rtoObjetivo !== null"
                        class="text-xs text-muted-foreground"
                    >
                        {{ servicio.rtoObjetivo - servicio.rtoAlcanzado }} h de margen
                    </span>
                </div>
            </li>
        </ul>

        <div aria-hidden="true" class="hidden sm:grid sm:grid-cols-[12rem_minmax(0,1fr)_6.5rem] sm:gap-x-4">
            <span />
            <div class="cifra relative h-4 text-xs text-muted-foreground">
                <span
                    v-for="(marca, indice) in marcas"
                    :key="marca"
                    class="absolute"
                    :class="indice === 0 ? 'left-0' : indice === marcas.length - 1 ? 'right-0' : '-translate-x-1/2'"
                    :style="indice === 0 || indice === marcas.length - 1 ? undefined : { left: `${porcentaje(marca)}%` }"
                >
                    {{ marca === 0 ? '0' : `${marca} h` }}
                </span>
            </div>
            <span />
        </div>

        <ul class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-muted-foreground">
            <li class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm bg-marca-500" />Tiempo de recuperación</li>
            <li class="inline-flex items-center gap-1.5"><span class="h-3.5 w-0.5 bg-foreground" />RTO objetivo del BIA</li>
            <li class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm bg-destructive" />Por encima del RTO</li>
        </ul>
    </div>
</template>
