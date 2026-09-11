<script setup lang="ts">
import CampoBase from '@/components/formulario/CampoBase.vue';
import type { Opcion } from '@/lib/formularios';
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';

/**
 * Un valor de una escala corta, elegido de un clic.
 *
 * Para lo que **ordena** —los niveles del Anexo I, la madurez— un desplegable
 * cuesta tres gestos (abrir, leer, elegir) y enseña un valor cada vez. Es el
 * mismo argumento que DESIGN.md §9 da para no pintar la madurez como badge:
 * lo que se pregunta es si esto es más que aquello, y eso lo contesta la
 * posición antes que el texto. Con las cinco dimensiones a la vista se ve de
 * golpe cuál manda, que es justo lo que decide la categoría del sistema.
 *
 * Va sobre `RadioGroupRoot` de Reka —ya es dependencia— por el *roving
 * tabindex*: se entra con el tabulador una vez y se recorre con las flechas. Un
 * grupo de botones a mano obliga a tabular cuatro veces por dimensión.
 *
 * Reka pinta botones, así que el valor viaja en un campo oculto, igual que en
 * `CampoSelect`.
 */
defineProps<{
    nombre: string;
    etiqueta: string;
    opciones: Opcion[];
    error?: string | string[] | null;
    ayuda?: string;
    requerido?: boolean;
    deshabilitado?: boolean;
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
        <input type="hidden" :name="nombre" :value="modelo ?? ''" />

        <!--
            A menos de `sm` se reparte en dos columnas: cuatro etiquetas como
            «No aplica» no caben en una fila a 375 px sin recortarse.
        -->
        <RadioGroupRoot
            v-model="modelo"
            :disabled="deshabilitado"
            orientation="horizontal"
            class="grid grid-cols-2 gap-1 rounded-md border border-input p-1 shadow-xs sm:flex"
            v-bind="atributos"
        >
            <RadioGroupItem
                v-for="opcion in opciones"
                :key="opcion.valor"
                :value="opcion.valor"
                class="h-8 flex-1 cursor-pointer rounded-sm px-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:hover:bg-marca-700"
            >
                {{ opcion.etiqueta }}
            </RadioGroupItem>
        </RadioGroupRoot>
    </CampoBase>
</template>
