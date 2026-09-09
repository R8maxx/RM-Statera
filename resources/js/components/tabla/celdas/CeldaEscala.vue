<script setup lang="ts">
import { computed } from 'vue';

type ValorEscala = App.Http.Resources.Definicion.ValorEscala;

/**
 * Un nivel dentro de una progresión conocida: hoy la madurez L0–L5 del CCN.
 *
 * No es un badge y no debería serlo. Un badge dice «L4», pero lo que se busca
 * al recorrer la columna es si L4 es más que L2, y eso lo contesta la longitud
 * de la barra antes que el texto. El texto sigue estando —DESIGN.md §11: un
 * estado nunca se lee sólo por el color— y la etiqueta completa va en el
 * `title`, que es donde cabe.
 */
const props = defineProps<{ valor: ValorEscala | null }>();

const pasos = computed(() => (props.valor === null ? [] : Array.from({ length: props.valor.de }, (_, i) => i + 1)));
</script>

<template>
    <div v-if="valor" class="flex items-center gap-2" :title="valor.etiqueta">
        <span class="flex shrink-0 gap-0.5" role="img" :aria-label="valor.etiqueta">
            <span
                v-for="paso in pasos"
                :key="paso"
                class="h-3 w-1 rounded-[1px] transition-colors"
                :class="paso <= valor.valor ? 'bg-primary' : 'bg-muted'"
            />
        </span>
        <span class="cifra text-xs text-muted-foreground">
            {{ valor.corta ?? `${valor.valor}/${valor.de}` }}
        </span>
    </div>
    <span v-else class="text-muted-foreground" aria-label="sin valor">—</span>
</template>
