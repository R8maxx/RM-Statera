<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * Proveedores y terceros: § 4.9, A.5.19 a A.5.23, `op.ext` y `op.nub`.
 *
 * **Dos rojos, y los dos caducan solos**: una reevaluación pasada de fecha y un
 * certificado caducado de alguien con quien se sigue trabajando. Un proveedor
 * rechazado o sin evaluar no va mal: es una decisión, o trabajo pendiente.
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
        <CabeceraPagina :titulo="recurso.etiquetas.plural" :descripcion="recurso.etiquetas.descripcion" />

        <!-- Con cero proveedores, «sin incidencias sobre 0» no dice nada: el
             estado vacío de la tabla ya explica qué falta. -->
        <TiraIndicadores
            v-if="total > 0"
            :alertas="alertas"
            :pendientes="pendientes"
            :denominador="total"
            denominador-etiqueta="proveedores con los que se trabaja"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
