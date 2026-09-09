<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';

/**
 * La barra de acciones pegada al pie de la ventana.
 *
 * Los formularios de este dominio son largos —una valoración de cinco
 * dimensiones con su justificación no cabe en una pantalla— y bajar hasta el
 * final para guardar es trabajo que no aporta nada.
 *
 * Vive aparte de `FormularioRecurso` porque no todas las pantallas de este tipo
 * son un `<Form>`: la valoración necesita estado controlado para derivar la
 * categoría en vivo y para previsualizar el recálculo antes de aplicarlo, pero
 * su pie tiene que ser el mismo pie.
 */
defineProps<{ urlCancelar: string; etiquetaCancelar?: string }>();
</script>

<template>
    <div
        class="fixed inset-x-0 bottom-0 z-20 border-t bg-background/90 px-4 py-3 backdrop-blur-sm sm:px-6 lg:px-8"
    >
        <div class="mx-auto flex max-w-4xl items-center gap-2">
            <div class="min-w-0 flex-1 text-sm text-muted-foreground">
                <slot name="nota" />
            </div>

            <Link :href="urlCancelar">
                <Button type="button" variant="ghost">{{ etiquetaCancelar ?? 'Cancelar' }}</Button>
            </Link>

            <slot />
        </div>
    </div>
</template>
