<script setup lang="ts">
import { partirPorTerminos } from '@/lib/filtros';
import { computed } from 'vue';

/**
 * Un texto con lo que coincide con lo buscado marcado encima.
 *
 * Con doscientos requisitos en pantalla y «copias de seguridad» en el filtro,
 * saber qué filas han pasado no basta: hay que ver por dónde. Es lo que hace
 * el buscador del navegador y aquí se espera lo mismo.
 *
 * Se compone con `v-for`, **nunca con `v-html`**: el texto es dato de la
 * organización y no vuelve al DOM como marcado.
 */
const props = defineProps<{ texto: string; terminos?: string[] }>();

const fragmentos = computed(() => partirPorTerminos(props.texto, props.terminos ?? []));
</script>

<template>
    <template v-for="(fragmento, indice) in fragmentos" :key="indice">
        <mark
            v-if="fragmento.coincide"
            class="rounded-sm bg-primary/20 px-px text-inherit"
        >{{ fragmento.texto }}</mark>
        <template v-else>{{ fragmento.texto }}</template>
    </template>
</template>
