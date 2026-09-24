<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import PestanasContinuidad from '@/components/continuidad/PestanasContinuidad.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * El BIA de cada servicio: § 4.11.
 *
 * **Dos alertas, y ninguna es un incumplimiento consumado.** Un RTO
 * incoherente con el umbral tolerable es una contradicción que corregir, y una
 * revisión vencida es un dato que envejece — las dos gastan `caducada` porque
 * las dos piden atención ahora, pero ninguna es lo mismo que el rojo de un
 * plazo legal ya incumplido, como el de la AEPD en incidentes.
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

        <PestanasContinuidad />

        <TiraIndicadores
            :alertas="alertas"
            :pendientes="pendientes"
            :denominador="total"
            denominador-etiqueta="BIA registrados"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
