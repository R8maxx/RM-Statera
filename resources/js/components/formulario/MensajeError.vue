<script setup lang="ts">
import { AlertCircleIcon } from '@lucide/vue';

/**
 * El error de un campo.
 *
 * Lleva `id` porque el control tiene que apuntarlo desde su `aria-describedby`:
 * sin eso, un lector de pantalla anuncia el campo como inválido pero no dice
 * por qué, que es la mitad del problema.
 */
defineProps<{ mensaje?: string | string[] | null; id?: string }>();
</script>

<template>
    <p v-if="mensaje" :id="id" class="flex items-start gap-1.5 text-sm font-medium text-destructive">
        <AlertCircleIcon class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
        <span>{{ Array.isArray(mensaje) ? mensaje.join(' ') : mensaje }}</span>
    </p>
</template>
