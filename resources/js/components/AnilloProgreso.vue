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

/**
 * Y cuando llega a cien, remata.
 *
 * Una vez, sin bucle, y sólo en cien. Es la cifra que el producto entero existe
 * para mover: llegar al cien por cien de implantación es el trabajo de meses de
 * una organización, y que la pantalla lo trate igual que a un 62 % es dejar sin
 * decir lo único que había que decir. DESIGN.md §10 lo tiene enumerado como uno
 * de los cuatro momentos, y no hay un quinto sin pasar antes por ese documento.
 *
 * Espera a que el contador llegue: el pulso es la consecuencia de haber
 * llegado, no un aviso de que se va a llegar.
 */
const completo = computed(() => Math.round(mostrado.value) >= 100 && props.valor >= 100);

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

            <!--
                El remate del cien por cien: un anillo que se expande una vez y
                se apaga. Va por fuera del trazo y no lo toca, así que el arco
                no se deforma ni cambia de grosor.
            -->
            <circle
                v-if="completo && !reducido"
                :cx="tamano / 2"
                :cy="tamano / 2"
                :r="radio"
                fill="none"
                stroke-width="2"
                class="pulso-completo origin-center stroke-primary"
            />
        </svg>

        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="cifra text-3xl font-semibold tracking-tight">{{ Math.round(mostrado) }}%</span>
            <span v-if="etiqueta" class="mt-0.5 text-xs text-muted-foreground">{{ etiqueta }}</span>
        </div>
    </div>
</template>
