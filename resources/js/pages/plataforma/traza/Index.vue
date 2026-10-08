<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * La traza de la plataforma (punto 50): lo que han hecho quienes administran
 * Statera, filtrable y exportable a CSV. Lo que pregunta el auditor de nuestro
 * propio SGSI sobre el acceso privilegiado.
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
