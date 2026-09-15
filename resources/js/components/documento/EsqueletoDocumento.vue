<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Lo que se enseña mientras Gotenberg construye el PDF.
 *
 * DESIGN.md §9 pide «esqueletos con la forma del contenido real y pulso, no
 * ruedas girando», y ésta es la única espera del producto lo bastante larga
 * —decenas de segundos— como para que una rueda se note vacía. Hasta ahora sólo
 * giraba el icono del botón, que dice que algo pasa pero no qué.
 *
 * La forma no es genérica: es **la portada de un documento de cumplimiento**
 * —título, subtítulo, una tabla de requisitos, un pie—, que es exactamente lo
 * que va a salir. Un esqueleto que anticipa la forma equivocada es peor que no
 * tenerlo, porque promete otra cosa.
 *
 * No cuenta como un tercer bucle del chrome de trabajo (DESIGN.md §10): existe
 * mientras el trabajo existe y se va con él, igual que el hilo de carga de la
 * tabla. El pulso lo pone `ui/skeleton`, y el `@media` global de `app.css` lo
 * para con movimiento reducido.
 */
defineProps<{ filas?: number }>();
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-xl border border-dashed p-4"
        role="status"
        aria-live="polite"
    >
        <!-- Portada: título y subtítulo. -->
        <Skeleton class="h-5 w-2/5" />
        <Skeleton class="h-3 w-1/4" />

        <div class="mt-2 h-px bg-border" />

        <!-- La tabla de requisitos, que es el cuerpo de las dos declaraciones. -->
        <div v-for="fila in filas ?? 4" :key="fila" class="flex items-center gap-3">
            <Skeleton class="h-3 w-16 shrink-0" />
            <Skeleton class="h-3 flex-1" />
            <Skeleton class="h-3 w-14 shrink-0" />
        </div>

        <div class="mt-2 h-px bg-border" />

        <!-- El pie, con la huella. -->
        <Skeleton class="h-2.5 w-1/3" />

        <span class="sr-only">Generando el documento. Puede tardar un minuto.</span>
    </div>
</template>
