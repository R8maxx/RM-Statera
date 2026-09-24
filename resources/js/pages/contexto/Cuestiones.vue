<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import ConmutadorContexto from '@/components/contexto/ConmutadorContexto.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * Las cuestiones internas y externas, en tabla. Cláusula 4.1 de ISO 27001.
 *
 * Es la trastienda de `/contexto`: allí se lee la matriz y aquí se trabaja. A las
 * treinta o cuarenta cuestiones hacen falta filtros, orden y las dos columnas que
 * dicen qué se ha atado y qué no.
 *
 * **Enseña también las retiradas**, a diferencia de la matriz. Sacarlas dejaría sin
 * sitio la pregunta «¿qué había el año pasado que ya no está?», que es literalmente
 * lo que pide la cláusula 9.3.
 *
 * **Sin acciones masivas.** Retirar exige un motivo escrito, y un motivo escrito
 * una vez para cuarenta filas no es un motivo, es un trámite: el mismo criterio que
 * dejó `descartada` fuera de la masiva de tareas.
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
        >
            <template #acciones>
                <ConmutadorContexto vista="cuestiones" />
            </template>
        </CabeceraPagina>

        <TiraIndicadores
            :alertas="alertas"
            :pendientes="pendientes"
            :denominador="total"
            denominador-etiqueta="cuestiones vigentes"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
