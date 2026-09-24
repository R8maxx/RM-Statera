<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import ConmutadorContexto from '@/components/contexto/ConmutadorContexto.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * El historial de revisiones del contexto.
 *
 * **Es la razón entera de que el módulo lleve análisis versionados.** La cláusula
 * 9.3 pide «cambios de contexto» como entrada obligatoria de la revisión por la
 * dirección, y sin esta pantalla la respuesta sería releer dos DAFO enteros y
 * compararlos a ojo.
 *
 * Las altas y las bajas salen directamente de las filas —cada cuestión sabe qué
 * análisis la dio de alta—, no de comparar dos instantáneas. Por eso es exacto y
 * barato: una cuestión no «aparece» entre dos revisiones, la da de alta una
 * concreta y eso está escrito.
 *
 * Es una tabla (`AnalisisContextoRecurso`) y no una tarjeta por revisión: las
 * altas y las bajas de cada una se comparan con las de las demás.
 */

defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    hayBorrador: boolean;
}>();
</script>

<template>
    <AppLayout :titulo="recurso.etiquetas.plural" ancho="completo">
        <CabeceraPagina :titulo="recurso.etiquetas.plural" :descripcion="recurso.etiquetas.descripcion">
            <template #acciones>
                <ConmutadorContexto vista="analisis" />
            </template>
        </CabeceraPagina>

        <Aviso v-if="hayBorrador" titulo="Hay una revisión abierta sin firmar">
            Lo que se escriba en el DAFO y en las partes interesadas se anota en ella hasta que
            alguien la apruebe.
        </Aviso>

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
