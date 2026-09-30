<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import type { EntradaIndice } from '@/composables/useIndiceFormulario';
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * El índice de secciones del carril de `FormularioRecurso`.
 *
 * **El número es lo que falta, y sólo cuando falta.** Una sección completa no
 * lleva palomita: es la misma regla que el «2 sin rellenar» de su cabecera
 * (DESIGN.md §9), y una columna de palomitas es la fila de ceros otra vez.
 *
 * **Pulsar abre la sección antes de saltar**: dentro de un pliegue a altura
 * cero no se puede desplazar ni enfocar nada, que es la trampa que ya resolvía
 * `irAlCampo`. Y el foco va a la sección, no se queda en el índice: quien
 * navega con teclado sigue tabulando desde donde ha saltado.
 *
 * **La sección que se está leyendo se marca** con el mismo fondo que el ítem
 * activo del sidebar, sin su barra: la barra es de la navegación.
 */
const props = defineProps<{ secciones: EntradaIndice[] }>();

const { reducido } = useMovimientoReducido();
const actual = ref<string | null>(null);

/*
 * Cuenta como «la que se lee» la última cuyo título ya ha pasado por encima
 * del 40 % de la ventana, o la última si ya no queda por donde bajar. Se probó con un `IntersectionObserver` y «la primera
 * visible en una franja»: una sección larga seguía cruzando la franja con la
 * siguiente ya a media pantalla, y el índice no se movía.
 */
let pendiente = false;

function medir(): void {
    pendiente = false;
    const umbral = window.innerHeight * 0.4;
    let ultima: string | null = props.secciones[0]?.id ?? null;

    for (const seccion of props.secciones) {
        const arriba = seccion.elemento.value?.getBoundingClientRect().top;

        if (arriba !== undefined && arriba <= umbral) {
            ultima = seccion.id;
        }
    }

    /* Al final de la página la última sección corta ya no puede subir más. */
    const alFinal = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;

    actual.value = alFinal ? (props.secciones.at(-1)?.id ?? ultima) : ultima;
}

function alDesplazar(): void {
    if (!pendiente) {
        pendiente = true;
        requestAnimationFrame(medir);
    }
}

onMounted(() => {
    window.addEventListener('scroll', alDesplazar, { passive: true });
    medir();
});

onUnmounted(() => window.removeEventListener('scroll', alDesplazar));

function ir(seccion: EntradaIndice): void {
    seccion.abrir();

    requestAnimationFrame(() => {
        seccion.elemento.value?.scrollIntoView({ block: 'start', behavior: reducido.value ? 'auto' : 'smooth' });
        seccion.elemento.value?.focus({ preventScroll: true });
    });
}
</script>

<template>
    <nav aria-label="Secciones del formulario" class="grid gap-0.5">
        <p class="px-2.5 pb-1.5 text-xs text-muted-foreground">En esta pantalla</p>

        <a
            v-for="seccion in secciones"
            :key="seccion.id"
            :href="`#${seccion.id}`"
            class="flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm transition-colors hover:bg-muted"
            :class="actual === seccion.id ? 'bg-accent font-medium text-accent-foreground' : 'text-secondary-foreground'"
            :aria-current="actual === seccion.id ? 'location' : undefined"
            @click.prevent="ir(seccion)"
        >
            <span class="min-w-0 flex-1 truncate">{{ seccion.titulo }}</span>
            <span
                v-if="seccion.pendientes.value > 0"
                class="cifra shrink-0 rounded-full bg-muted px-1.5 text-xs text-secondary-foreground"
            >
                {{ seccion.pendientes.value }}
                <span class="sr-only">sin rellenar</span>
            </span>
        </a>
    </nav>
</template>
