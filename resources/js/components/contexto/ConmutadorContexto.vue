<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { HistoryIcon, LayoutGridIcon, TableIcon } from '@lucide/vue';

/**
 * Las tres formas de mirar el mismo contexto: la matriz, la tabla y el historial.
 *
 * Calcado de `tarea/ConmutadorVista`, y por los mismos dos motivos. **El estado es
 * la URL y no `localStorage`**: un conmutador con memoria hace que el enlace que
 * alguien pega en un correo abra otra pantalla y que el sidebar lleve a sitios
 * distintos según el día. Y `aria-current="page"` en vez de `aria-pressed`, porque
 * son enlaces a tres páginas y no un grupo de interruptores.
 *
 * La `vista` llega por prop en lugar de deducirse del `pathname` como allí: aquí
 * las rutas anidan —`/contexto/cuestiones/7` sigue siendo la tabla— y comparar la
 * ruta exacta dejaría el conmutador sin ninguna opción marcada dentro de una ficha.
 */
defineProps<{ vista: 'matriz' | 'cuestiones' | 'analisis' }>();

const vistas = [
    { clave: 'matriz', href: '/contexto', etiqueta: 'Matriz', icono: LayoutGridIcon },
    { clave: 'cuestiones', href: '/contexto/cuestiones', etiqueta: 'Tabla', icono: TableIcon },
    { clave: 'analisis', href: '/contexto/analisis', etiqueta: 'Revisiones', icono: HistoryIcon },
] as const;
</script>

<template>
    <nav aria-label="Cómo ver el contexto" class="flex rounded-md border p-0.5">
        <Link
            v-for="opcion in vistas"
            :key="opcion.clave"
            :href="opcion.href"
            :aria-current="vista === opcion.clave ? 'page' : undefined"
            class="flex min-h-11 items-center gap-1.5 rounded-sm px-3 text-sm font-medium transition-colors sm:min-h-8"
            :class="
                vista === opcion.clave
                    ? 'bg-accent text-accent-foreground'
                    : 'text-muted-foreground hover:text-foreground'
            "
        >
            <component :is="opcion.icono" class="size-4 shrink-0" aria-hidden="true" />
            <span class="hidden sm:inline">{{ opcion.etiqueta }}</span>
            <span class="sr-only sm:hidden">{{ opcion.etiqueta }}</span>
        </Link>
    </nav>
</template>
