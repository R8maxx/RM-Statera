<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { useTransition, TransitionPresets } from '@vueuse/core';
import { computed, onMounted, ref } from 'vue';

/**
 * La huella SHA-256 de una entrega, escribiéndose.
 *
 * **Sólo en la versión que se acaba de emitir**, y una vez. El resto del
 * historial la enseña puesta, como siempre.
 *
 * Por qué merece la pena: la huella es la prueba de que el PDF que tiene el
 * auditor es el PDF que se firmó, y sin embargo son sesenta y cuatro caracteres
 * que nadie lee. Verla escribirse es lo que la convierte en un hecho —«esto se
 * acaba de calcular sobre el fichero que acabas de entregar»— en lugar de en una
 * cadena de adorno. Es el mismo argumento que ya justificaba el contador de
 * `AnilloProgreso`: una cifra que ya está puesta se da por leída.
 *
 * Emitir una versión ocurre unas pocas veces al año por documento, así que esto
 * cae en el tramo de DESIGN.md §10 que tiene presupuesto para que se recuerde.
 *
 * **Lo no revelado se pinta transparente, no se recorta.** Con una subcadena que
 * crece, el `break-all` recalcula el salto de línea en cada fotograma y el
 * bloque entero da tirones. Así el texto ocupa desde el principio lo que va a
 * ocupar.
 *
 * Y el lector de pantalla recibe la huella entera de golpe: sesenta y cuatro
 * caracteres anunciados uno a uno serían un castigo, no un detalle.
 */
const props = defineProps<{ huella: string; revelar: boolean }>();

const { reducido } = useMovimientoReducido();

const destino = ref(props.revelar && !reducido.value ? 0 : props.huella.length);

const revelados = useTransition(destino, {
    duration: 600,
    transition: TransitionPresets.easeOutQuart,
});

onMounted(() => (destino.value = props.huella.length));

const caracteres = computed(() =>
    props.huella.split('').map((caracter, indice) => ({
        caracter,
        indice,
        visible: indice < revelados.value,
    })),
);
</script>

<template>
    <code class="cifra min-w-0 flex-1 break-all rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground">
        <span class="sr-only">{{ huella }}</span>

        <span aria-hidden="true">
            <span
                v-for="letra in caracteres"
                :key="letra.indice"
                :class="letra.visible ? 'opacity-100' : 'opacity-0'"
            >{{ letra.caracter }}</span>
        </span>
    </code>
</template>
