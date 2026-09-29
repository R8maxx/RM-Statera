<script setup lang="ts">
/**
 * La balanza, y el único sitio donde vive su geometría.
 *
 * `DESIGN.md` §2 lo pide con todas las letras —«la fuente de verdad de la
 * geometría es `SimboloBalanza.vue`; cualquier redibujo sale de ahí»—, así que
 * el logotipo, la marca de agua de los estados vacíos, el favicon y la nube de
 * puntos del acceso dibujan literalmente esto.
 *
 * **La forma es la del logo definitivo** (`resources/statera-mark.svg`): el brazo, el
 * fiel y dos platillos triangulares, pasada de su lienzo de 512 a la retícula
 * de 32 dividiendo entre 16. El `viewBox` recorta a los 24 centrales: el
 * dibujo ocupa doce de alto, y con los 32 enteros se veía pequeño junto al
 * wordmark. **Los colores no son los suyos**: aquel va en
 * degradado de teal a violeta pasando por azul, y aquí todo es `currentColor`
 * y plano, que es lo que §2 exige y lo que permite imprimirlo a una tinta. El
 * degradado se queda para las portadas, donde §3 lo admite.
 */
defineProps<{
    /** Sin etiqueta el SVG es decorativo y se oculta al lector de pantalla. */
    etiqueta?: string | null;
}>();
</script>

<template>
    <svg
        viewBox="4 4 24 24"
        fill="currentColor"
        stroke="currentColor"
        stroke-linejoin="round"
        :role="etiqueta ? 'img' : undefined"
        :aria-label="etiqueta ?? undefined"
        :aria-hidden="etiqueta ? undefined : true"
    >
        <!-- Los dos platillos. El trazo sólo redondea las esquinas, como en el original. -->
        <path d="M6.1875 19.625 11.1875 12.4375 13.6875 19.625Z" stroke-width="0.5" />
        <path d="M18.5 19.625 20.875 12.4375 25.875 19.625Z" stroke-width="0.5" />
        <!-- El brazo y el fiel. -->
        <rect x="8.5" y="9.9375" width="15" height="1.1875" rx="0.1875" stroke="none" />
        <rect x="15.125" y="11.125" width="1.875" height="10.9375" rx="0.125" stroke="none" />
    </svg>
</template>
