<script setup lang="ts">
import IconoTipo from '@/components/IconoTipo.vue';
import { Button } from '@/components/ui/button';
import { tono } from '@/lib/tonos';
import { computed } from 'vue';

/**
 * Un botón que lleva a un estado, pintado **como el estado al que lleva**.
 *
 * La idea en una frase: pulsa el botón que se parece al badge que quieres. Antes
 * los cuatro destinos eran cuatro rectángulos grises idénticos y había que
 * leerlos uno a uno para saber cuál pulsar.
 *
 * **Tinte suave, nunca relleno.** Es la misma intensidad que un badge y la misma
 * que ya usa la variante `destructive`, así que no compite con el primario de la
 * pantalla: DESIGN.md §9 pide un solo primario por vista, y dos botones de color
 * lleno a la vez hacen que no mande ninguno.
 *
 * **Y lleva icono, que no es adorno.** El verde de «Hecha» y el ámbar de «En
 * curso» quedan cerca con protanopía; sin icono serían el mismo botón. Es la
 * misma razón por la que el badge dejó de conformarse con un punto.
 */
const props = defineProps<{
    destino: { valor: string; etiqueta: string; tono: string; icono: string };
    deshabilitado?: boolean;
}>();

const estilo = computed(() => tono(props.destino.tono));
</script>

<template>
    <Button
        variant="ghost"
        size="sm"
        :disabled="deshabilitado"
        :class="estilo.badge"
        :aria-label="`Mover a ${destino.etiqueta}`"
    >
        <IconoTipo :nombre="destino.icono" clase="size-4" />
        {{ destino.etiqueta }}
    </Button>
</template>
