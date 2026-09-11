<script setup lang="ts">
import CampoBase from '@/components/formulario/CampoBase.vue';
import { Input } from '@/components/ui/input';

// Los atributos no declarados (inputmode, maxlength, pattern…) van al <input>,
// no al contenedor.
defineOptions({ inheritAttrs: false });

/**
 * Campo de texto de una sola línea.
 *
 * Funciona con el componente `<Form>` de Inertia (sin `v-model`: basta `nombre`,
 * porque el formulario lee el `FormData`) y con `useForm` (con `v-model`).
 */
withDefaults(
    defineProps<{
        nombre: string;
        etiqueta: string;
        tipo?: 'text' | 'email' | 'password' | 'tel' | 'url' | 'number' | 'date';
        error?: string | string[] | null;
        ayuda?: string;
        requerido?: boolean;
        deshabilitado?: boolean;
        soloLectura?: boolean;
        placeholder?: string;
        autocomplete?: string;
        autofocus?: boolean;
        valorInicial?: string | number;
    }>(),
    { tipo: 'text' },
);

const modelo = defineModel<string | number | undefined>();
</script>

<template>
    <CampoBase
        :nombre="nombre"
        :etiqueta="etiqueta"
        :error="error"
        :ayuda="ayuda"
        :requerido="requerido"
        #default="{ atributos }"
    >
        <Input
            v-model="modelo"
            :name="nombre"
            :type="tipo"
            :required="requerido"
            :disabled="deshabilitado"
            :readonly="soloLectura"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :autofocus="autofocus"
            :default-value="valorInicial"
            v-bind="{ ...atributos, ...$attrs }"
        />
    </CampoBase>
</template>
