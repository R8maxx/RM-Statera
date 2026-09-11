<script setup lang="ts">
import CampoBase from '@/components/formulario/CampoBase.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Opcion } from '@/lib/formularios';

defineProps<{
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
        <!--
            Reka pinta un botón, no un <select>, así que el valor viaja en un
            campo oculto: el componente <Form> de Inertia lee el FormData. Por
            eso `atributos` va al disparador y no aquí: un campo oculto no se
            puede enfocar, y el resumen de errores tiene que poder llevar a él.
        -->
        <input type="hidden" :name="nombre" :value="modelo ?? ''" />

        <Select v-model="modelo" :disabled="deshabilitado">
            <SelectTrigger class="w-full" v-bind="atributos">
                <SelectValue :placeholder="placeholder ?? 'Selecciona una opción'" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="opcion in opciones" :key="opcion.valor" :value="opcion.valor">
                    {{ opcion.etiqueta }}
                </SelectItem>
            </SelectContent>
        </Select>
    </CampoBase>
</template>
