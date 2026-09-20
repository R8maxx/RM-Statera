<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * Las revisiones por la dirección: § 4.15 y la cláusula 9.3.
 *
 * **Sin tira de indicadores**, y es el único registro del producto que va así.
 * Una organización celebra una revisión al año: con cinco filas, tres baldosas
 * encima de la tabla no resumen nada que la tabla no enseñe ya. Lo único que
 * merece decirse —si hay alguna sin firmar— cabe en la descripción.
 *
 * **Y sin acciones masivas**: no hay ningún gesto de este registro que tenga
 * sentido sobre veinte filas a la vez.
 */
defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    total: number;
    sinFirmar: number;
}>();
</script>

<template>
    <AppLayout :titulo="recurso.etiquetas.plural">
        <CabeceraPagina
            :titulo="recurso.etiquetas.plural"
            :descripcion="recurso.etiquetas.descripcion"
        />

        <p v-if="sinFirmar > 0" class="text-sm text-muted-foreground">
            <span class="cifra font-medium text-foreground">{{ sinFirmar }}</span>
            de {{ total }}
            {{ sinFirmar === 1 ? 'sigue sin firmar' : 'siguen sin firmar' }}.
            Hasta que el acta se aprueba, las entradas no quedan congeladas.
        </p>

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
