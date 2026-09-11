<script setup lang="ts">
import MensajeError from '@/components/formulario/MensajeError.vue';
import { Label } from '@/components/ui/label';
import { registrarCampoObligatorio } from '@/composables/useCamposObligatorios';
import { computed, useId } from 'vue';

/**
 * La envoltura común de un campo: etiqueta, marca de obligatorio, ayuda y error.
 *
 * Existía cinco veces copiada —texto, textarea, select, contraseña y fichero— y
 * ésa es exactamente la razón de que el asterisco siguiera pintado del mismo
 * rojo que los errores: cambiarlo obligaba a acertar en cinco sitios.
 *
 * **El marcador de obligatorio no es `destructive`.** Un campo que falta y un
 * campo que ha fallado no son lo mismo, y con el mismo rojo un formulario recién
 * abierto parece un formulario lleno de errores. El asterisco va en el teal de
 * marca, el filete de la izquierda agrupa lo exigible de un vistazo, y como el
 * color no puede ser el único portador del estado (DESIGN.md §11) el control
 * lleva `aria-required` y la etiqueta un «(obligatorio)» para lector de
 * pantalla.
 *
 * `data-campo` es el contrato con el resumen de errores de `FormularioRecurso`:
 * apunta al elemento **enfocable**, no al `name`, que en la mitad de los campos
 * está en un `<input type="hidden">` que no se puede ni enfocar ni desplazar.
 */
const props = defineProps<{
    nombre: string;
    etiqueta: string;
    error?: string | string[] | null;
    ayuda?: string;
    requerido?: boolean;
    /**
     * Ids adicionales que encadenar en `aria-describedby`, para el aviso que un
     * campo concreto pueda añadir —el bloqueo de mayúsculas de la contraseña—.
     */
    descritoExtra?: string[];
}>();

const id = `${props.nombre}-${useId()}`;

/* El control apunta a su ayuda y a su error; si no hay ninguno, no apunta nada. */
const descrito = computed(
    () =>
        [props.ayuda ? `${id}-ayuda` : null, ...(props.descritoExtra ?? []), props.error ? `${id}-error` : null]
            .filter(Boolean)
            .join(' ') || undefined,
);

/** Todo lo que el control tiene que recibir, en un solo `v-bind`. */
const atributos = computed(() => ({
    id,
    'aria-describedby': descrito.value,
    'aria-invalid': props.error ? true : undefined,
    'aria-required': props.requerido ? true : undefined,
    'data-campo': props.nombre,
}));

registrarCampoObligatorio(props.nombre, () => props.requerido === true);
</script>

<template>
    <div
        class="grid gap-2"
        :class="requerido ? 'border-l-2 border-primary pl-3' : undefined"
    >
        <Label :for="id">
            {{ etiqueta }}
            <span v-if="requerido" class="text-primary" aria-hidden="true">*</span>
            <span v-if="requerido" class="sr-only">(obligatorio)</span>
        </Label>

        <slot :atributos="atributos" :id="id" :descrito="descrito" />

        <p v-if="ayuda" :id="`${id}-ayuda`" class="text-xs text-muted-foreground">{{ ayuda }}</p>

        <MensajeError :id="`${id}-error`" :mensaje="error" />
    </div>
</template>
