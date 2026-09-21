<script setup lang="ts">
import IndiceSecciones from '@/components/documento/IndiceSecciones.vue';
import SeccionesNarrativa, { type SeccionNarrativa } from '@/components/documento/SeccionesNarrativa.vue';
import Aviso from '@/components/Aviso.vue';
import FormularioRecurso from '@/components/formulario/FormularioRecurso.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    tipo: { valor: string; etiqueta: string; corta: string };
    documentosAfectados: number;
    secciones: SeccionNarrativa[];
}>();
</script>

<template>
    <AppLayout :titulo="`Plantilla · ${tipo.corta}`">
        <FormularioRecurso
            :titulo="`Textos base · ${tipo.etiqueta}`"
            descripcion="El punto de partida de los documentos de este tipo que se creen a partir de ahora."
            :action="`/plantillas-documento/${tipo.valor}`"
            method="put"
            etiqueta-enviar="Guardar plantilla"
            ancho
            url-cancelar="/plantillas-documento"
            #default="{ errors }"
        >
            <!--
                Sin este aviso, cualquiera daría por hecho que acaba de cambiar
                su SoA. No la ha cambiado: un documento conserva los textos con
                los que se creó, porque lo que dice un documento es un hecho del
                documento y no el resultado de un join que cambia bajo los pies.
            -->
            <Aviso v-if="documentosAfectados > 0" tono="info">
                Hay {{ documentosAfectados }}
                {{ documentosAfectados === 1 ? 'documento' : 'documentos' }} de este tipo ya creados.
                Conservan los textos con los que se crearon: esto sólo afecta a los que se creen a
                partir de ahora.
            </Aviso>

            <!--
                El índice al lado y no encima: una plantilla del ENS tiene once
                huecos y la única forma de llegar al último era rodar. Dentro del
                ancho del formulario, así que los editores se estrechan un poco;
                a cambio se ve dónde estás. Desaparece por debajo de `lg`, donde
                no cabe y donde la página ya se recorre de un tirón.
            -->
            <div class="flex gap-6">
                <IndiceSecciones
                    :entradas="secciones.map((seccion) => ({ clave: seccion.clave, etiqueta: seccion.etiqueta }))"
                    titulo="Huecos"
                />

                <div class="min-w-0 flex-1 space-y-6">
                    <SeccionesNarrativa
                        :secciones="secciones"
                        :errores="errors"
                        :url-restablecer="(clave) => `/plantillas-documento/${props.tipo.valor}/${clave}`"
                        etiqueta-restablecer="Restablecer al texto de Statera"
                    />
                </div>
            </div>
        </FormularioRecurso>
    </AppLayout>
</template>
