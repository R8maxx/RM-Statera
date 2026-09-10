<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { curva, duracion } from '@/lib/motion';
import NumberFlow from '@number-flow/vue';
import { onMounted, ref, watch } from 'vue';

/**
 * Una cifra que llega contando hasta su valor.
 *
 * **Por qué existe y por qué no es decoración.** El panel se abre para leer
 * cifras, y una cifra que ya está puesta se da por leída: la vista pasa por
 * encima. Verla llegar obliga a mirarla. Es el mismo argumento con el que
 * `AnilloProgreso` ya animaba el porcentaje de implantación, generalizado al
 * resto de números del resumen.
 *
 * **Dónde NO se usa: en las celdas de una tabla.** Trescientas cifras contando
 * a la vez cada vez que alguien filtra no es énfasis, es ruido, y contradice la
 * regla de que las recargas parciales no se animan (`lib/motion.ts`). Esto es
 * para los números que resumen, no para los que se listan.
 *
 * Las duraciones y la curva salen de `lib/motion.ts`, no de la librería: si
 * mañana cambia el ritmo del producto, cambia aquí también.
 */
const props = withDefaults(
    defineProps<{
        valor: number;
        /** Cifras decimales, para las medias. Entero por defecto. */
        decimales?: number;
        prefijo?: string;
        sufijo?: string;
    }>(),
    { decimales: 0 },
);

const { reducido } = useMovimientoReducido();

/**
 * NumberFlow anima al CAMBIAR el valor, no al montarse. Para que la cifra
 * cuente al entrar hay que arrancarla en cero y darle el valor real después del
 * primer pintado — el mismo truco que usa `AnilloProgreso`.
 *
 * Sólo se arranca en cero cuando de verdad se va a poder contar. Con movimiento
 * reducido, o en una pestaña que nadie está mirando, la cifra nace ya puesta:
 * `requestAnimationFrame` no corre en segundo plano, y arrancar en cero ahí
 * dejaría el panel enseñando ceros hasta que alguien le diera el foco.
 */
const cuenta = !reducido.value && document.visibilityState === 'visible';

const mostrado = ref(cuenta ? 0 : props.valor);

/*
 * El salto va en el siguiente fotograma y no en el mismo `onMounted`: dentro
 * del mismo, Vue puede aplicar los dos valores en el mismo parcheo y NumberFlow
 * lo lee como render inicial, no como cambio, y no anima nada.
 *
 * Y `requestAnimationFrame` en vez de un temporizador porque encaja con el
 * pintado del navegador.
 */
onMounted(() => {
    if (!cuenta) {
        return;
    }

    requestAnimationFrame(() => (mostrado.value = props.valor));
});

/*
 * Y si el valor cambia después —una recarga parcial tras filtrar, un dato que
 * se corrige— la cifra tiene que seguirlo. Sin esto se quedaba con el número
 * del primer montaje y el panel mentía en silencio, que es peor que no animar.
 */
watch(
    () => props.valor,
    (nuevo) => (mostrado.value = nuevo),
);

const ritmo = {
    /** El dígito que sube o baja. Es el movimiento que se ve. */
    transformTiming: {
        duration: duracion.narrativa * 1000,
        easing: `cubic-bezier(${curva.join(',')})`,
    },
    /** El desvanecido de los dígitos que entran y salen, más corto. */
    opacityTiming: { duration: duracion.lenta * 1000, easing: 'ease-out' },
} as const;
</script>

<template>
    <!--
        `respect-motion-preference` es la tercera capa de lo mismo: ya lo cortan
        el `@media` global de `app.css` y el `reducido` de arriba, pero la
        librería trae su propia comprobación y apagarla sería quedarse con dos
        de tres.
    -->
    <NumberFlow
        :value="mostrado"
        :animated="!reducido"
        respect-motion-preference
        :transform-timing="ritmo.transformTiming"
        :opacity-timing="ritmo.opacityTiming"
        :format="{ minimumFractionDigits: decimales, maximumFractionDigits: decimales }"
        locales="es-ES"
        :prefix="prefijo"
        :suffix="sufijo"
        class="cifra tabular-nums"
    />
</template>
