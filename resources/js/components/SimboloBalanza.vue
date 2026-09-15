<script setup lang="ts">
/**
 * La balanza romana, y el único sitio donde vive su geometría.
 *
 * `DESIGN.md` §2 lo pide con todas las letras —«la fuente de verdad de la
 * geometría es `Logotipo.vue`; cualquier redibujo sale de ahí»—, así que cuando
 * hizo falta una segunda copia para la marca de agua, lo que se hizo no fue
 * copiarla: se sacó aquí y ahora el logotipo y la marca de agua dibujan
 * literalmente lo mismo. Si alguien redibuja el símbolo, se redibuja en los dos
 * a la vez.
 *
 * Sigue todo en `currentColor` y sin degradado, que es lo que separa esta marca
 * del sistema de RM Technology.
 */
withDefaults(
    defineProps<{
        /** Sin etiqueta el SVG es decorativo y se oculta al lector de pantalla. */
        etiqueta?: string | null;
        /** El trazo va sobre retícula de 32: 2 en el logotipo, más fino al ampliar. */
        trazo?: number;
    }>(),
    { etiqueta: null, trazo: 2 },
);
</script>

<template>
    <svg
        viewBox="0 0 32 32"
        fill="none"
        stroke="currentColor"
        :stroke-width="trazo"
        stroke-linecap="round"
        stroke-linejoin="round"
        :role="etiqueta ? 'img' : undefined"
        :aria-label="etiqueta ?? undefined"
        :aria-hidden="etiqueta ? undefined : true"
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
</template>
