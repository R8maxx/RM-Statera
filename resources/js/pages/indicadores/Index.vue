<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * El cuadro de indicadores: § 4.14 y la cláusula 9.1 de ISO.
 *
 * **Sin acciones masivas.** Sellar una medición es un acto por indicador —cada
 * uno tiene su cadencia, su periodo y su cifra—, y «medir cuarenta de golpe»
 * escribiría cuarenta filas que nadie ha mirado. El comando de las 07:30 hace
 * exactamente eso y lo hace bien porque no lo decide nadie en ese momento.
 *
 * El indicador que más importa es «Periodo sin medir»: es el único rojo del
 * módulo, y es la 9.1 sin hacer. Quedarse por debajo de un objetivo va en ámbar
 * a propósito — es la distancia que queda, no un incumplimiento, y pintarla de
 * alarma enseñaría a ponerse objetivos flojos.
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
    <AppLayout :titulo="recurso.etiquetas.plural">
        <CabeceraPagina
            :titulo="recurso.etiquetas.plural"
            :descripcion="recurso.etiquetas.descripcion"
        />

        <TiraIndicadores
            :alertas="alertas"
            :pendientes="pendientes"
            :denominador="total"
            denominador-etiqueta="indicadores declarados"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
