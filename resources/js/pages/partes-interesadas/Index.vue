<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * Las partes interesadas: la cláusula 4.2 de ISO 27001.
 *
 * El indicador que importa es **«Con obligaciones sin cubrir»**: de todo lo que un
 * regulador o un contrato exigen, cuánto no tiene ninguna medida detrás. Esa es la
 * pregunta entera de la cláusula, y va en ámbar y no en rojo — es trabajo por
 * hacer sobre algo que la organización ha sabido identificar, no un incumplimiento.
 *
 * **Sin acciones masivas**, como en cuestiones: retirar exige un motivo escrito.
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
            denominador-etiqueta="partes interesadas vigentes"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
