<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from 'reka-ui'

import type { HTMLAttributes } from 'vue'
import { XIcon } from '@lucide/vue'
import { reactiveOmit } from '@vueuse/core'
import {
  DialogClose,
  DialogContent,
  DialogOverlay,
  DialogPortal,
  useForwardPropsEmits,
} from 'reka-ui'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'

defineOptions({
  inheritAttrs: false,
})

const props = defineProps<DialogContentProps & { class?: HTMLAttributes['class'] }>()
const emits = defineEmits<DialogContentEmits>()

const delegatedProps = reactiveOmit(props, 'class')

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <!--
    El mismo diálogo que `DialogContent`, pero con el velo como contenedor que
    se desplaza: para lo que es más alto que la ventana. Venía de la plantilla
    antigua de shadcn —`bg-black/80`, `rounded-lg`, `shadow-lg`, 200 ms— y era
    el único diálogo del producto que no se parecía a los otros.
  -->
  <DialogPortal>
    <DialogOverlay
      class="fixed inset-0 z-(--z-superposicion) grid place-items-center overflow-y-auto bg-black/10 px-4 supports-backdrop-filter:backdrop-blur-xs data-open:animate-in data-closed:animate-out data-closed:fade-out-0 data-open:fade-in-0 data-open:duration-[var(--duracion)] data-closed:duration-[var(--duracion-salida)] ease-marca"
    >
      <DialogContent
        :class="
          cn(
            'relative my-8 grid w-full max-w-lg gap-6 rounded-xl bg-popover p-6 text-sm text-popover-foreground shadow-sombra-3 ring-1 ring-foreground/10 outline-none data-open:animate-in data-closed:animate-out data-closed:fade-out-0 data-open:fade-in-0 data-closed:zoom-out-95 data-open:zoom-in-95 data-open:duration-[var(--duracion-lenta)] data-closed:duration-[var(--duracion-salida)] ease-marca',
            props.class,
          )
        "
        v-bind="{ ...$attrs, ...forwarded }"
        @pointer-down-outside="(event) => {
          const originalEvent = event.detail.originalEvent;
          const target = originalEvent.target as HTMLElement;
          if (originalEvent.offsetX > target.clientWidth || originalEvent.offsetY > target.clientHeight) {
            event.preventDefault();
          }
        }"
      >
        <slot />

        <DialogClose as-child>
          <Button variant="ghost" class="absolute top-4 right-4" size="icon-sm">
            <XIcon />
            <span class="sr-only">Cerrar</span>
          </Button>
        </DialogClose>
      </DialogContent>
    </DialogOverlay>
  </DialogPortal>
</template>
