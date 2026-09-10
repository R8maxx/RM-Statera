<script setup lang="ts">
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
const props = defineProps<{
    propia: ValoracionSerializada;
    efectiva: ValoracionSerializada;
}>();

const filas = computed(() =>
    props.propia.dimensiones.map((dimension, indice) => {
        const efectiva = props.efectiva.dimensiones[indice];

        return {
            ...dimension,
            efectivaEtiqueta: efectiva.nivelEtiqueta,
            heredada: efectiva.peso > dimension.peso,
        };
    }),
);

const hayHerencia = computed(() => filas.value.some((fila) => fila.heredada));
</script>

<template>
    <div class="space-y-3">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[26rem] text-sm">
                <thead>
                    <tr class="border-b text-left text-xs text-muted-foreground">
                        <th scope="col" class="pb-2 font-medium">Dimensión</th>
                        <th scope="col" class="pb-2 font-medium">Valorada</th>
                        <th scope="col" class="pb-2 font-medium">Efectiva</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="fila in filas" :key="fila.codigo">
                        <th scope="row" class="py-2 text-left font-normal">{{ fila.nombre }}</th>
                        <td class="py-2 text-muted-foreground">{{ fila.nivelEtiqueta }}</td>
                        <td class="py-2" :class="fila.heredada ? 'font-medium text-primary' : 'text-muted-foreground'">
                            {{ fila.efectivaEtiqueta }}
                            <span v-if="fila.heredada" class="text-xs font-normal text-muted-foreground">
                                · heredado
                            </span>
                        </td>
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
