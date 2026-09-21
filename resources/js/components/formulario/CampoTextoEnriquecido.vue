<script setup lang="ts">
import CampoBase from '@/components/formulario/CampoBase.vue';
import { defineAsyncComponent } from 'vue';

/**
 * Un hueco de texto de un documento, con formato.
 *
 * Sobre `CampoBase`, como el resto: hereda etiqueta, ayuda, `aria-describedby`,
 * el filete teal de obligatorio y el registro en `useCamposObligatorios`. Lo
 * único que cambia es el control.
 *
 * **El editor se carga aparte.** ProseMirror ronda los cien kilobytes y sólo lo
 * necesitan dos pantallas de toda la aplicación; con `defineAsyncComponent` sale
 * en su propio chunk y no lo paga quien nunca edita un texto. Es el mismo
 * criterio que ya se aplicó a `@number-flow/vue`.
 */
const EditorTexto = defineAsyncComponent(() => import('@/components/formulario/EditorTexto.vue'));

defineProps<{
    nombre: string;
    etiqueta: string;
    html: string;
    error?: string | string[] | null;
    ayuda?: string;
}>();

/**
 * Lo que se va a enviar, en Markdown, con cada pulsación.
 *
 * `EditorTexto` ya lo emitía y esto no lo reenviaba, así que un contador de
 * caracteres tenía que adivinarlo del HTML. Lo que valida el `FormRequest` es el
 * Markdown, que es lo que sale por aquí.
 */
defineEmits<{ cambio: [string] }>();
</script>

<template>
    <CampoBase
        :nombre="nombre"
        :etiqueta="etiqueta"
        :error="error"
        :ayuda="ayuda"
        etiqueta-oculta
        #default="{ atributos }"
    >
        <EditorTexto :nombre="nombre" :html="html" :atributos="atributos" @cambio="$emit('cambio', $event)">
            <template #fallback>
                <!-- Mientras llega el chunk: la caja, para que no salte el diseño. -->
                <div class="min-h-32 rounded-md border border-input" aria-hidden="true"></div>
            </template>
        </EditorTexto>
    </CampoBase>
</template>
