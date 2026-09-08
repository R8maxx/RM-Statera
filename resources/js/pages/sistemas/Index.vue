<script setup lang="ts">
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
        <p v-if="recurso.etiquetas.descripcion" class="mb-4 max-w-3xl text-sm text-muted-foreground">
            {{ recurso.etiquetas.descripcion }}
        </p>

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
