<script setup lang="ts">
import CampoTextoEnriquecido from '@/components/formulario/CampoTextoEnriquecido.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { RotateCcwIcon } from '@lucide/vue';

export interface SeccionNarrativa {
    clave: string;
    etiqueta: string;
    ayuda: string;
    maximo: number;
    contenido: string;
    contenidoHtml: string;
    origen: string;
    origenEtiqueta: string;
    origenTono: string;
    restablecible: boolean;
    deLaPlantilla: string;
}

const props = defineProps<{
    secciones: SeccionNarrativa[];
    errores: Record<string, string>;
    /** A dónde va el `DELETE` que devuelve una sección a su origen. */
    urlRestablecer: (clave: string) => string;
    etiquetaRestablecer: string;
}>();

function restablecer(seccion: SeccionNarrativa): void {
    router.delete(props.urlRestablecer(seccion.clave), { preserveScroll: true });
}
</script>

<template>
    <SeccionFormulario
        v-for="seccion in secciones"
        :key="seccion.clave"
        :titulo="seccion.etiqueta"
        :ayuda="seccion.ayuda"
        plegable
    >
        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{ valor: seccion.origen, etiqueta: seccion.origenEtiqueta, tono: seccion.origenTono }"
            />

            <!--
                Sólo cuando hay algo a lo que volver. Y en `ghost`, no en
                destructivo: retocar un texto no es un error del que haya que
                avisar en rojo.
            -->
            <Button
                v-if="seccion.restablecible"
                type="button"
                variant="ghost"
                size="sm"
                @click="restablecer(seccion)"
            >
                <RotateCcwIcon class="size-4" />
                {{ etiquetaRestablecer }}
            </Button>
        </div>

        <CampoTextoEnriquecido
            :nombre="seccion.clave"
            :etiqueta="seccion.etiqueta"
            :html="seccion.contenidoHtml"
            :error="errores[seccion.clave]"
        />
    </SeccionFormulario>
</template>
