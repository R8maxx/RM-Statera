<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Pestana {
    etiqueta: string;
    href: string;
}

/**
 * BIA / Pruebas: los dos listados de «Continuidad».
 *
 * El sidebar sólo lleva una entrada, «Continuidad», con `href="/continuidad"`
 * —`esSeccionActiva()` usa `startsWith`, así que una entrada hermana
 * «Pruebas de continuidad» se encendería a la vez que ella en cualquiera de
 * las dos pantallas—. Éstas son las pestañas que reparten lo que el sidebar
 * ya no puede: sobre `ConmutadorPanel.vue`, mismo criterio de fondo —**el
 * estado es la URL y no `localStorage`**, para que el enlace que alguien pega
 * en un correo abra la pestaña que estaba mirando y no la última elegida—.
 *
 * Sin punto de alerta, a diferencia de `ConmutadorPanel`: aquí no hay un rojo
 * que repartir entre dos vistas del mismo dato, son dos registros distintos.
 */
const pestanas: Pestana[] = [
    { etiqueta: 'BIA', href: '/continuidad/bia' },
    { etiqueta: 'Pruebas', href: '/continuidad/pruebas' },
];

const pagina = usePage();

const actual = computed(() => new URL(pagina.url, 'http://localhost').pathname);
</script>

<template>
    <nav aria-label="La sección de continuidad a mostrar" class="flex overflow-x-auto rounded-md border p-0.5">
        <Link
            v-for="pestana in pestanas"
            :key="pestana.href"
            :href="pestana.href"
            :aria-current="actual.startsWith(pestana.href) ? 'page' : undefined"
            class="flex min-h-11 flex-1 items-center justify-center rounded-sm px-4 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:min-h-8 sm:flex-none"
            :class="
                actual.startsWith(pestana.href)
                    ? 'bg-accent text-accent-foreground'
                    : 'text-muted-foreground hover:text-foreground'
            "
        >
            {{ pestana.etiqueta }}
        </Link>
    </nav>
</template>
