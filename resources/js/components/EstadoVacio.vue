<script setup lang="ts">
import SimboloBalanza from '@/components/SimboloBalanza.vue';
import { Button } from '@/components/ui/button';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { Link } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import { motion } from 'motion-v';

/**
 * El estado vacío, compuesto de verdad.
 *
 * Un vacío no es un error ni un hueco: es el único momento en que se puede
 * explicar para qué sirve la pantalla. Antes cada sitio improvisaba el suyo con
 * un `<p>` gris, y el más importante —el panel sin ningún sistema, que es lo
 * primero que ve alguien que estrena la herramienta— no decía qué hacer.
 */
defineProps<{
    icono?: LucideIcon;
    titulo: string;
    descripcion?: string;
    accion?: { etiqueta: string; href: string };
}>();

const { variantesEntrada } = useMovimientoReducido();
</script>

<template>
    <motion.div
        :variants="variantesEntrada"
        initial="oculto"
        animate="visible"
        class="relative flex flex-col items-center overflow-hidden px-6 py-14 text-center"
    >
        <!--
            La balanza ampliada y recortada por el borde, al 7 % de opacidad.

            `DESIGN.md` §2 la autoriza justo aquí desde el principio —«sirve de
            fondo en portadas, cabeceras de informe y estados vacíos»— y no
            estaba puesta en ninguno: la única pieza de marca del producto se
            gastaba entera en la pantalla de acceso, que es la que nadie mira a
            partir del segundo día.

            Un vacío es el mejor sitio para el símbolo dentro del chrome de
            trabajo, y por un motivo que no es estético: **desaparece en cuanto
            hay datos**. No puede cansar a quien tiene esto ocho horas abierto,
            porque a esa persona sólo le sale cuando no hay nada que mirar. Una
            por pieza y nunca en patrón, como pide §2. Trazo más fino que en el
            logotipo: a este tamaño, un 2 sobre retícula de 32 se convierte en
            una raya gorda.

            **Va al borde y no detrás del texto.** Centrada, el fiel de la
            balanza pasaba justo por debajo del círculo del icono: la misma
            figura dos veces en el mismo eje, que es el fallo que ya se corrigió
            una vez en el panel de acceso —«el símbolo no se repite»—. Recortada
            por la esquina se lee como un recurso gráfico y deja la columna de
            texto limpia, que es además lo que §2 pide literalmente.
        -->
        <SimboloBalanza
            :trazo="1"
            class="pointer-events-none absolute -right-20 -bottom-24 h-[22rem] w-[22rem] text-primary opacity-[0.07] select-none"
        />

        <span
            v-if="icono"
            class="relative mb-4 flex size-11 items-center justify-center rounded-full bg-accent text-accent-foreground"
        >
            <component :is="icono" class="size-5" />
        </span>

        <p class="relative text-sm font-medium">{{ titulo }}</p>
        <p v-if="descripcion" class="relative mt-1.5 max-w-sm text-sm text-muted-foreground">
            {{ descripcion }}
        </p>

        <Button v-if="accion" as-child size="sm" class="mt-5">
            <Link :href="accion.href">{{ accion.etiqueta }}</Link>
        </Button>

        <!--
            La salida cuando no es un enlace.

            `accion` cubre el caso normal —ir a otra pantalla— y no llega cuando
            la salida es un POST, que es lo que necesita «asumir las obligaciones
            del catálogo»: ahí no se navega a ningún sitio, se escriben filas. El
            hueco admite los botones que haga falta y deja `accion` como estaba,
            que es lo que usan las demás pantallas.
        -->
        <div v-if="$slots.default" class="relative mt-5 flex flex-wrap justify-center gap-2">
            <slot />
        </div>
    </motion.div>
</template>
