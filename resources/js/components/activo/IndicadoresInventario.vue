<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type Indicador = App.Http.Resources.Panel.IndicadorInventario;

/**
 * Los indicadores de control del inventario, sobre la tabla.
 *
 * La diferencia con la hoja «Resumen» de una hoja de cálculo es que **cada cifra
 * es un enlace a su lista**. Allí «43 activos sin cifrar» es un callejón sin
 * salida —y ahora búscalos—; aquí se pulsa y la tabla de abajo enseña esos 43.
 * Una cifra que no lleva a la lista no se acciona, se mira.
 *
 * Los que están a cero se atenúan pero **no se ocultan**: que «Sin copia de
 * seguridad» ponga 0 es exactamente la información que se busca, y esconderlo
 * dejaría al lector sin saber si es que está bien o es que no se mide.
 */
const props = defineProps<{
    indicadores: Indicador[];
    vigentes: number;
    /** Los filtros aplicados ahora mismo, para marcar el indicador activo. */
    filtros: Record<string, string | string[]>;
}>();

const tonos: Record<string, string> = {
    caducada: 'text-destructive',
    en_progreso: 'text-estado-en-progreso',
    alta: 'text-primary',
    implantado: 'text-estado-implantado',
};

const activos = computed(() => new Set(Object.keys(props.filtros)));
</script>

<template>
    <section class="mb-6" aria-label="Indicadores de control del inventario">
        <div class="mb-2 flex items-baseline justify-between gap-4">
            <h2 class="text-sm font-medium">Control del inventario</h2>
            <p class="text-xs text-muted-foreground">
                Sobre <span class="cifra">{{ vigentes }}</span> activos vigentes. Los retirados no cuentan: no
                están pendientes, están cerrados.
            </p>
        </div>

        <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <li v-for="indicador in indicadores" :key="indicador.clave">
                <Link
                    :href="`/activos?${indicador.filtro}`"
                    class="block h-full rounded-xl border bg-superficie px-3.5 py-3 transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="activos.has(indicador.clave) ? 'border-primary ring-1 ring-primary/25' : ''"
                    :title="indicador.ayuda ?? undefined"
                >
                    <span
                        class="cifra block text-xl font-bold tabular-nums"
                        :class="indicador.valor === 0 ? 'text-muted-foreground' : (tonos[indicador.tono] ?? '')"
                    >
                        {{ indicador.valor }}
                    </span>
                    <span class="mt-0.5 block text-xs leading-snug text-muted-foreground">
                        {{ indicador.etiqueta }}
                    </span>
                </Link>
            </li>
        </ul>
    </section>
</template>
