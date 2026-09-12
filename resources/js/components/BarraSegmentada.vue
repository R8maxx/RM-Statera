<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import { tono } from '@/lib/tonos';
import { computed } from 'vue';

/**
 * El reparto de implantaciones por estado, en una sola barra.
 *
 * Sustituye a la barra verde única que había: aquella pintaba sólo lo
 * implantado y tiraba a la basura la diferencia entre lo que está planificado y
 * lo que ya está en marcha, que es justo lo que se mira para saber si un
 * sistema avanza o está parado.
 *
 * Los tramos van separados por 2 px de superficie y con los extremos
 * redondeados: pegados, dos estados contiguos se leen como uno solo, y con
 * protanopia el verde y el ámbar ya cuestan de separar de por sí. El orden de
 * los tramos lo decide quien llama —`ResumenCumplimiento` lo fija con el
 * motivo escrito—, no este componente.
 */
export interface Segmento {
    clave: string;
    etiqueta: string;
    valor: number;
    /** El color, cuando no coincide con la clave. Un tipo de activo lo declara. */
    tono?: string;
}

const props = withDefaults(
    defineProps<{
        segmentos: Segmento[];
        alto?: 'fino' | 'normal';
        /**
         * La leyenda con la etiqueta y la cifra de cada tramo.
         *
         * Con dos o más tramos la identidad no puede quedarse sólo en el color.
         * Se apaga donde la barra es un indicador compacto —una fila de una
         * lista—, no la gráfica principal.
         */
        leyenda?: boolean;
    }>(),
    { alto: 'normal', leyenda: false },
);

/*
 * Los colores son los del dominio y viven en `lib/tonos.ts`, una sola vez para
 * todo el producto. Se usa `tramo` y no `relleno`: en una barra, los dos grises
 * van más apagados a propósito —son el hueco que queda por llenar y a plena
 * saturación pesan tanto como lo que sí se ha hecho—.
 *
 * Los tramos a cero no se pintan: un tramo invisible sigue ocupando su hueco de
 * separación y deja la barra con agujeros.
 */
const total = computed(() => props.segmentos.reduce((suma, segmento) => suma + segmento.valor, 0));

const visibles = computed(() =>
    props.segmentos
        .filter((segmento) => segmento.valor > 0)
        .map((segmento) => ({
            ...segmento,
            porcentaje: total.value === 0 ? 0 : (segmento.valor / total.value) * 100,
            fondo: tono(segmento.tono ?? segmento.clave).tramo,
        })),
);

const descripcion = computed(
    () => visibles.value.map((segmento) => `${segmento.etiqueta}: ${segmento.valor}`).join(', ')
        || 'Sin datos',
);
</script>

<template>
    <div class="w-full">
        <!-- Los tramos reparten el ancho con `flex-grow` y no con `width`: así
             los 2 px de separación salen del reparto en vez de desbordar la
             caja y recortar el último tramo. -->
        <div
            class="flex w-full gap-0.5 overflow-hidden rounded-full bg-muted"
            :class="alto === 'fino' ? 'h-1.5' : 'h-2.5'"
            role="img"
            :aria-label="descripcion"
        >
            <div
                v-for="segmento in visibles"
                :key="segmento.clave"
                class="h-full min-w-[2px] shrink basis-0 rounded-full transition-[flex-grow] duration-500 ease-marca"
                :class="segmento.fondo"
                :style="{ flexGrow: segmento.porcentaje }"
                :title="`${segmento.etiqueta}: ${segmento.valor}`"
            />
        </div>

        <ul v-if="leyenda && visibles.length > 0" class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5">
            <li v-for="segmento in visibles" :key="segmento.clave" class="flex items-center gap-1.5 text-xs">
                <span class="size-2 shrink-0 rounded-full" :class="segmento.fondo" aria-hidden="true" />
                <span class="text-muted-foreground">{{ segmento.etiqueta }}</span>
                <Cifra class="font-medium" :valor="segmento.valor" />
            </li>
        </ul>
    </div>
</template>
