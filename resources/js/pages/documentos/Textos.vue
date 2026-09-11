<script setup lang="ts">
import SeccionesNarrativa, { type SeccionNarrativa } from '@/components/documento/SeccionesNarrativa.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    documento: { id: number; codigo: string; titulo: string; tipoEtiqueta: string };
    secciones: SeccionNarrativa[];
}>();
</script>

<template>
    <AppLayout :titulo="`Textos de ${documento.codigo}`">
        <FormularioRecurso
            titulo="Textos del documento"
            descripcion="Lo que la organización redacta: la introducción, cómo se lee cada tabla y lo que cierra el documento. Las tablas, las cifras y la derivación de la categoría se siguen calculando desde el registro y no se editan aquí."
            :action="`/documentos/${documento.id}/textos`"
            method="put"
            etiqueta-enviar="Guardar textos"
            :url-cancelar="`/documentos/${documento.id}`"
            #default="{ errors }"
        >
            <SeccionesNarrativa
                :secciones="secciones"
                :errores="errors"
                :url-restablecer="(clave) => `/documentos/${props.documento.id}/textos/${clave}`"
                etiqueta-restablecer="Restablecer a la plantilla"
            />
        </FormularioRecurso>
    </AppLayout>
</template>
