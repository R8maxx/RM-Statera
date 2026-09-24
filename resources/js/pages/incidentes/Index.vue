<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import TiraIndicadores from '@/components/TiraIndicadores.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * El registro de incidentes: § 4.10 y `op.exp.7`.
 *
 * **Una sola alerta, y es el plazo de la AEPD.** Es el único número que pone la
 * ley —72 h, artículo 33.1 del RGPD— y el único sitio del módulo donde algo está
 * incumplido ahora mismo. Ni los estados ni la peligrosidad gastan rojo: un
 * incidente crítico abierto no va mal, va siendo atendido.
 *
 * **Y el CCN-CERT no tiene cifra**, a propósito: el RD 311/2022 dice «sin
 * dilación» y no fija horas, así que no hay plazo que contar sin inventárselo.
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
            denominador-etiqueta="incidentes registrados"
            :filtros="props.meta.filtros"
        />

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
