<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import DataTable, { type Fila } from '@/components/tabla/DataTable.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Auditoria {
    id: number;
    codigo: string;
    sistema: string | null;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    admiteCambios: boolean;
}

const props = defineProps<{
    recurso: App.Http.Resources.Definicion.DefinicionRecurso;
    filas: Fila[];
    meta: App.Http.Resources.Definicion.MetaTabla;
    auditoria: Auditoria;
    puedeGestionar: boolean;
}>();

/*
 * La acción masiva la resuelve la página, como en implantaciones y en activos:
 * la tabla aporta la selección y la acción, y la semántica es de aquí. La URL se
 * compone con el id de la auditoría y **no viene en la definición**, que es lo
 * que se cachea: meter el padre ahí es lo que obliga a separar la clave de caché.
 *
 * Sólo marca conformes. «No conforme» y «observación» piden un hallazgo detrás
 * que las explique, y marcar cuarenta de golpe fabricaría cuarenta huecos — el
 * mismo argumento que dejó `descartada` fuera de la masiva de tareas.
 */
const enviando = ref(false);

function marcarConformes(_accion: App.Http.Resources.Definicion.Accion, ids: (number | string)[]): void {
    if (ids.length === 0 || enviando.value) {
        return;
    }

    enviando.value = true;

    router.post(
        `/auditorias/${props.auditoria.id}/checklist/resultado`,
        { puntos: ids },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
            },
        },
    );
}
</script>

<template>
    <AppLayout :titulo="`Checklist · ${auditoria.codigo}`">
        <CabeceraPagina
            :titulo="`Checklist de ${auditoria.codigo}`"
            :descripcion="recurso.etiquetas.descripcion"
        >
            <template #acciones>
                <CeldaBadge
                    :valor="{
                        valor: auditoria.estado,
                        etiqueta: auditoria.estadoEtiqueta,
                        tono: auditoria.estadoTono,
                        icono: auditoria.estadoIcono,
                    }"
                />
                <Button as-child variant="outline">
                    <Link :href="`/auditorias/${auditoria.id}`">Volver a la auditoría</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <!--
            Una auditoría cerrada se puede leer y no tocar. Se dice aquí, y no
            sólo al intentarlo: un gesto que se ofrece y luego falla se explica
            mucho peor que uno que no se ofrece.
        -->
        <p
            v-if="!auditoria.admiteCambios"
            class="rounded-xl border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
        >
            Esta auditoría está cerrada: su checklist quedó congelada tal como se
            entregó y ya no admite cambios. Para corregir algo hay que reabrirla
            desde su ficha, y eso queda registrado.
        </p>

        <DataTable
            :recurso="recurso"
            :filas="filas"
            :meta="meta"
            @masiva="marcarConformes"
        />
    </AppLayout>
</template>
