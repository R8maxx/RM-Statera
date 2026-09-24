<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

/**
 * El título de una tarjeta es **un encabezado de verdad**, no un `<div>`.
 *
 * Era un `div`, y la consecuencia era que toda ficha del producto tenía un
 * `<h1>` —el de `CabeceraPagina`— y ningún `<h2>`: una pantalla con ocho
 * tarjetas no se podía recorrer por encabezados, que es justo como navega quien
 * usa un lector de pantalla. `DESIGN.md` §11 pide jerarquía sin saltos, y no
 * hay jerarquía con un solo nivel.
 *
 * El cambio es **visualmente nulo**: el preflight de Tailwind ya deja los
 * encabezados sin margen y con el tamaño heredado, y las clases de aquí ponen
 * el resto.
 *
 * `as` existe para los casos en los que `h2` no es el nivel que toca —una
 * tarjeta dentro de otra, o una que vive bajo un encabezado de sección—.
 */
const props = withDefaults(defineProps<{
  class?: HTMLAttributes['class']
  as?: string
}>(), {
  as: 'h2',
})
</script>

<template>
  <component
    :is="props.as"
    data-slot="card-title"
    :class="cn('text-base leading-6 font-semibold tracking-[-0.01em] group-data-[size=sm]/card:text-sm cn-font-heading', props.class)"
  >
    <slot />
  </component>
</template>
