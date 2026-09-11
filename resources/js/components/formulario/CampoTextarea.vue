<script setup lang="ts">
import CampoBase from '@/components/formulario/CampoBase.vue';
import { Textarea } from '@/components/ui/textarea';

withDefaults(
    defineProps<{
        nombre: string;
        etiqueta: string;
        error?: string | string[] | null;
        ayuda?: string;
        requerido?: boolean;
        deshabilitado?: boolean;
        placeholder?: string;
        filas?: number;
        valorInicial?: string;
    }>(),
    { filas: 4 },
);

const modelo = defineModel<string | undefined>();
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
        <Textarea
            v-model="modelo"
            :name="nombre"
            :rows="filas"
            :required="requerido"
            :disabled="deshabilitado"
            :placeholder="placeholder"
            :default-value="valorInicial"
            v-bind="atributos"
        />
    </CampoBase>
</template>
