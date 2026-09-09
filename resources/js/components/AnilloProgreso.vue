<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { duracion } from '@/lib/motion';
import { useTransition, TransitionPresets } from '@vueuse/core';
import { computed, onMounted, ref } from 'vue';

/**
 * El porcentaje global de implantación, que es la cifra que abre el panel.
 *
 * Crece de cero hasta su valor al montar. La razón no es decorativa: el número
 * es el mensaje de la pantalla, y verlo llegar hace que se lea, en lugar de que
 * se dé por leído. Con `prefers-reduced-motion` aparece ya en su sitio.
 */
const props = withDefaults(
    defineProps<{
        valor: number;
        tamano?: number;
        grosor?: number;
        etiqueta?: string;
    }>(),
    { tamano: 148, grosor: 10 },
);

const { reducido } = useMovimientoReducido();

const destino = ref(0);
const animado = useTransition(destino, {
    duration: duracion.narrativa * 1000,
    transition: TransitionPresets.easeOutQuart,
});

const mostrado = computed(() => (reducido.value ? props.valor : animado.value));

const radio = computed(() => (props.tamano - props.grosor) / 2);
const circunferencia = computed(() => 2 * Math.PI * radio.value);
const recorrido = computed(() => circunferencia.value * (1 - Math.min(Math.max(mostrado.value, 0), 100) / 100));

onMounted(() => (destino.value = props.valor));
</script>

<template>
    <div class="relative shrink-0" :style="{ width: `${tamano}px`, height: `${tamano}px` }">
        <svg :width="tamano" :height="tamano" class="-rotate-90" aria-hidden="true">
            <circle
                :cx="tamano / 2"
                :cy="tamano / 2"
                :r="radio"
                fill="none"
                :stroke-width="grosor"
                class="stroke-muted"
            />
            <circle
                :cx="tamano / 2"
                :cy="tamano / 2"
                :r="radio"
                fill="none"
                :stroke-width="grosor"
                stroke-linecap="round"
                class="stroke-primary"
                :stroke-dasharray="circunferencia"
                :stroke-dashoffset="recorrido"
            />
        </svg>

        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="cifra text-3xl font-semibold tracking-tight">{{ Math.round(mostrado) }}%</span>
            <span v-if="etiqueta" class="mt-0.5 text-xs text-muted-foreground">{{ etiqueta }}</span>
        </div>
    </div>
</template>
