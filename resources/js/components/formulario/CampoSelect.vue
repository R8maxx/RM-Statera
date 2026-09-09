<script setup lang="ts">
import MensajeError from '@/components/formulario/MensajeError.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { computed, useId } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

const props = defineProps<{
    nombre: string;
    etiqueta: string;
    opciones: Opcion[];
    error?: string | string[] | null;
    ayuda?: string;
    requerido?: boolean;
    deshabilitado?: boolean;
    placeholder?: string;
}>();

const modelo = defineModel<string | undefined>();

const id = `${props.nombre}-${useId()}`;

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

        <!--
            Reka pinta un botón, no un <select>, así que el valor viaja en un
            campo oculto: el componente <Form> de Inertia lee el FormData.
        -->
        <input type="hidden" :name="nombre" :value="modelo ?? ''" />

        <Select v-model="modelo" :disabled="deshabilitado">
            <SelectTrigger :id="id" class="w-full" :aria-invalid="error ? true : undefined" :aria-describedby="descrito">
                <SelectValue :placeholder="placeholder ?? 'Selecciona una opción'" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="opcion in opciones" :key="opcion.valor" :value="opcion.valor">
                    {{ opcion.etiqueta }}
                </SelectItem>
            </SelectContent>
        </Select>

        <p v-if="ayuda" :id="`${id}-ayuda`" class="text-xs text-muted-foreground">{{ ayuda }}</p>

        <MensajeError :id="`${id}-error`" :mensaje="error" />
    </div>
</template>
