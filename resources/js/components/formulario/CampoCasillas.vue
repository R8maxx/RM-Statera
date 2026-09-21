<script setup lang="ts">
import MensajeError from '@/components/formulario/MensajeError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { registrarCampoObligatorio } from '@/composables/useCamposObligatorios';
import type { Opcion } from '@/lib/formularios';
import { computed, useId } from 'vue';

/**
 * Un grupo de casillas que viaja como array (`nombre[]`).
 *
 * Existe porque el alcance de un activo es N:M —el mismo servidor entra en el
 * SGSI de ISO y en el sistema del ENS— y un desplegable de selección única no
 * puede expresarlo. Con tres o cuatro sistemas, las casillas enseñan a la vez
 * qué hay y qué está marcado, que es más de lo que da un multiselect plegado.
 *
 * Reka pinta un botón y no un `<input type="checkbox">`, así que el valor viaja
 * en campos ocultos, igual que en `CampoSelect`: el `<Form>` de Inertia lee el
 * `FormData` y no un `v-model`.
 *
 * Con la lista vacía se manda un `nombre[]` vacío a propósito: sin él, desmarcar
 * la última casilla no envía nada y el servidor no distingue «ninguno» de «no
 * venía el campo», que es como se pierde una desvinculación.
 *
 * No va sobre `CampoBase` porque su forma es otra —`fieldset` y `legend`, no
 * `label` de un control—, pero comparte sus tokens y su marca de obligatorio.
 *
 * **`descripcion` por opción, y no un slot ni `v-html`.** Desde el § 4.10 hay
 * casillas cuya etiqueta no se sostiene sola: marcar «AEPD» pone en marcha un
 * plazo legal y eso hay que decirlo al lado. Un slot dejaría entrar marcado
 * arbitrario en un formulario, que es justo lo que este producto evita; el
 * reparto etiqueta + ayuda es el que `DESIGN.md` §9 ya prescribe para un campo.
 */
export interface OpcionCasilla extends Opcion {
    /** Una línea bajo la etiqueta. No es la ayuda del grupo, es la de la opción. */
    descripcion?: string;
}

const props = defineProps<{
    nombre: string;
    etiqueta: string;
    opciones: OpcionCasilla[];
    error?: string | string[] | null;
    ayuda?: string;
    requerido?: boolean;
    vacio?: string;
    /**
     * Encierra las opciones en una caja con desplazamiento.
     *
     * Para las listas que no las decide el dominio sino el inventario: cinco
     * dimensiones caben siempre, doscientos activos no.
     */
    desplazable?: boolean;
}>();

const modelo = defineModel<string[]>({ default: () => [] });

const id = `${props.nombre}-${useId()}`;

const descrito = computed(
    () =>
        [props.ayuda ? `${id}-ayuda` : null, props.error ? `${id}-error` : null].filter(Boolean).join(' ') ||
        undefined,
);

registrarCampoObligatorio(props.nombre, () => props.requerido === true);

function alternar(valor: string, marcada: boolean): void {
    modelo.value = marcada
        ? [...modelo.value, valor]
        : modelo.value.filter((elegido) => elegido !== valor);
}
</script>

<template>
    <fieldset
        class="grid gap-2"
        :aria-describedby="descrito"
        :aria-required="requerido ? true : undefined"
        :aria-invalid="error ? true : undefined"
        :class="requerido ? 'border-l-2 border-primary pl-3' : undefined"
    >
        <legend class="flex items-center gap-2 text-sm leading-none font-medium">
            {{ etiqueta }}
            <span v-if="requerido" class="text-primary" aria-hidden="true">*</span>
            <span v-if="requerido" class="sr-only">(obligatorio)</span>
        </legend>

        <input v-for="valor in modelo" :key="valor" type="hidden" :name="`${nombre}[]`" :value="valor" />
        <input v-if="modelo.length === 0" type="hidden" :name="`${nombre}[]`" value="" />

        <p v-if="opciones.length === 0" class="text-sm text-muted-foreground">
            {{ vacio ?? 'No hay ninguna opción todavía.' }}
        </p>

        <div
            v-else
            class="grid gap-2.5"
            :class="desplazable ? 'max-h-56 overflow-y-auto rounded-md p-3 ring-1 ring-foreground/10' : undefined"
        >
            <div v-for="(opcion, indice) in opciones" :key="opcion.valor" class="flex items-start gap-2.5">
                <!--
                    `data-campo` en la primera casilla: es el contrato con el
                    resumen de errores, que necesita un elemento enfocable y no
                    el `name`, que aquí es `nombre[]` y además está oculto.
                -->
                <Checkbox
                    :id="`${id}-${opcion.valor}`"
                    :data-campo="indice === 0 ? nombre : undefined"
                    class="mt-0.5"
                    :model-value="modelo.includes(opcion.valor)"
                    @update:model-value="(marcada) => alternar(opcion.valor, marcada === true)"
                />
                <div class="grid gap-0.5">
                    <Label :for="`${id}-${opcion.valor}`" class="font-normal">{{ opcion.etiqueta }}</Label>
                    <p v-if="opcion.descripcion" class="text-xs text-muted-foreground">
                        {{ opcion.descripcion }}
                    </p>
                </div>
            </div>
        </div>

        <p v-if="ayuda" :id="`${id}-ayuda`" class="text-xs text-muted-foreground">{{ ayuda }}</p>
        <MensajeError :id="`${id}-error`" :mensaje="error" />
    </fieldset>
</template>
