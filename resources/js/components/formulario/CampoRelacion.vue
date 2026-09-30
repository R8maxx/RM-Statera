<script setup lang="ts">
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import { Button } from '@/components/ui/button';
import { SIN_VALOR, type Opcion } from '@/lib/formularios';
import { PlusIcon, XIcon } from '@lucide/vue';
import { nextTick, ref, useTemplateRef } from 'vue';

/**
 * Un vínculo opcional que casi nunca se rellena: el riesgo o el incidente de una
 * vulnerabilidad.
 *
 * Un desplegable con «Ninguno» por cada uno ocupaba una fila entera para decir
 * que no había nada. Aquí, sin valor, es un botón «+ Riesgo»; al pulsarlo sale
 * el desplegable ya enfocado, con una × para volver atrás. Van varios en una
 * fila (`flex flex-wrap`): el abierto ocupa la suya entera.
 *
 * **Plegado sigue enviando el campo**, con el centinela de «ninguno» que
 * `NormalizaSeleccionVacia` traduce a nulo. Sin él, quitar el vínculo en una
 * edición no enviaría nada y el servidor lo dejaría como estaba.
 *
 * Con un error del servidor se abre solo, y el botón lleva `data-campo`: el
 * resumen de errores de `FormularioRecurso` tiene que poder llegar a él.
 */
const props = defineProps<{
    nombre: string;
    /** Lo que se añade: «Riesgo», «Incidente». Es la etiqueta del botón y del desplegable. */
    etiqueta: string;
    opciones: Opcion[];
    error?: string | string[] | null;
    ayuda?: string;
    valorInicial?: string | null;
}>();

const modelo = ref<string | undefined>(props.valorInicial && props.valorInicial !== SIN_VALOR ? props.valorInicial : undefined);
const abierto = ref(modelo.value !== undefined || Boolean(props.error));
const raiz = useTemplateRef<HTMLElement>('raiz');

async function abrir(): Promise<void> {
    abierto.value = true;
    await nextTick();
    raiz.value?.querySelector<HTMLElement>(`[data-campo="${CSS.escape(props.nombre)}"]`)?.focus();
}

async function quitar(): Promise<void> {
    modelo.value = undefined;
    abierto.value = false;
    await nextTick();
    raiz.value?.querySelector<HTMLElement>('button')?.focus();
}
</script>

<template>
    <div ref="raiz" :class="abierto ? 'basis-full' : undefined">
        <template v-if="!abierto">
            <input type="hidden" :name="nombre" :value="SIN_VALOR" />
            <Button type="button" variant="outline" class="border-dashed shadow-none" :data-campo="nombre" @click="abrir">
                <PlusIcon aria-hidden="true" />
                {{ etiqueta }}
            </Button>
        </template>

        <div v-else class="flex items-start gap-2">
            <div class="min-w-0 flex-1">
                <CampoSelect
                    v-model="modelo"
                    :nombre="nombre"
                    :etiqueta="etiqueta"
                    :opciones="opciones"
                    :error="error"
                    :ayuda="ayuda"
                    placeholder="Elige uno"
                />
            </div>
            <!-- Alineada con el control: la etiqueta de encima mide 18 px y el
                 hueco hasta el control 8 (`CampoBase`). Con `items-end` bajaba
                 hasta la línea de ayuda cuando la había. -->
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="mt-[1.625rem]"
                :aria-label="`Quitar ${etiqueta.toLowerCase()}`"
                @click="quitar"
            >
                <XIcon aria-hidden="true" />
            </Button>
        </div>
    </div>
</template>
