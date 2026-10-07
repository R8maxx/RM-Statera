<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * Los clientes de Statera, vistos desde la plataforma (punto 41).
 *
 * Sólo la ficha comercial: lo que cada organización guarda dentro no se lee
 * desde aquí. La tabla es la de siempre, con la capa de recursos.
 */
defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
}>();
</script>

<template>
    <AppLayout ancho="completo" :titulo="recurso.etiquetas.plural">
        <CabeceraPagina :titulo="recurso.etiquetas.plural" :descripcion="recurso.etiquetas.descripcion" />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
