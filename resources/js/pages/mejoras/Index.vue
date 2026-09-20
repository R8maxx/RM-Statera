<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * El registro de oportunidades de mejora: la cláusula 10.1.
 *
 * **Sin alertas, y es el único registro del producto que va así.** Ninguna cifra
 * de aquí va mal de verdad: una idea sin hacer no incumple nada, y la 10.1 pide
 * mejorar de forma continua, no tener cero ideas pendientes. Pintar de rojo lo que
 * alguien apuntó voluntariamente es la forma más rápida de que deje de apuntarlo.
 *
 * **Y sin acciones masivas**, como en no conformidades: descartar exige un motivo
 * escrito, y un motivo escrito una vez para veinte filas no es un motivo.
 */
const props = defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    pendientes: App.Http.Resources.Panel.Indicador[];
    total: number;
}>();
</script>

<template>
    <AppLayout :titulo="recurso.etiquetas.plural">
        <CabeceraPagina
            :titulo="recurso.etiquetas.plural"
            :descripcion="recurso.etiquetas.descripcion"
        />

        <TiraIndicadores
            :alertas="[]"
            :pendientes="pendientes"
            :denominador="total"
            denominador-etiqueta="mejoras registradas"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
