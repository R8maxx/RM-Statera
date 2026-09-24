<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * Las sesiones de formación y concienciación: `mp.per.4` y `mp.per.3`.
 *
 * **Sin alertas, y a propósito**: lo que va mal de verdad no es una sesión, es
 * una **persona** sin formar, y esa cifra vive en `/personas` con su filtro.
 * Repetirla aquí sobre otro denominador —sesiones en vez de personas— daría dos
 * números que parecen el mismo y no lo son.
 *
 * Lo único que sí es de aquí es la sesión que se registró y a la que nadie
 * apuntó a nadie: una convocatoria vacía no prueba nada. Va en la tira y no
 * como texto suelto porque **tiene filtro detrás**, y una cifra que no lleva a
 * su lista se mira en vez de accionarse.
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
        />

        <TiraIndicadores
            :alertas="alertas"
            :pendientes="pendientes"
            :denominador="total"
            denominador-etiqueta="sesiones registradas"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
