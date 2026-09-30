<script setup lang="ts">
import MarcaProcedencia from '@/components/formulario/MarcaProcedencia.vue';
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
    /**
     * Oculta la etiqueta a la vista, no al lector de pantalla.
     *
     * Para cuando el título ya lo pone su `SeccionFormulario` y repetirlo justo
     * debajo sólo añade ruido. El `<label>` sigue existiendo y sigue asociado:
     * quitarlo dejaría el control sin nombre accesible.
     */
    etiquetaOculta?: boolean;
    /**
     * De dónde salió el valor, cuando no lo escribió nadie: «NVD» en una
     * vulnerabilidad rellenada con «Traer de NVD». Va en un chip teal junto a
     * la etiqueta, y **sólo mientras el valor sea el traído**: con
     * `procedenciaEditada` pasa a un chip neutro «editado», que es lo que un
     * revisor quiere saber —esto ya no es lo que dice la fuente—. Sustituye a la
     * lista «Rellenado: …» del aviso, que había que cruzar a mano con los campos.
     */
    procedencia?: string | null;
    procedenciaEditada?: boolean;
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
    <!--
        `content-start` no es adorno: sin él, dos campos lado a lado en una
        rejilla de dos columnas **no alinean sus inputs** cuando uno lleva ayuda
        y el otro no. La celda corta se estira hasta la altura de la larga
        —`align-items: stretch` es el valor por defecto— y esta rejilla interna
        reparte el hueco sobrante entre sus filas, así que el input del campo sin
        ayuda baja. Medido: 28 px de desfase entre «CIF» y «Sector».

        Es la misma corrección que `SeccionFormulario` ya lleva en su contenedor.
    -->
    <div
        class="grid content-start gap-2"
        :class="requerido ? 'border-l-2 border-primary pl-3' : undefined"
    >
        <Label :for="id" :class="etiquetaOculta ? 'sr-only' : undefined">
            {{ etiqueta }}
            <span v-if="requerido" class="text-primary" aria-hidden="true">*</span>
            <span v-if="requerido" class="sr-only">(obligatorio)</span>
            <MarcaProcedencia v-if="procedencia" :fuente="procedencia" :editado="procedenciaEditada" />
        </Label>

        <slot :atributos="atributos" :id="id" :descrito="descrito" />

        <p v-if="ayuda" :id="`${id}-ayuda`" class="text-xs text-muted-foreground">{{ ayuda }}</p>

        <MensajeError :id="`${id}-error`" :mensaje="error" />
    </div>
</template>
