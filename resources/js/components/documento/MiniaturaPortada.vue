<script setup lang="ts">
import SimboloBalanza from '@/components/SimboloBalanza.vue';

/**
 * La portada del borrador, en pequeño y en A4.
 *
 * Es forma, no contenido: el sello, un título, las líneas del cuerpo y el hueco
 * de la firma. Sirve para que el borrador se lea como **un documento** y no
 * como una fila con un badge, y para que se vea de un vistazo si la firma ya
 * está o falta. Sin texto dentro: a este tamaño quedaría por debajo de los
 * 12 px que DESIGN.md §4 pone de suelo.
 *
 * Decorativa para el lector de pantalla: lo que dice ya lo dicen el badge y los
 * datos de al lado.
 */
defineProps<{ firmada: boolean; enRevision: boolean }>();
</script>

<template>
    <div
        aria-hidden="true"
        class="flex aspect-[210/297] w-36 shrink-0 flex-col gap-2 rounded-sm border bg-card p-4 shadow-sombra-2 sm:w-44"
    >
        <div class="flex items-center gap-1.5">
            <SimboloBalanza class="size-3.5 text-primary" />
            <div class="h-1 w-10 rounded-full bg-border" />
        </div>

        <div class="mt-6 flex flex-col gap-1.5">
            <div class="h-2 w-4/5 rounded-full bg-secondary-foreground/70" />
            <div class="h-2 w-3/5 rounded-full bg-secondary-foreground/70" />
            <div class="mt-1 h-1 w-1/3 rounded-full bg-muted-foreground/40" />
        </div>

        <div class="mt-auto flex flex-col gap-1.5">
            <div class="h-1 w-full rounded-full bg-muted" />
            <div class="h-1 w-[88%] rounded-full bg-muted" />
            <div class="h-1 w-[94%] rounded-full bg-muted" />
            <div class="h-1 w-3/5 rounded-full bg-muted" />
        </div>

        <!-- El hueco de la firma: punteado mientras falta, lleno cuando está. -->
        <div
            class="h-5 rounded-[3px]"
            :class="
                firmada
                    ? 'bg-estado-implantado-suave'
                    : enRevision
                      ? 'border border-dashed border-acento-borde bg-acento-suave'
                      : 'border border-dashed border-border'
            "
        />
    </div>
</template>
