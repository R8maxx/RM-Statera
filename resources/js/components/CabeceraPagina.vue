<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { useRecorrido } from '@/composables/useRecorrido';
import { duracion, curva } from '@/lib/motion';
import type { ClaveRecorrido } from '@/lib/recorridos';
import { CircleHelpIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { onMounted } from 'vue';

/**
 * Título, descripción y acciones de una pantalla.
 *
 * Existe para que los módulos que vienen no repitan cada uno su propia versión
 * del mismo bloque, que es exactamente lo que hacían las dos pantallas que
 * había: un `<p class="mb-4 max-w-3xl text-sm text-muted-foreground">` copiado.
 *
 * Es además el sitio donde el violeta de acento aparece en el producto: un
 * filete por pantalla, siempre en el mismo punto. Repartirlo por tarjetas y
 * badges lo convertiría en un segundo color de marca, que es exactamente lo
 * que DESIGN.md §3 pide no hacer.
 *
 * **Y el filete se traza.** Es la firma visual más barata que tiene el producto:
 * una sola propiedad (`scaleX`), sobre un elemento que ya estaba, y ocurre una
 * vez por navegación —no por sección al hacer scroll, ni por tarjeta—. Cuarenta
 * píxeles dibujándose de izquierda a derecha es donde arranca la lectura de la
 * pantalla, que es justo el trabajo que el filete ya hacía quieto.
 */
const props = withDefaults(
    defineProps<{
        titulo: string;
        descripcion?: string | null;
        /**
         * El identificador de lo que enseña la ficha —`R-001`, `op.acc.4`—,
         * pintado como el chip neutro y monoespaciado de DESIGN.md §9.
         *
         * Existe porque cada ficha lo ponía en un sitio: seis de título, cuatro
         * de descripción y el resto en ninguna parte. La regla es una: **si lo
         * que se enseña tiene nombre, el nombre es el título y el código va
         * aquí**; si no lo tiene —una no conformidad, una revisión—, el código
         * es el título y esto no se pasa.
         */
        codigo?: string | null;
        /**
         * El filete de acento sobre el título. Se apaga en la pantalla que ya
         * lleve uno: la regla de DESIGN.md §6 es uno por bloque y nunca dos en
         * la misma pantalla.
         */
        filete?: boolean;
        /**
         * El recorrido de esta pantalla (`lib/recorridos.ts`). Arranca solo la
         * primera vez que se entra y el «?» lo repite. Va aquí y no en cada
         * página porque la cabecera es lo único que todas las pantallas
         * comparten, y el botón tiene que estar siempre en el mismo sitio.
         */
        recorrido?: ClaveRecorrido;
    }>(),
    { filete: true, recorrido: undefined },
);

const { reducido } = useMovimientoReducido();
const { abrir, arrancarSiEsLaPrimeraVez } = useRecorrido();

onMounted(() => {
    if (props.recorrido) {
        arrancarSiEsLaPrimeraVez(props.recorrido);
    }
});
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <!-- 3 px por 40, en violeta. El gesto distintivo de la marca. -->
            <motion.span
                v-if="filete"
                class="mb-3 block h-[3px] w-10 origin-left bg-acento"
                :initial="reducido ? { scaleX: 1 } : { scaleX: 0 }"
                :animate="{ scaleX: 1 }"
                :transition="{ duration: reducido ? 0 : duracion.lenta, ease: curva }"
                aria-hidden="true"
            />

            <div class="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
                <span
                    v-if="codigo"
                    class="cifra shrink-0 rounded-md bg-muted px-1.5 py-0.5 text-xs text-muted-foreground"
                >
                    {{ codigo }}
                </span>
                <h1 class="text-xl leading-7 font-semibold tracking-[-0.015em] text-balance">{{ titulo }}</h1>
            </div>
            <p v-if="descripcion" class="mt-1 max-w-2xl text-sm text-pretty text-muted-foreground">{{ descripcion }}</p>

            <!-- Para lo que matiza al título sin ser acción: la leyenda de
                 campos obligatorios de un formulario, por ejemplo. -->
            <slot />
        </div>

        <!-- Se parte en varias líneas por debajo de `sm`: con tres botones, a
             375 px el hueco no cabía y empujaba la página en horizontal. -->
        <div v-if="$slots.acciones || recorrido" class="flex flex-wrap items-center gap-2 sm:shrink-0">
            <div v-if="$slots.acciones" class="flex flex-wrap items-center gap-2" data-recorrido="cabecera-acciones">
                <slot name="acciones" />
            </div>
            <!-- El último, y discreto: es ayuda, no una acción de la pantalla. -->
            <Tooltip v-if="recorrido">
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="text-muted-foreground"
                        aria-label="Recorrido de esta pantalla"
                        @click="abrir(recorrido)"
                    >
                        <CircleHelpIcon />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Recorrido de esta pantalla</TooltipContent>
            </Tooltip>
        </div>
    </div>
</template>
