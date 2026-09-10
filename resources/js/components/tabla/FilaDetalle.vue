<script setup lang="ts">
import CeldaValor from '@/components/tabla/celdas/CeldaValor.vue';

type Columna = App.Http.Resources.Definicion.Columna;

/**
 * Lo que no cabe en la fila, desplegado debajo de ella.
 *
 * Es el gesto que hacen las tablas responsive de toda la vida: se dejan a la
 * vista las columnas que se recorren y el resto se consulta bajo demanda, en
 * lugar de obligar a desplazarse dos pantallas a la derecha para leer un número
 * de serie.
 *
 * **No pide nada al servidor.** `ConsultaRecurso` serializa todas las columnas
 * declaradas, estén visibles o no, así que el valor ya viaja en la fila. Ocultar
 * una columna es una decisión de pintado, no de consulta.
 *
 * Se pinta con `CeldaValor`, el mismo componente que la tabla: un badge tiene
 * que verse igual aquí que en su columna, o desplegar la fila parecería llevar a
 * otro sitio.
 */
defineProps<{
    columnas: Columna[];
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    fila: Record<string, any>;
}>();
</script>

<template>
    <div class="bg-muted/30 px-3 py-3">
        <dl class="grid gap-x-8 gap-y-2.5 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="columna in columnas"
                :key="columna.clave"
                class="flex min-w-0 items-baseline justify-between gap-3 border-b border-border/60 pb-2"
            >
                <dt class="shrink-0 text-xs text-muted-foreground">{{ columna.etiqueta }}</dt>
                <dd class="min-w-0 text-right text-sm">
                    <CeldaValor :columna="columna" :valor="fila[columna.clave]" />
                </dd>
            </div>
        </dl>
    </div>
</template>
