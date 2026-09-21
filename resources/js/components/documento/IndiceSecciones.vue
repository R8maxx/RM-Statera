<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { cn } from '@/lib/utils';
import { motion } from 'motion-v';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * El índice lateral de un formulario largo por secciones.
 *
 * Hermano de `IndiceDocumento`, con la misma forma y otro origen: aquél deriva
 * sus entradas del documento de ProseMirror y éste las recibe, porque aquí las
 * secciones **son** una lista que el servidor ya manda. Una plantilla del ENS
 * tiene once huecos y sin esto la única forma de llegar al último era rodar.
 *
 * Localiza cada sección por `[data-campo]`, que es el mismo asidero que usa
 * `FormularioRecurso::irAlCampo()`: el elemento **enfocable** del campo, no su
 * `<input type="hidden">`. Y abre la sección antes de saltar, por lo mismo que
 * allí — dentro de una sección plegada no se puede enfocar ni desplazar nada.
 */
const props = defineProps<{
    entradas: { clave: string; etiqueta: string }[];
    /** Rótulo del bloque. */
    titulo?: string;
}>();

const activa = ref(0);
const { reducido } = useMovimientoReducido();

/**
 * El elemento de una sección.
 *
 * Por `data-seccion` y no por el `data-campo` del editor: ése lo pone un
 * componente que se carga con `defineAsyncComponent`, así que al montar la
 * página no existe todavía y el observador se montaba sobre cero elementos —el
 * índice marcaba lo que se pulsaba y no se movía solo, que es justo lo que se
 * quería—. El `<section>` sí está desde el primer fotograma.
 */
function seccionDe(clave: string): HTMLElement | null {
    return document.querySelector<HTMLElement>(`[data-seccion="${CSS.escape(clave)}"]`);
}

/** Lo que tapa la cabecera pegajosa, en píxeles. El mismo `top-20` del índice. */
const ALTO_CABECERA = 80;

function ir(clave: string, indice: number): void {
    const seccion = seccionDe(clave);

    if (seccion === null) {
        return;
    }

    activa.value = indice;

    const disparador = seccion.querySelector<HTMLButtonElement>('[data-plegar]');

    if (disparador?.getAttribute('aria-expanded') === 'false') {
        disparador.click();
    }

    // Al fotograma siguiente: recién abierta, la posición todavía es la vieja.
    requestAnimationFrame(() => {
        /*
         * **El foco va ANTES del desplazamiento, y no al revés.** Enfocar cancela
         * un desplazamiento suave en curso aunque lleve `preventScroll`, así que
         * con el orden contrario el índice marcaba la sección y la página se
         * quedaba donde estaba — y sobre novecientos píxeles el fallo parecía que
         * la pestaña se había colgado.
         *
         * Y se enfoca el título, no el editor: pulsar un índice es navegar, no
         * empezar a escribir. Entrar en la caja de texto levanta el teclado del
         * móvil y deja el formulario tocado sin que nadie haya escrito nada.
         */
        (disparador ?? seccion).focus({ preventScroll: true });

        /*
         * `scrollTo` y no `scrollIntoView`: éste no admite margen y la cabecera
         * pegajosa se comería el título de la sección a la que se acaba de saltar.
         */
        window.scrollTo({
            top: seccion.getBoundingClientRect().top + window.scrollY - ALTO_CABECERA,
            behavior: reducido.value ? 'auto' : 'smooth',
        });
    });
}

/*
 * Cuál está activa la decide el scroll, no el último clic: si la marca sólo se
 * moviera al pulsar, quien baja rodando vería el índice señalando una sección
 * que ya no está en pantalla, que es peor que no tener índice.
 *
 * `IntersectionObserver` y no un `scroll` con `getBoundingClientRect`: el
 * navegador lo calcula fuera del hilo principal y no hay nada que estrangular a
 * mano. Es API del DOM, no una dependencia — la misma regla por la que las
 * gráficas se pintan sin librería.
 */
let observador: IntersectionObserver | null = null;

onMounted(() => {
    const secciones = props.entradas
        .map((entrada, indice) => ({ indice, elemento: seccionDe(entrada.clave) }))
        .filter((par): par is { indice: number; elemento: HTMLElement } => par.elemento !== null);

    if (secciones.length === 0) {
        return;
    }

    observador = new IntersectionObserver(
        (entradas) => {
            const visibles = entradas
                .filter((entrada) => entrada.isIntersecting)
                .map((entrada) => secciones.find((par) => par.elemento === entrada.target)?.indice)
                .filter((indice): indice is number => indice !== undefined);

            if (visibles.length > 0) {
                activa.value = Math.min(...visibles);
            }
        },
        // La banda de arriba de la ventana: la sección «en la que estás» es la
        // que tienes bajo la cabecera, no la que ocupa más píxeles.
        { rootMargin: '-80px 0px -60% 0px', threshold: 0 },
    );

    for (const { elemento } of secciones) {
        observador.observe(elemento);
    }
});

onBeforeUnmount(() => observador?.disconnect());
</script>

<template>
    <nav :aria-label="titulo ?? 'Secciones'" class="hidden w-52 shrink-0 lg:block">
        <div class="sticky top-20">
            <p class="mb-2 px-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {{ titulo ?? 'Secciones' }}
            </p>
            <ol class="space-y-0.5">
                <li v-for="(entrada, indice) in entradas" :key="entrada.clave" class="relative">
                    <!-- La marca se desliza: ver de dónde a dónde va es lo que
                         explica por qué se ha movido. Igual que en el editor de
                         cuerpo, y apagada con movimiento reducido. -->
                    <motion.span
                        v-if="indice === activa"
                        :layout-id="reducido ? undefined : 'indice-secciones-activa'"
                        :transition="{ duration: reducido ? 0 : 0.28, ease: [0.77, 0, 0.175, 1] }"
                        class="absolute inset-y-1 left-0 w-0.5 rounded-full bg-primary"
                        aria-hidden="true"
                    />

                    <button
                        type="button"
                        :aria-current="indice === activa ? 'true' : undefined"
                        :class="
                            cn(
                                'w-full rounded-md px-2 py-1.5 text-left text-sm',
                                'transition-colors duration-[var(--duracion-rapida)] ease-marca',
                                'outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50',
                                indice === activa
                                    ? 'bg-accent font-medium text-accent-foreground'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            )
                        "
                        @click="ir(entrada.clave, indice)"
                    >
                        {{ entrada.etiqueta }}
                    </button>
                </li>
            </ol>
        </div>
    </nav>
</template>
