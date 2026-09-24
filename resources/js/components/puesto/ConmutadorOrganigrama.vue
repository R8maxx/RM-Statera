<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ListTreeIcon, NetworkIcon, UsersIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Las tres formas de mirar el mismo organigrama.
 *
 * Calcado de `ConmutadorVista`, el del plan de acción, y por lo mismo: **el
 * estado es la URL y no `localStorage`**. Un conmutador que recuerda la última
 * vista hace que el enlace que alguien pega en un correo abra otra pantalla.
 *
 * **La lista va primera y es la vista por defecto**, y no por antigüedad: es la
 * única de las tres que se recorre entera con el teclado y que cabe en 375 px
 * sin arrastrar. Los dos diagramas son la alternativa, no el sustituto.
 */
const vistas = [
    { href: '/puestos/organigrama', etiqueta: 'Lista', icono: ListTreeIcon },
    { href: '/puestos/organigrama/grafo', etiqueta: 'Diagrama', icono: NetworkIcon },
    { href: '/puestos/organigrama/grafo-personas', etiqueta: 'Con personas', icono: UsersIcon },
] as const;

const pagina = usePage();

const actual = computed(() => new URL(pagina.url, 'http://localhost').pathname);
</script>

<template>
    <nav aria-label="Cómo ver el organigrama" class="flex rounded-md border p-0.5">
        <Link
            v-for="vista in vistas"
            :key="vista.href"
            :href="vista.href"
            :aria-current="actual === vista.href ? 'page' : undefined"
            class="flex min-h-11 items-center gap-1.5 rounded-sm px-3 text-sm font-medium transition-colors sm:min-h-8"
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
