<script setup lang="ts">
import { OCUPANTES_A_LA_VISTA, type NodoOrganigrama } from '@/lib/organigrama';
import { Link } from '@inertiajs/vue3';
import { Handle, Position } from '@vue-flow/core';
import { UserIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Una caja del organigrama.
 *
 * **Es HTML y no un dibujo dentro del SVG**, que es lo que se gana con Vue
 * Flow: el texto se selecciona, el enlace es un enlace de verdad —tabulable y
 * con su menú contextual—, y el color sale de los tokens de `app.css` como en
 * el resto del producto. Un nodo pintado en SVG obligaría a repetir la paleta en
 * JavaScript, que es justo el motivo por el que se descartó Chart.js.
 *
 * Los dos `Handle` son de donde salen y a donde llegan las líneas. Van
 * `:connectable="false"`: aquí no se conectan nodos arrastrando —la jerarquía se
 * cambia en la ficha del puesto, con su comprobación de ciclos— y dejar el punto
 * activo prometería un gesto que no existe.
 */
const props = defineProps<{
    data: { puesto: NodoOrganigrama; conPersonas: boolean };
}>();

const puesto = computed(() => props.data.puesto);

const visibles = computed(() => puesto.value.ocupantes.slice(0, OCUPANTES_A_LA_VISTA));
const deMas = computed(() => Math.max(0, puesto.value.ocupantes.length - OCUPANTES_A_LA_VISTA));
</script>

<template>
    <Handle type="target" :position="Position.Top" :connectable="false" />

    <div
        class="flex h-full w-full flex-col gap-1 rounded-xl border bg-card px-3 py-2.5 text-left shadow-xs"
        :class="puesto.caracterizado ? 'border-border' : 'border-dashed'"
    >
        <p class="cifra text-xs leading-none text-muted-foreground">{{ puesto.codigo }}</p>

        <Link
            :href="`/puestos/${puesto.id}`"
            class="text-sm leading-snug font-medium underline-offset-4 hover:underline"
        >
            {{ puesto.titulo }}
        </Link>

        <!--
            El borde discontinuo ya dice que no está caracterizado, pero el color
            no puede ser el único canal —DESIGN.md § 11— y una línea de puntos
            tampoco lo es para quien no la distingue. Por eso va escrito.
        -->
        <p v-if="!puesto.caracterizado && !data.conPersonas" class="text-xs text-muted-foreground">
            Sin caracterizar
        </p>

        <template v-if="data.conPersonas">
            <ul v-if="visibles.length > 0" class="mt-0.5 space-y-0.5">
                <li
                    v-for="ocupante in visibles"
                    :key="ocupante.id"
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <UserIcon class="size-3 shrink-0" aria-hidden="true" />
                    <Link
                        :href="`/personas/${ocupante.id}`"
                        class="truncate underline-offset-4 hover:underline"
                    >
                        {{ ocupante.nombre }}
                    </Link>
                </li>
                <li v-if="deMas > 0" class="pl-4.5 text-xs text-muted-foreground">
                    y {{ deMas }} más
                </li>
            </ul>

            <p v-else class="mt-0.5 text-xs text-muted-foreground italic">Sin ocupar</p>
        </template>
    </div>

    <Handle type="source" :position="Position.Bottom" :connectable="false" />
</template>
