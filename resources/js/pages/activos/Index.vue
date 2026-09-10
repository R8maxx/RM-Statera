<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import IndicadoresInventario from '@/components/activo/IndicadoresInventario.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

type Accion = App.Http.Resources.Definicion.Accion;

const props = defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    alertas: App.Http.Resources.Panel.IndicadorInventario[];
    pendientes: App.Http.Resources.Panel.IndicadorInventario[];
    vigentes: number;
}>();

/**
 * Las dos acciones masivas se resuelven aquí porque hacen cosas distintas:
 * etiquetar navega a una hoja imprimible y revisar escribe una fecha. La tabla
 * genérica aporta la selección, no la semántica.
 */
function masiva(accion: Accion, ids: (number | string)[]): void {
    if (accion.clave === 'etiquetas') {
        router.get('/activos/etiquetas', { ids });

        return;
    }

    router.post('/activos/revision', { activos: ids }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :titulo="recurso.etiquetas.plural">
        <CabeceraPagina
            :titulo="recurso.etiquetas.plural"
            :descripcion="recurso.etiquetas.descripcion"
        />

        <IndicadoresInventario
            :alertas="alertas"
            :pendientes="pendientes"
            :vigentes="vigentes"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" @masiva="masiva" />
    </AppLayout>
</template>
