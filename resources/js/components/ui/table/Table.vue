<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <!--
    La tabla estática del producto: para lo que no se pagina ni se filtra en
    servidor —los sistemas bajo el ENS, los tipos de plantilla—. Se pinta como
    `DataTable` (DESIGN.md §9): mismo envoltorio, misma cabecera en versalitas,
    mismo hover sin alfa. Lo que pagina o filtra va con `DataTable` y un
    `Recurso`; esto no fabrica un `MetaTabla` para fingirlo.
  -->
  <div
    data-slot="table-container"
    class="relative w-full overflow-x-auto rounded-xl bg-card shadow-xs ring-1 ring-foreground/10"
  >
    <table data-slot="table" :class="cn('w-full caption-bottom text-sm', props.class)">
      <slot />
    </table>
  </div>
</template>
