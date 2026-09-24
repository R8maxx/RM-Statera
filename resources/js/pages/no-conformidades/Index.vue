<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * El registro de no conformidades: § 4.13 y la cláusula 10.2 de ISO.
 *
 * **Sin acciones masivas.** Cada transición de este registro exige algo escrito
 * —el motivo de una anulación, el resultado de una verificación— y un texto
 * escrito una vez para cincuenta filas no es un motivo, es un trámite. Mismo
 * criterio que dejó `descartada` fuera de la masiva de tareas.
 *
 * El indicador que más importa es «Sin verificar»: una no conformidad cerrada y
 * no verificada se lee como resuelta y no lo está, y es justo lo que el auditor
 * comprueba.
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
            denominador-etiqueta="no conformidades registradas"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
