<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type Vista = App.Http.Resources.Panel.VistaPanel;

/**
 * Las tres formas de mirar el mismo panel.
 *
 * Calcado de `ConmutadorVista`, el del plan de acción, y por lo mismo: **el
 * estado es la URL y no `localStorage`**. Un conmutador que recuerda la última
 * vista elegida hace que el enlace que alguien pega en un correo abra una
 * pantalla distinta de la que estaba mirando, y que el sidebar lleve a sitios
 * distintos según el día.
 *
 * **El punto es lo que sujeta el reparto.** Partir el panel en tres tiene un
 * riesgo y es sólo uno: que una pestaña esconda un incumplimiento detrás de un
 * clic que nadie da. El punto dice dónde mirar; el detalle está dentro, en la
 * tarjeta del módulo que lo produce, con su enlace a la lista exacta.
 *
 * Se probó a sacar además todos los rojos a una tira encima de las pestañas, y
 * se quitó: con el registro de ejemplo salían **doce** tarjetas rojas, que es
 * la misma fila de cifras que hay que leerse entera y que el panel ya tenía —el
 * problema que este rediseño existe para quitar—. El punto dice lo mismo en un
 * píxel.
 *
 * `aria-current="page"` y no `aria-pressed`: son enlaces a tres páginas, no un
 * grupo de interruptores. El recuento va en el nombre accesible, porque un punto
 * de color no lo anuncia ningún lector de pantalla.
 */
defineProps<{ vistas: Vista[] }>();

const pagina = usePage();

/* La ruta sin query: `/panel/ciclo?lo-que-sea` sigue siendo el ciclo. */
const actual = computed(() => new URL(pagina.url, 'http://localhost').pathname);
</script>

<template>
    <nav aria-label="Qué parte del panel mirar" class="flex overflow-x-auto rounded-md border p-0.5">
        <Link
            v-for="vista in vistas"
            :key="vista.clave"
            :href="vista.href"
            :title="vista.pregunta"
            :aria-current="actual === vista.href ? 'page' : undefined"
            class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-sm px-3 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:min-h-8 sm:flex-none sm:justify-start"
            :class="
                actual === vista.href
                    ? 'bg-accent text-accent-foreground'
                    : 'text-muted-foreground hover:text-foreground'
            "
        >
            {{ vista.etiqueta }}
            <span
                v-if="vista.alertas > 0"
                class="size-1.5 shrink-0 rounded-full bg-destructive"
                aria-hidden="true"
            />
            <span v-if="vista.alertas > 0" class="sr-only">
                ({{ vista.alertas }} {{ vista.alertas === 1 ? 'aviso' : 'avisos' }})
            </span>
        </Link>
    </nav>
</template>
