<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    /** Lo que pide acción hoy: revisiones vencidas y firmas pendientes. */
    alertas: App.Http.Resources.Panel.Indicador[];
}>();
</script>

<template>
    <AppLayout ancho="completo" :titulo="recurso.etiquetas.plural">
        <CabeceraPagina
            :titulo="recurso.etiquetas.plural"
            :descripcion="recurso.etiquetas.descripcion"
        />

        <!--
            Sin lista de «pendientes de completar»: aquí no hay datos a medias
            que rellenar, hay decisiones esperando. El denominador es el total de
            documentos, que es sobre lo que se cuentan las dos cifras.
        -->
        <TiraIndicadores
            :alertas="alertas"
            :pendientes="[]"
            :denominador="props.meta.total"
            denominador-etiqueta="documentos"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
