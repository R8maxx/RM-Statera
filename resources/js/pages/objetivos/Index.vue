<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * Los objetivos de seguridad de la información: la cláusula 6.2.
 *
 * **Sin acciones masivas.** Las transiciones de este registro son decisiones de
 * dirección —firmar un compromiso, declarar si se alcanzó, retirarlo— y tres de
 * ellas exigen algo escrito. Un motivo escrito una vez para veinte filas no es
 * un motivo, es un trámite. Mismo criterio que en no conformidades y en tareas.
 *
 * El indicador que más importa es «Sin indicador»: la 6.2 exige que el objetivo
 * sea medible, y uno sin ninguna cifra detrás lo cumple de palabra y no de hecho.
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
        />

        <TiraIndicadores
            :alertas="alertas"
            :pendientes="pendientes"
            :denominador="total"
            denominador-etiqueta="objetivos registrados"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
