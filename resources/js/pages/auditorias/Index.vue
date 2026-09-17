<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * El registro de auditorías: § 4.12 y la cláusula 9.2 de ISO.
 *
 * **Sin acciones masivas.** Cerrar una auditoría congela su checklist y la vuelve
 * inmutable en la base: es un acto con su fecha y su firmante, no algo que se
 * haga a cincuenta filas de golpe. Mismo criterio que riesgos con la aceptación.
 *
 * La columna «Revisadas» va con su denominador —«12 de 52»— porque es la razón
 * entera por la que existe la checklist: sin él, tres hallazgos no dicen si la
 * auditoría miró tres medidas o cincuenta.
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
            denominador-etiqueta="auditorías registradas"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
