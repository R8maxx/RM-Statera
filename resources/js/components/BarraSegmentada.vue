<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
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
 * Los colores son los del dominio, declarados en `app.css`.
 *
 * Escritos enteros y no compuestos (`bg-${clave}`) porque Tailwind analiza el
 * fichero como texto: una clase construida en ejecución no se genera y el tramo
 * sale sin fondo.
 */
const fondos: Record<string, string> = {
    implantado: 'bg-estado-implantado',
    en_progreso: 'bg-estado-en-progreso',
    planificado: 'bg-estado-planificado',
    no_iniciado: 'bg-estado-no-iniciado/45',
    no_aplica: 'bg-estado-no-aplica/35',

    /*
     * `caducada` es el tono con el que el dominio nombra lo que va mal: una
     * evidencia sin vigencia, un control que dice «no». Los otros tres valores
     * de un control ya están arriba —«sí» es `implantado`, «por confirmar» es
     * `en_progreso` y «no aplica» es gris—, porque lo que llega del servidor es
     * el TONO, no la clave del valor.
     */
    caducada: 'bg-destructive',

    /* Tipología de activos, con el prefijo que la separa de los estados. */
    'tipo:servicios': 'bg-tipo-servicios',
    'tipo:datos': 'bg-tipo-datos',
    'tipo:software': 'bg-tipo-software',
    'tipo:hardware': 'bg-tipo-hardware',
    'tipo:comunicaciones': 'bg-tipo-comunicaciones',
    'tipo:soportes': 'bg-tipo-soportes',
    'tipo:equipamiento_auxiliar': 'bg-tipo-equipamiento-auxiliar',
    'tipo:instalaciones': 'bg-tipo-instalaciones',
    'tipo:personal': 'bg-tipo-personal',

    /* Ciclo de vida del activo, sobre los mismos tokens de estado. */
    en_produccion: 'bg-estado-implantado',
    en_mantenimiento: 'bg-estado-en-progreso',
    en_reparacion: 'bg-estado-en-progreso',
    prestado: 'bg-estado-planificado',
    retirado: 'bg-estado-no-aplica/60',
    dado_de_baja: 'bg-estado-no-aplica/35',
};

const total = computed(() => props.segmentos.reduce((suma, segmento) => suma + segmento.valor, 0));

const visibles = computed(() =>
    props.segmentos
        .filter((segmento) => segmento.valor > 0)
        .map((segmento) => ({
            ...segmento,
            porcentaje: total.value === 0 ? 0 : (segmento.valor / total.value) * 100,
            fondo: fondos[segmento.tono ?? segmento.clave] ?? 'bg-muted-foreground/40',
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
