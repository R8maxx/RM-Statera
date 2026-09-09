<script setup lang="ts">
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
        <svg
            viewBox="0 0 32 32"
            class="size-7 shrink-0 text-primary"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            role="img"
            aria-label="Statera"
        >
            <!-- El fiel y la base. -->
            <path d="M16 6.5v19" />
            <path d="M11 26.5h10" />
            <!-- El brazo, con el punto de apoyo en el centro. -->
            <path d="M5 10.5h22" />
            <circle cx="16" cy="10.5" r="1.6" fill="currentColor" stroke="none" />
            <!-- Los dos platillos. Uno pesa algo más: una balanza en equilibrio
                 perfecto no está midiendo nada. -->
            <path d="M5 10.5 1.5 17.5a3.5 3.5 0 0 0 7 0Z" />
            <path d="M27 10.5 23.5 19a3.5 3.5 0 0 0 7 0Z" />
        </svg>

        <span v-if="variante === 'completo'" class="min-w-0 leading-tight">
            <span class="block text-[0.9375rem] font-semibold tracking-tight">Statera</span>
            <span v-if="respaldo" class="block text-[0.6875rem] text-muted-foreground">
                un producto de RM Technology
            </span>
        </span>
    </span>
</template>
