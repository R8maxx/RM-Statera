<script setup lang="ts">
/**
 * El chip junto a la etiqueta que dice de dónde salió un valor: «NVD» mientras
 * sea el traído, «editado» en neutro cuando alguien lo cambió. Lo pintan
 * `CampoBase` y `CampoLista`, que tienen etiquetas distintas (`label` y
 * `legend`) y el mismo chip.
 *
 * El teal es `accent`, el del ítem activo: señala sin parecer un estado. Y el
 * texto para lector de pantalla completa lo que el chip abrevia.
 */
defineProps<{ fuente: string; editado?: boolean }>();
</script>

<template>
    <span
        v-if="editado"
        class="inline-flex h-[18px] items-center rounded-full bg-muted px-1.5 text-xs font-medium text-muted-foreground"
    >
        editado<span class="sr-only">&nbsp;después de traerlo de {{ fuente }}</span>
    </span>
    <span
        v-else
        class="inline-flex h-[18px] items-center rounded-full bg-accent px-1.5 text-xs font-medium text-accent-foreground"
    >
        <span class="sr-only">rellenado desde&nbsp;</span>{{ fuente }}
    </span>
</template>
