<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * El plan de comunicación: la cláusula 7.4.
 *
 * **Sin acciones masivas**: registrar que se comunicó algo lleva su fecha, su
 * asunto y su prueba, y veinte filas marcadas de golpe son veinte hechos que
 * nadie ha mirado. Mismo argumento que en obligaciones.
 */
const props = defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    alertas: App.Http.Resources.Panel.Indicador[];
    pendientes: App.Http.Resources.Panel.Indicador[];
    total: number;
}>();
</script>

<template>
    <AppLayout ancho="completo" :titulo="recurso.etiquetas.plural">
        <CabeceraPagina
            :titulo="recurso.etiquetas.plural"
            :descripcion="recurso.etiquetas.descripcion"
            recorrido="plan-comunicacion"
        />

        <TiraIndicadores
            data-recorrido="plan-comunicacion-tira"
            :alertas="alertas"
            :pendientes="pendientes"
            :denominador="total"
            denominador-etiqueta="comunicaciones en el plan"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" data-recorrido="plan-comunicacion-tabla" />
    </AppLayout>
</template>
