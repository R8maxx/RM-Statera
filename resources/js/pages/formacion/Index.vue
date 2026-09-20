<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';

/**
 * Las sesiones de formación y concienciación: `mp.per.4` y `mp.per.3`.
 *
 * **Sin tira de indicadores**, a diferencia de personas: lo que pide acción aquí
 * no es una sesión, es una **persona** sin formar, y esa cifra ya está en
 * `/personas` con su filtro. Repetirla aquí sobre otro denominador —sesiones en
 * vez de personas— daría dos números que parecen el mismo y no lo son.
 *
 * Lo único que sí es de aquí es la sesión que se registró y a la que nadie
 * apuntó a nadie: una convocatoria vacía no prueba nada.
 */
defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    total: number;
    sinAsistencia: number;
}>();
</script>

<template>
    <AppLayout :titulo="recurso.etiquetas.plural">
        <CabeceraPagina
            :titulo="recurso.etiquetas.plural"
            :descripcion="recurso.etiquetas.descripcion"
        />

        <p v-if="total > 0" class="text-sm text-muted-foreground">
            <Cifra class="font-semibold text-foreground" :valor="total" />
            {{ total === 1 ? 'sesión registrada' : 'sesiones registradas' }}<template
                v-if="sinAsistencia > 0"
            >
                ·
                <Cifra class="font-semibold text-foreground" :valor="sinAsistencia" />
                sin nadie convocado todavía</template
            >.
        </p>

        <DataTable :recurso="recurso" :filas="filas" :meta="meta" />
    </AppLayout>
</template>
