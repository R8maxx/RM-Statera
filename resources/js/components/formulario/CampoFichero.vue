<script setup lang="ts">
import MensajeError from '@/components/formulario/MensajeError.vue';
import { Label } from '@/components/ui/label';
import { computed, ref, useId } from 'vue';

/**
 * Selector de fichero.
 *
 * Sin `v-model`: el componente `<Form>` de Inertia lee el `FormData`, y ahí es
 * donde el fichero viaja de verdad. Lo único que se guarda en estado es el
 * nombre, para poder decir qué se ha elegido —un `<input type="file">` a secas
 * no lo enseña de forma legible en todos los navegadores—.
 */
const props = defineProps<{
    nombre: string;
    etiqueta: string;
    error?: string | string[] | null;
    ayuda?: string;
    requerido?: boolean;
    /** Tipos admitidos, tal cual los espera el atributo `accept`. */
    acepta?: string;
}>();

const id = `${props.nombre}-${useId()}`;
const elegido = ref<string | null>(null);

const descrito = computed(() =>
    [props.ayuda ? `${id}-ayuda` : null, props.error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined,
);

function alElegir(evento: Event): void {
    const entrada = evento.target as HTMLInputElement;

    elegido.value = entrada.files?.[0]?.name ?? null;
}
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">
            {{ etiqueta }}
            <span v-if="requerido" class="text-destructive" aria-hidden="true">*</span>
        </Label>

        <input
            :id="id"
            type="file"
            :name="nombre"
            :accept="acepta"
            :aria-invalid="error ? true : undefined"
            :aria-describedby="descrito"
            class="block w-full cursor-pointer rounded-md border border-input bg-transparent text-sm text-muted-foreground transition-colors file:mr-3 file:cursor-pointer file:border-0 file:border-r file:border-input file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium file:text-foreground hover:file:bg-accent focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            @change="alElegir"
        />

        <p v-if="elegido" class="cifra text-xs text-muted-foreground">{{ elegido }}</p>
        <p v-if="ayuda" :id="`${id}-ayuda`" class="text-xs text-muted-foreground">{{ ayuda }}</p>

        <MensajeError :id="`${id}-error`" :mensaje="error" />
    </div>
</template>
