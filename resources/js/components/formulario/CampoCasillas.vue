<script setup lang="ts">
import MensajeError from '@/components/formulario/MensajeError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
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
 */
const props = defineProps<{
    nombre: string;
    etiqueta: string;
    opciones: Opcion[];
    error?: string | string[] | null;
    ayuda?: string;
    vacio?: string;
}>();

const modelo = defineModel<string[]>({ default: () => [] });

const id = `${props.nombre}-${useId()}`;

const descrito = computed(() =>
    [props.ayuda ? `${id}-ayuda` : null, props.error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined,
);

function alternar(valor: string, marcada: boolean): void {
    modelo.value = marcada
        ? [...modelo.value, valor]
        : modelo.value.filter((elegido) => elegido !== valor);
}
</script>

<template>
    <fieldset class="grid gap-2" :aria-describedby="descrito">
        <legend class="text-sm leading-none font-medium">{{ etiqueta }}</legend>

        <input v-for="valor in modelo" :key="valor" type="hidden" :name="`${nombre}[]`" :value="valor" />
        <input v-if="modelo.length === 0" type="hidden" :name="`${nombre}[]`" value="" />

        <p v-if="opciones.length === 0" class="text-sm text-muted-foreground">
            {{ vacio ?? 'No hay ninguna opción todavía.' }}
        </p>

        <div v-else class="grid gap-2.5">
            <div v-for="opcion in opciones" :key="opcion.valor" class="flex items-center gap-2.5">
                <Checkbox
                    :id="`${id}-${opcion.valor}`"
                    :model-value="modelo.includes(opcion.valor)"
                    @update:model-value="(marcada) => alternar(opcion.valor, marcada === true)"
                />
                <Label :for="`${id}-${opcion.valor}`" class="font-normal">{{ opcion.etiqueta }}</Label>
            </div>
        </div>

        <p v-if="ayuda" :id="`${id}-ayuda`" class="text-sm text-muted-foreground">{{ ayuda }}</p>
        <MensajeError :id="`${id}-error`" :mensaje="error" />
    </fieldset>
</template>
