<script setup lang="ts">
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
 */
withDefaults(
    defineProps<{
        titulo: string;
        descripcion?: string | null;
        /**
         * El filete de acento sobre el título. Se apaga en la pantalla que ya
         * lleve uno: la regla de DESIGN.md §6 es uno por bloque y nunca dos en
         * la misma pantalla.
         */
        filete?: boolean;
    }>(),
    { filete: true },
);
</script>

<template>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <!-- 3 px por 40, en violeta. El gesto distintivo de la marca. -->
            <span v-if="filete" class="mb-3 block h-[3px] w-10 bg-acento" aria-hidden="true" />

            <h1 class="text-xl font-semibold tracking-tight">{{ titulo }}</h1>
            <p v-if="descripcion" class="mt-1 max-w-2xl text-sm text-muted-foreground">{{ descripcion }}</p>

            <!-- Para lo que matiza al título sin ser acción: la leyenda de
                 campos obligatorios de un formulario, por ejemplo. -->
            <slot />
        </div>

        <div v-if="$slots.acciones" class="flex shrink-0 items-center gap-2">
            <slot name="acciones" />
        </div>
    </div>
</template>
