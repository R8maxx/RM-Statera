<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * El registro de vulnerabilidades: invariante 8, A.8.8 y `op.exp.4`.
 *
 * **Un rojo, y es el plazo**: una vulnerabilidad con el plazo de remediación
 * pasado y sin arreglo. Una crítica en plazo, una mitigada sin verificar o una
 * aceptada son trabajo pendiente, o riesgo asumido, y no alarma.
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
            recorrido="vulnerabilidades"
        />

        <!-- Con cero vulnerabilidades, «sin incidencias sobre 0» no dice nada: el
             estado vacío de la tabla ya explica qué falta. -->
        <TiraIndicadores
            data-recorrido="vulnerabilidades-indicadores"
            v-if="total > 0"
            :alertas="alertas"
            :pendientes="pendientes"
            :denominador="total"
            denominador-etiqueta="vulnerabilidades vivas"
            :filtros="props.meta.filtros"
        />

        <DataTable data-recorrido="vulnerabilidades-registro" :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
