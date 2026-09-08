<script setup lang="ts">
import MensajeError from '@/components/formulario/MensajeError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useId } from 'vue';

// Los atributos no declarados (inputmode, maxlength, pattern…) van al <input>,
// no al contenedor.
defineOptions({ inheritAttrs: false });

/**
 * Campo de texto de una sola línea.
 *
 * Funciona con el componente `<Form>` de Inertia (sin `v-model`: basta `nombre`,
 * porque el formulario lee el `FormData`) y con `useForm` (con `v-model`).
 */
const props = withDefaults(
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

const id = `${props.nombre}-${useId()}`;
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">
            {{ etiqueta }}
            <span v-if="requerido" class="text-destructive" aria-hidden="true">*</span>
        </Label>

        <Input
            :id="id"
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
            :aria-invalid="error ? true : undefined"
            :aria-describedby="ayuda ? `${id}-ayuda` : undefined"
            v-bind="$attrs"
        />

        <p v-if="ayuda" :id="`${id}-ayuda`" class="text-xs text-muted-foreground">{{ ayuda }}</p>

        <MensajeError :mensaje="error" />
    </div>
</template>
