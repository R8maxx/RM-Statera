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
        /**
         * `datetime-local` entra con los incidentes (§ 4.10): ahí la hora
         * importa —las 72 h del artículo 33.1 del RGPD se cuentan desde la
         * detección—, y una fecha a secas dejaría el reloj con un margen de un
         * día. En el resto del producto se sigue usando `date`.
         */
        tipo?: 'text' | 'email' | 'password' | 'tel' | 'url' | 'number' | 'date' | 'datetime-local';
        error?: string | string[] | null;
        ayuda?: string;
        requerido?: boolean;
        deshabilitado?: boolean;
        soloLectura?: boolean;
        placeholder?: string;
        autocomplete?: string;
        autofocus?: boolean;
        valorInicial?: string | number;
        /**
         * Para filas repetidas —los escalones de una escala—, donde el título de
         * la sección ya dice qué son y repetirlo en cada fila es ruido. El
         * `<label>` sigue existiendo y asociado: quitarlo dejaría el control sin
         * nombre accesible.
         */
        etiquetaOculta?: boolean;
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
        :etiqueta-oculta="etiquetaOculta"
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
