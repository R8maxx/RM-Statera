<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDaysIcon, ColumnsIcon, TableIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Las tres formas de mirar el mismo plan.
 *
 * **El estado es la URL y no `localStorage`.** Un conmutador que recuerda la
 * última vista elegida hace que el enlace que alguien pega en un correo abra una
 * pantalla distinta de la que estaba mirando, y que el sidebar lleve a sitios
 * distintos según el día. Aquí cada vista es una ruta.
 *
 * `aria-current="page"` y no un `aria-pressed`: son enlaces a tres páginas, no
 * un grupo de interruptores. Y 44 px de alto en móvil, que es el mínimo de
 * DESIGN.md § 11 para un objetivo táctil.
 */
const vistas = [
    { href: '/tareas', etiqueta: 'Tabla', icono: TableIcon },
    { href: '/tareas/tablero', etiqueta: 'Tablero', icono: ColumnsIcon },
    { href: '/tareas/calendario', etiqueta: 'Calendario', icono: CalendarDaysIcon },
] as const;

const pagina = usePage();

/* La ruta sin query: `/tareas/calendario?mes=2026-09` sigue siendo el calendario. */
const actual = computed(() => new URL(pagina.url, 'http://localhost').pathname);
</script>

<template>
    <nav aria-label="Cómo ver el plan" class="flex rounded-md border p-0.5">
        <Link
            v-for="vista in vistas"
            :key="vista.href"
            :href="vista.href"
            :aria-current="actual === vista.href ? 'page' : undefined"
            class="flex min-h-11 items-center gap-1.5 rounded-sm px-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:min-h-8"
            :class="
                actual === vista.href
                    ? 'bg-accent text-accent-foreground'
                    : 'text-muted-foreground hover:text-foreground'
            "
        >
            <component :is="vista.icono" class="size-4 shrink-0" aria-hidden="true" />
            <span class="hidden sm:inline">{{ vista.etiqueta }}</span>
            <span class="sr-only sm:hidden">{{ vista.etiqueta }}</span>
        </Link>
    </nav>
</template>
