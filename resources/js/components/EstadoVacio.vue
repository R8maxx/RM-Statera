<script setup lang="ts">
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
        class="flex flex-col items-center px-6 py-14 text-center"
    >
        <span
            v-if="icono"
            class="mb-4 flex size-11 items-center justify-center rounded-full bg-accent text-accent-foreground"
        >
            <component :is="icono" class="size-5" />
        </span>

        <p class="text-sm font-medium">{{ titulo }}</p>
        <p v-if="descripcion" class="mt-1.5 max-w-sm text-sm text-muted-foreground">{{ descripcion }}</p>

        <Link v-if="accion" :href="accion.href" class="mt-5">
            <Button size="sm">{{ accion.etiqueta }}</Button>
        </Link>
    </motion.div>
</template>
