<script setup lang="ts">
import CampoTextoEnriquecido from '@/components/formulario/CampoTextoEnriquecido.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { EyeIcon, RotateCcwIcon } from '@lucide/vue';
import { reactive } from 'vue';

export interface SeccionNarrativa {
    clave: string;
    etiqueta: string;
    ayuda: string;
    maximo: number;
    contenido: string;
    contenidoHtml: string;
    origen: string;
    origenEtiqueta: string;
    origenTono: string;
    restablecible: boolean;
    deLaPlantilla: string;
}

const props = defineProps<{
    secciones: SeccionNarrativa[];
    errores: Record<string, string>;
    /** A dónde va el `DELETE` que devuelve una sección a su origen. */
    urlRestablecer: (clave: string) => string;
    etiquetaRestablecer: string;
}>();

function restablecer(seccion: SeccionNarrativa): void {
    router.delete(props.urlRestablecer(seccion.clave), { preserveScroll: true });
}

/**
 * Cuántos caracteres lleva escritos cada hueco.
 *
 * El tope llegaba del servidor desde el principio y no se pintaba en ninguna
 * parte: quien escribía la metodología de una DdA se enteraba de que se pasaba
 * **al guardar**, con el formulario entero rebotado. Se cuenta sobre el Markdown
 * porque es lo que valida el `FormRequest`, no sobre el HTML del editor.
 *
 * Arranca con lo que mandó el servidor: `EditorTexto` emite al cambiar, no al
 * montarse, así que sin esto todos los contadores dirían cero hasta que alguien
 * tocara una tecla.
 */
const escritos = reactive<Record<string, number>>(
    Object.fromEntries(props.secciones.map((seccion) => [seccion.clave, seccion.contenido.length])),
);

/** Qué huecos tienen abierto el texto original de Statera. */
const comparando = reactive<Record<string, boolean>>({});

/**
 * Se avisa cerca del tope y no desde el principio.
 *
 * Un contador en ámbar desde el primer carácter es un contador que se deja de
 * mirar; lo que importa es saber que queda poco antes de perder el trabajo.
 */
function apurado(seccion: SeccionNarrativa): boolean {
    return escritos[seccion.clave] > seccion.maximo * 0.9;
}
</script>

<template>
    <!--
        `data-seccion` cae al `<section>` raíz por herencia de atributos, así que
        marca cada hueco sin tocar `SeccionFormulario`. Es el asidero del índice
        lateral, y tiene que ser **éste** y no el `data-campo` del editor: el
        editor se carga con `defineAsyncComponent`, así que al montar la página
        todavía no existe y el índice se quedaba sin nada que observar.
    -->
    <SeccionFormulario
        v-for="seccion in secciones"
        :key="seccion.clave"
        :data-seccion="seccion.clave"
        :titulo="seccion.etiqueta"
        :ayuda="seccion.ayuda"
        plegable
    >
        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{ valor: seccion.origen, etiqueta: seccion.origenEtiqueta, tono: seccion.origenTono }"
            />

            <!--
                Ver a qué se vuelve ANTES de volver. Restablecer borra lo escrito
                y hasta ahora se pulsaba a ciegas: la única forma de saber qué
                decía el texto de Statera era perder el propio.
            -->
            <Button
                v-if="seccion.restablecible"
                type="button"
                variant="ghost"
                size="sm"
                :aria-expanded="comparando[seccion.clave] === true"
                @click="comparando[seccion.clave] = !comparando[seccion.clave]"
            >
                <EyeIcon class="size-4" />
                {{ comparando[seccion.clave] ? 'Ocultar el texto de Statera' : 'Ver el texto de Statera' }}
            </Button>

            <!--
                Sólo cuando hay algo a lo que volver. Y en `ghost`, no en
                destructivo: retocar un texto no es un error del que haya que
                avisar en rojo.
            -->
            <Button
                v-if="seccion.restablecible"
                type="button"
                variant="ghost"
                size="sm"
                @click="restablecer(seccion)"
            >
                <RotateCcwIcon class="size-4" />
                {{ etiquetaRestablecer }}
            </Button>
        </div>

        <div
            v-if="comparando[seccion.clave]"
            class="rounded-xl border border-border bg-muted/40 p-3 text-sm whitespace-pre-line text-muted-foreground"
        >
            <p class="mb-1.5 text-xs font-medium text-foreground">Lo que trae Statera</p>
            <template v-if="seccion.deLaPlantilla !== ''">{{ seccion.deLaPlantilla }}</template>
            <template v-else><span class="italic">Statera deja este hueco vacío a propósito.</span></template>
        </div>

        <CampoTextoEnriquecido
            :nombre="seccion.clave"
            :etiqueta="seccion.etiqueta"
            :html="seccion.contenidoHtml"
            :error="errores[seccion.clave]"
            @cambio="escritos[seccion.clave] = $event.length"
        />

        <p
            class="-mt-3 text-right text-xs tabular-nums"
            :class="apurado(seccion) ? 'text-estado-en-progreso' : 'text-muted-foreground'"
        >
            {{ escritos[seccion.clave].toLocaleString('es-ES') }} /
            {{ seccion.maximo.toLocaleString('es-ES') }}
        </p>
    </SeccionFormulario>
</template>
