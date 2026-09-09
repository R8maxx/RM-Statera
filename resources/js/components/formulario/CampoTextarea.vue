<script setup lang="ts">
import MensajeError from '@/components/formulario/MensajeError.vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { computed, useId } from 'vue';

const props = withDefaults(
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

const id = `${props.nombre}-${useId()}`;

/* El control apunta a su ayuda y a su error; si no hay ninguno, no apunta nada. */
const descrito = computed(() =>
    [props.ayuda ? `${id}-ayuda` : null, props.error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined,
);
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">
            {{ etiqueta }}
            <span v-if="requerido" class="text-destructive" aria-hidden="true">*</span>
        </Label>

        <Textarea
            :id="id"
            v-model="modelo"
            :name="nombre"
            :rows="filas"
            :required="requerido"
            :disabled="deshabilitado"
            :placeholder="placeholder"
            :default-value="valorInicial"
            :aria-invalid="error ? true : undefined"
            :aria-describedby="descrito"
        />

        <p v-if="ayuda" :id="`${id}-ayuda`" class="text-xs text-muted-foreground">{{ ayuda }}</p>

        <MensajeError :id="`${id}-error`" :mensaje="error" />
    </div>
</template>
