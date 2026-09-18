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

const props = defineProps<{
    nombre: string;
    etiqueta: string;
    opciones: Opcion[];
    error?: string | string[] | null;
    ayuda?: string;
    requerido?: boolean;
    deshabilitado?: boolean;
    placeholder?: string;
    /**
     * El valor con el que arranca cuando nadie lo gobierna con `v-model`.
     *
     * **Existía de facto antes que la prop.** Cinco formularios le pasaban
     * `:valor-inicial` —`auditorias/Formulario`, `no-conformidades/Formulario` y
     * `no-conformidades/Ficha`— dando por hecho que funcionaba como en
     * `CampoTexto`, y no era una prop: caía como atributo suelto sobre el `<div>`
     * de `CampoBase` y no hacía nada. El desplegable abría vacío al editar, y en
     * los dos campos obligatorios —el origen de una no conformidad y el sistema de
     * una auditoría— la edición fallaba la validación con un campo que el usuario
     * juraría haber dejado puesto.
     *
     * Se añade en lugar de quitarla de los cinco sitios porque la asimetría era el
     * fallo: un juego de campos donde `CampoTexto` acepta `valorInicial` y
     * `CampoSelect` no es un juego que invita a este error una vez por formulario.
     *
     * **Sólo se lee al montar.** A partir de ahí manda el usuario, como en
     * `CampoTexto`: un `watch` que siguiera la prop pisaría lo que alguien acabara
     * de elegir cada vez que el padre se repintara.
     */
    valorInicial?: string | null;
}>();

const modelo = defineModel<string | undefined>();

/*
 * `v-model` gana si el padre lo gobierna; si no, arranca en `valorInicial`. Sin la
 * comprobación de `undefined`, un formulario con las dos cosas puestas perdería lo
 * que el padre ya tenía en el modelo.
 */
if (modelo.value === undefined && props.valorInicial != null) {
    modelo.value = props.valorInicial;
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
