<script setup lang="ts">
import CampoBase from '@/components/formulario/CampoBase.vue';
import { ref } from 'vue';

/**
 * Selector de fichero.
 *
 * Sin `v-model`: el componente `<Form>` de Inertia lee el `FormData`, y ahí es
 * donde el fichero viaja de verdad. Lo único que se guarda en estado es el
 * nombre, para poder decir qué se ha elegido —un `<input type="file">` a secas
 * no lo enseña de forma legible en todos los navegadores—.
 */
defineProps<{
    nombre: string;
    etiqueta: string;
    error?: string | string[] | null;
    ayuda?: string;
    requerido?: boolean;
    /** Tipos admitidos, tal cual los espera el atributo `accept`. */
    acepta?: string;
}>();

const elegido = ref<string | null>(null);

function alElegir(evento: Event): void {
    const entrada = evento.target as HTMLInputElement;

    elegido.value = entrada.files?.[0]?.name ?? null;
}
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
        <input
            type="file"
            :name="nombre"
            :accept="acepta"
            v-bind="atributos"
            class="block w-full cursor-pointer rounded-md border border-input bg-transparent text-sm text-muted-foreground transition-colors file:mr-3 file:cursor-pointer file:border-0 file:border-r file:border-input file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium file:text-foreground hover:file:bg-accent focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            @change="alElegir"
        />

        <p v-if="elegido" class="cifra text-xs text-muted-foreground">{{ elegido }}</p>
    </CampoBase>
</template>
