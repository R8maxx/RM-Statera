<script setup lang="ts">
import { useDesplegable } from '@/composables/useDesplegable';
import { ChevronRightIcon } from '@lucide/vue';
import { computed, useId } from 'vue';

/**
 * Un grupo del sidebar, plegable desde su título.
 *
 * Con veintiséis módulos el sidebar ya no cabía en una pantalla de portátil, y
 * quien trabaja en dos o tres módulos tenía que desplazarse por los otros
 * veintitrés cada vez. El título es el mando, como en `SeccionFormulario`: es
 * donde se mira y donde se pulsa.
 *
 * **El grupo de la pantalla activa no se pliega nunca**: plegado, la entrada
 * que dice dónde estás desaparecería justo cuando más falta hace. Por eso
 * `abierto` lo decide quien lo usa, que sabe cuál es la ruta.
 *
 * Con el sidebar plegado a iconos no hay título que pulsar y el grupo se
 * enseña siempre: sólo quedan los iconos, y esconder alguno sería perderlo.
 */
const props = defineProps<{
    titulo: string;
    abierto: boolean;
    /** El sidebar entero plegado a iconos. */
    compacto: boolean;
}>();

const emit = defineEmits<{ alternar: [] }>();

const id = useId();
const visible = computed(() => props.compacto || props.abierto);
const { asentada, alTerminarTransicion } = useDesplegable(visible);
</script>

<template>
    <div class="space-y-1">
        <button
            v-if="!compacto"
            type="button"
            class="group flex w-full items-center gap-1 rounded-md px-3 pb-1 text-left text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
            :aria-expanded="abierto"
            :aria-controls="id"
            @click="emit('alternar')"
        >
            <span class="flex-1">{{ titulo }}</span>
            <ChevronRightIcon
                class="size-3.5 shrink-0 transition-[opacity,transform]"
                :class="
                    abierto
                        ? 'rotate-90 opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100'
                        : 'opacity-100'
                "
                aria-hidden="true"
            />
        </button>

        <div
            :id="id"
            class="desplegable"
            :data-abierto="visible ? '' : undefined"
            :data-asentado="asentada ? '' : undefined"
            :inert="!visible"
            @transitionend="alTerminarTransicion"
        >
            <div class="space-y-1">
                <slot />
            </div>
        </div>
    </div>
</template>
