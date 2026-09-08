<script setup lang="ts">
import MensajeError from '@/components/formulario/MensajeError.vue';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useId } from 'vue';

const props = defineProps<{
    nombre: string;
    etiqueta: string;
    error?: string | string[] | null;
    ayuda?: string;
    deshabilitado?: boolean;
}>();

const modelo = defineModel<boolean>({ default: false });

const id = `${props.nombre}-${useId()}`;
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center gap-3">
            <input type="hidden" :name="nombre" :value="modelo ? '1' : '0'" />
            <Switch :id="id" v-model="modelo" :disabled="deshabilitado" />
            <Label :for="id" class="font-normal">{{ etiqueta }}</Label>
        </div>

        <p v-if="ayuda" class="text-xs text-muted-foreground">{{ ayuda }}</p>

        <MensajeError :mensaje="error" />
    </div>
</template>
