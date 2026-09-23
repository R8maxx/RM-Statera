<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import PestanasContinuidad from '@/components/continuidad/PestanasContinuidad.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * Las pruebas de un plan de continuidad: § 4.11 y `op.cont.3`.
 *
 * **La única alerta es «Vencidas», y gasta `caducada` de verdad**: una
 * planificada cuya fecha ya pasó sin resultado es un plazo incumplido, no una
 * contradicción a corregir como el RTO incoherente del BIA.
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

        <PestanasContinuidad />

        <TiraIndicadores
            :alertas="alertas"
            :pendientes="pendientes"
            :denominador="total"
            denominador-etiqueta="pruebas registradas"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
