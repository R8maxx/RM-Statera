<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { motion } from 'motion-v';
import { computed } from 'vue';

export interface DimensionValorada {
    codigo: string;
    nombre: string;
    nivel: string;
    nivelEtiqueta: string;
    peso: number;
}

export interface ValoracionSerializada {
    dimensiones: DimensionValorada[];
    maximo: string;
    maximoEtiqueta: string;
    categoria: string | null;
    categoriaEtiqueta: string | null;
}

/**
 * Las cinco dimensiones, con lo que valoró la organización y lo que el activo
 * vale de verdad contando el grafo.
 *
 * Las dos cifras se enseñan juntas y nunca una sola: el auditor pregunta qué
 * valoró la organización, no qué dedujo la herramienta, y una tabla que sólo
 * muestre la efectiva deja sin defensa a quien tiene que explicar la suya.
 *
 * Lo heredado se marca con texto además de con el peso de la tipografía, porque
 * el color nunca es la única lectura de un estado (DESIGN.md §11).
 */
const props = withDefaults(
    defineProps<{
        propia: ValoracionSerializada;
        efectiva: ValoracionSerializada;
        /**
         * De quién hereda cada dimensión, por código: `{ D: ['SRV-0001'] }`.
         * «Heredado» a secas no dice a quién preguntar.
         */
        origenes?: Record<string, string[]>;
    }>(),
    { origenes: () => ({}) },
);

/** Los tres escalones que ordenan: «no aplica» no es un escalón, es no valorar. */
const escalones = ['Bajo', 'Medio', 'Alto'];

const filas = computed(() =>
    props.propia.dimensiones.map((dimension, indice) => {
        const efectiva = props.efectiva.dimensiones[indice];

        return {
            ...dimension,
            efectivaEtiqueta: efectiva.nivelEtiqueta,
            efectivaPeso: efectiva.peso,
            heredada: efectiva.peso > dimension.peso,
            origen: props.origenes[dimension.codigo] ?? [],
        };
    }),
);

const hayHerencia = computed(() => filas.value.some((fila) => fila.heredada));
const { reducido } = useMovimientoReducido();
</script>

<template>
    <div class="space-y-3">
        <!--
            La escala de pasos es lo que §9 pide para un ordinal: si «alto» es
            más que «medio» lo contesta la longitud antes que el texto. El
            relleno es la efectiva y el contorno marca hasta dónde llegó la
            valorada, así que la subida por el grafo se ve como el tramo entre
            los dos.
        -->
        <div class="hidden flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground sm:flex" aria-hidden="true">
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2 w-3.5 rounded-full ring-2 ring-foreground/70 ring-inset" />
                Valorada por la organización
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2 w-3.5 rounded-full bg-primary" />
                Efectiva, contando el grafo
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                        <th scope="col" class="pb-2 font-semibold">Dimensión</th>
                        <th scope="col" class="pb-2 font-semibold">Valorada</th>
                        <th scope="col" class="hidden pr-4 pb-2 font-semibold sm:table-cell">
                            <span class="flex max-w-64 justify-between">
                                <span v-for="escalon in escalones" :key="escalon">{{ escalon }}</span>
                            </span>
                        </th>
                        <th scope="col" class="pb-2 font-semibold">Efectiva</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="fila in filas" :key="fila.codigo">
                        <th scope="row" class="py-3 pr-3 text-left" :class="fila.heredada ? 'font-medium' : 'font-normal'">
                            {{ fila.nombre }}
                        </th>
                        <td class="py-3 pr-3 text-muted-foreground">{{ fila.nivelEtiqueta }}</td>
                        <td class="hidden py-3 pr-4 sm:table-cell" aria-hidden="true">
                            <span class="flex max-w-64 gap-0.5">
                                <span
                                    v-for="(escalon, posicion) in escalones"
                                    :key="escalon"
                                    class="h-2 flex-1 rounded-full transition-colors"
                                    :class="[
                                        posicion < fila.efectivaPeso ? 'bg-primary' : 'bg-muted',
                                        posicion < fila.peso ? 'ring-2 ring-foreground/70 ring-inset' : '',
                                    ]"
                                />
                            </span>
                        </td>
                        <!--
                            La efectiva llega después de la valorada, 180 ms.
                            Es lo que enseña que una DERIVA de la otra en vez de
                            ser dos columnas puestas al lado: la valoración sube
                            por el grafo, y aquí se ve subir. Sólo en las filas
                            heredadas — donde coinciden no hay nada que contar y
                            un retraso sería un tropiezo.
                        -->
                        <motion.td
                            class="py-3"
                            :initial="reducido || !fila.heredada ? { opacity: 1 } : { opacity: 0, y: -4 }"
                            :animate="{ opacity: 1, y: 0 }"
                            :transition="{
                                duration: reducido ? 0 : 0.22,
                                delay: reducido || !fila.heredada ? 0 : 0.18,
                                ease: [0.16, 1, 0.3, 1],
                            }"
                        >
                            <span :class="fila.heredada ? 'font-semibold text-primary' : 'text-muted-foreground'">
                                {{ fila.efectivaEtiqueta }}
                            </span>
                            <span v-if="fila.heredada" class="block text-xs text-muted-foreground">
                                heredado<template v-if="fila.origen.length > 0">
                                    de <span class="cifra">{{ fila.origen.join(', ') }}</span></template>
                            </span>
                        </motion.td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="hayHerencia" class="text-sm text-muted-foreground">
            La valoración efectiva es la más alta entre la propia y la de todo lo que se apoya en este activo. No
            sustituye a la valorada: las dos se conservan.
        </p>
    </div>
</template>
