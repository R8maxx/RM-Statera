<script setup lang="ts">
import SimboloBalanza from '@/components/SimboloBalanza.vue';
/**
 * La marca de Statera.
 *
 * *Statera* es la balanza romana, y ese es el símbolo: el fiel vertical, el
 * brazo horizontal y los dos platillos que penden de él — dos marcos normativos
 * puestos en la misma balanza, que es literalmente lo que hace el producto.
 *
 * Geometría plana en `currentColor`, sin degradados: el sistema de RM
 * Technology (chip, Poppins, degradado morado) comunica marca de desarrollo, y
 * Statera le habla a auditores y a compradores del sector público, donde ese
 * degradado resta credibilidad. Identidad propia, no derivada.
 */
withDefaults(
    defineProps<{
        /** `simbolo` para espacios estrechos; `completo` añade el wordmark. */
        variante?: 'simbolo' | 'completo';
        /** Muestra el respaldo en letra pequeña. Sólo donde hay sitio. */
        respaldo?: boolean;
    }>(),
    { variante: 'completo', respaldo: false },
);
</script>

<template>
    <span class="flex items-center gap-2.5">
        <SimboloBalanza class="size-7 shrink-0 text-primary" etiqueta="Statera" />

        <span v-if="variante === 'completo'" class="min-w-0 leading-tight">
            <span class="block text-[0.9375rem] font-semibold tracking-tight">Statera</span>
            <span v-if="respaldo" class="block text-[0.6875rem] text-muted-foreground">
                un producto de RM Technology
            </span>
        </span>
    </span>
</template>
