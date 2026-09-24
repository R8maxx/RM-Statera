<script setup lang="ts">
import IconoTipo from '@/components/IconoTipo.vue';
import type { NodoActivo } from '@/lib/grafoActivos';
import { tono } from '@/lib/tonos';
import { Link } from '@inertiajs/vue3';
import { Handle, Position } from '@vue-flow/core';
import { computed } from 'vue';

/**
 * Una caja del grafo de dependencias.
 *
 * **El color dice la valoración EFECTIVA**, que es lo que paga esta pantalla:
 * una base de datos valorada «bajo» que sostiene un servicio esencial vale
 * «alto», y en el diagrama se ve **por dónde** le sube. El tono lo declara el
 * dominio —`NivelDimension::tono()`, en la familia ordinal— y aquí sólo se
 * traduce a clases con `lib/tonos.ts`, como en cualquier badge del producto.
 *
 * **El nivel va además escrito**, porque el color no puede ser el único canal
 * (DESIGN.md § 11) y porque un ordinal de tres escalones distingue peor que un
 * estado: «medio» y «alto» son el mismo teal a distinta fuerza.
 *
 * El activo del centro lleva anillo de marca en vez de otro color: es el sujeto
 * de la pantalla, no un nivel más.
 */
const props = defineProps<{
    data: { activo: NodoActivo };
}>();

const activo = computed(() => props.data.activo);

const nivel = computed(() => tono(activo.value.nivelTono));
</script>

<template>
    <Handle type="target" :position="Position.Top" :connectable="false" />

    <div
        class="flex h-full w-full flex-col gap-1 rounded-xl border bg-card px-3 py-2 text-left shadow-xs"
        :class="activo.sentido === 'centro' ? 'border-primary ring-2 ring-primary/25' : 'border-border'"
    >
        <p class="flex items-center gap-1.5 text-xs leading-none text-muted-foreground">
            <IconoTipo v-if="activo.tipoIcono" :nombre="activo.tipoIcono" class="size-3 shrink-0" />
            <span class="cifra">{{ activo.codigo }}</span>
            <span class="truncate">{{ activo.tipoEtiqueta }}</span>
        </p>

        <Link
            :href="`/activos/${activo.id}`"
            class="line-clamp-2 text-sm leading-snug font-medium underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            {{ activo.nombre }}
        </Link>

        <p class="mt-auto flex items-center gap-1.5 text-xs">
            <span class="size-1.5 shrink-0 rounded-full" :class="nivel.punto" aria-hidden="true" />
            <span class="text-muted-foreground">
                Valor efectivo
                <span class="font-medium text-foreground">{{ activo.nivelEtiqueta ?? 'sin valorar' }}</span>
            </span>
        </p>
    </div>

    <Handle type="source" :position="Position.Bottom" :connectable="false" />
</template>
