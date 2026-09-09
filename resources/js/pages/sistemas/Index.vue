<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
}>();
</script>

<template>
    <AppLayout :titulo="recurso.etiquetas.plural">
        <CabeceraPagina
            :titulo="recurso.etiquetas.plural"
            :descripcion="recurso.etiquetas.descripcion"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
