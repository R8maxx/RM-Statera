<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <tr
    data-slot="table-row"
    :class="cn('border-b transition-colors hover:bg-fila-hover has-[:focus-visible]:bg-fila-hover data-[state=selected]:bg-fila-seleccionada', props.class)"
  >
    <slot />
  </tr>
</template>
