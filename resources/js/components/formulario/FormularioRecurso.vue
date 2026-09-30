<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import BarraAcciones from '@/components/formulario/BarraAcciones.vue';
import IndiceFormulario from '@/components/formulario/IndiceFormulario.vue';
import { Button } from '@/components/ui/button';
import { proveerObligatorios } from '@/composables/useCamposObligatorios';
import { proveerIndice } from '@/composables/useIndiceFormulario';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { Form } from '@inertiajs/vue3';
import type { Method } from '@inertiajs/core';
import { AlertCircleIcon } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { motion } from 'motion-v';
import { computed, onMounted, ref, useSlots } from 'vue';

/**
 * La envoltura común de los formularios de un recurso.
 *
 * Va sobre el componente `<Form>` de Inertia v3, así que los campos no
 * necesitan `v-model`: el formulario lee el `FormData` y los errores llegan tal
 * cual los devuelve el `FormRequest`, que es la única fuente de verdad de la
 * validación.
 *
 * El pie lo pone `BarraAcciones`, que comparte con las pantallas de este tipo
 * que no son un `<Form>`.
 *
 * **El carril.** Con tres secciones o más, o con un `#resumen`, las acciones
 * dejan el pie y suben a una columna de 20 rem a la derecha, pegajosa, con el
 * índice de secciones (lo que falta en cada una) y, si el formulario lo trae,
 * «Lo que sale de aquí»: lo que el sistema deriva de lo que se escribe —la
 * severidad y el plazo de una vulnerabilidad—. Un formulario de una o dos
 * secciones no tiene nada que recorrer y se queda con el pie.
 *
 * Por debajo de `lg` no hay sitio para dos columnas: el índice desaparece, el
 * resumen baja al final de los campos y las acciones vuelven al pie. Una sola
 * `BarraAcciones` en el DOM a la vez —es la que registra el `beforeunload`—,
 * de ahí `useMediaQuery` y no dos copias escondidas con clases.
 */
const props = withDefaults(
    defineProps<{
        titulo: string;
        descripcion?: string;
        action: string;
        method?: Method;
        etiquetaEnviar?: string;
        urlCancelar: string;
        /**
         * Una columna más ancha, para los formularios que además del campo
         * llevan algo al lado —hoy sólo el índice de la plantilla de un
         * documento—. Con un índice delante, y con el carril si lo hay, la caja
         * de texto se quedaba sin sitio para escribir.
         */
        ancho?: boolean;
        /**
         * El formulario es una sección de otra pantalla y no la pantalla entera.
         * Su título baja a `<h2>` y pierde el filete: `riesgos/Metodologia`
         * ponía su propia cabecera encima, y salían dos `<h1>` y dos filetes en
         * la misma vista, contra «uno por pantalla» de DESIGN.md §6.
         */
        seccion?: boolean;
        /** El título del bloque `#resumen`. */
        tituloResumen?: string;
    }>(),
    { method: 'post', etiquetaEnviar: 'Guardar', ancho: false, seccion: false, tituloResumen: 'Lo que sale de aquí' },
);

const { variantesEntrada } = useMovimientoReducido();
const slots = useSlots();
const { secciones } = proveerIndice();
const pantallaAncha = useMediaQuery('(min-width: 1024px)');

const conCarril = computed(() => !props.seccion && (secciones.value.length >= 3 || slots.resumen !== undefined));
const carrilVisible = computed(() => conCarril.value && pantallaAncha.value);

/*
 * El `<form>` se alcanza desde dentro con `closest` y no por el `$el` del
 * componente de Inertia: es el elemento lo que hace falta —para leer su
 * `FormData`— y así no se depende de qué expone `<Form>` por dentro.
 */
const ancla = ref<HTMLElement | null>(null);
const formulario = ref<HTMLFormElement | null>(null);

onMounted(() => {
    formulario.value = ancla.value?.closest('form') ?? null;
});

const { pendientes, hayObligatorios, sucio } = proveerObligatorios(formulario);

const leyendaObligatorios = computed(() =>
    pendientes.value === 0
        ? 'Los campos marcados con * son obligatorios.'
        : `Los campos marcados con * son obligatorios. Faltan ${pendientes.value}.`,
);

/**
 * Lleva el foco al campo que falla.
 *
 * El resumen anterior decía cuántos errores había y ahí se acababa: en un
 * formulario largo, saber que hay tres no ayuda a encontrarlos.
 *
 * Se busca por `data-campo`, que cada campo pone en su elemento **enfocable**.
 * Buscar por `name` no valía: en un select, unas casillas o una escala, el
 * `name` está en un `<input type="hidden">` que no se puede enfocar ni
 * desplazar, así que el salto no hacía nada justo en la mayoría de los campos
 * obligatorios. El `name` se conserva como respaldo.
 */
function irAlCampo(nombre: string): void {
    const escapado = CSS.escape(nombre);
    const campo =
        document.querySelector<HTMLElement>(`[data-campo="${escapado}"]`) ??
        document.querySelector<HTMLElement>(`[name="${escapado}"]`);

    if (campo === null) {
        return;
    }

    /*
     * Si el campo está en una sección plegada hay que abrirla primero: dentro de
     * un `display:none` no se puede enfocar ni desplazar nada, que es la misma
     * trampa que tenía buscar por `name` y caer en un campo oculto.
     */
    const disparador = campo.closest('[data-plegable]')?.querySelector<HTMLButtonElement>('[data-plegar]');

    if (disparador?.getAttribute('aria-expanded') === 'false') {
        disparador.click();
    }

    /* Tras abrirla, al fotograma siguiente: antes, la posición todavía es la vieja. */
    requestAnimationFrame(() => {
        campo.scrollIntoView({ block: 'center', behavior: 'smooth' });
        campo.focus({ preventScroll: true });
    });
}
</script>

<template>
    <Form
        :action="action"
        :method="method"
        #default="{ errors, processing, hasErrors }"
        class="mx-auto w-full"
        :class="ancho ? 'max-w-7xl' : conCarril || seccion ? 'max-w-6xl' : 'max-w-4xl'"
    >
        <div ref="ancla" class="contents" />

        <!-- Con `seccion` se queda en `max-w-6xl`, el ancho de la pantalla que lo
             contiene: más estrecho, quedaba centrado bajo tarjetas más anchas. -->
        <div :class="carrilVisible ? 'grid grid-cols-[minmax(0,1fr)_20rem] items-start gap-x-12' : undefined">
            <!-- El ritmo del formulario, igual que el de la página: 32 px entre
                 la cabecera, el resumen de errores y las secciones. Sin esto, la
                 primera sección salía pegada a la leyenda de obligatorios. -->
            <motion.div :variants="variantesEntrada" initial="oculto" animate="visible" class="min-w-0 space-y-8">
                <div v-if="seccion">
                    <h2 class="text-base font-semibold tracking-[-0.01em]">{{ titulo }}</h2>
                    <p v-if="descripcion" class="mt-1 max-w-2xl text-sm text-pretty text-muted-foreground">
                        {{ descripcion }}
                    </p>
                    <p v-if="hayObligatorios" class="mt-2 text-xs text-muted-foreground">
                        {{ leyendaObligatorios }}
                    </p>
                </div>

                <CabeceraPagina v-else :titulo="titulo" :descripcion="descripcion">
                    <!-- Un formulario sin campos obligatorios no anuncia asteriscos. -->
                    <p v-if="hayObligatorios" class="mt-2 text-xs text-muted-foreground">
                        {{ leyendaObligatorios }}
                    </p>
                </CabeceraPagina>

                <div
                    v-if="hasErrors"
                    role="alert"
                    class="rounded-xl border border-destructive/40 bg-destructive/5 p-4"
                >
                    <p class="flex items-center gap-2 text-sm font-medium text-destructive">
                        <AlertCircleIcon class="size-4" />
                        Revisa {{ Object.keys(errors).length === 1 ? 'este campo' : 'estos campos' }}
                    </p>
                    <ul class="mt-2.5 space-y-1">
                        <li v-for="(mensaje, campo) in errors" :key="campo">
                            <button
                                type="button"
                                class="rounded text-left text-sm text-destructive underline-offset-4 hover:underline"
                                @click="irAlCampo(String(campo))"
                            >
                                {{ mensaje }}
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="space-y-6">
                    <slot :errors="errors" :processing="processing" />
                </div>

                <!-- Sin carril, lo que se deriva se lee justo antes de enviar. -->
                <section
                    v-if="$slots.resumen && !carrilVisible"
                    aria-labelledby="resumen-formulario"
                    class="grid gap-4 rounded-xl bg-card p-5 ring-1 ring-foreground/10"
                >
                    <h2 id="resumen-formulario" class="text-sm font-semibold">{{ tituloResumen }}</h2>
                    <slot name="resumen" />
                </section>
            </motion.div>

            <!--
                `top-24`: 64 px de la cabecera de la aplicación y 32 de aire. El
                carril no se desplaza por dentro: si no cabe en la ventana, lo que
                sobra es el resumen, y eso se arregla recortando el resumen.
            -->
            <aside v-if="carrilVisible" aria-label="Resumen y acciones" class="sticky top-24 grid gap-5 pt-1">
                <IndiceFormulario v-if="secciones.length >= 3" :secciones="secciones" />

                <section
                    v-if="$slots.resumen"
                    aria-labelledby="resumen-formulario"
                    class="grid gap-4 rounded-xl bg-card p-5 ring-1 ring-foreground/10"
                >
                    <h2 id="resumen-formulario" class="text-sm font-semibold">{{ tituloResumen }}</h2>
                    <slot name="resumen" />
                </section>

                <BarraAcciones :url-cancelar="urlCancelar" :sucio="sucio" :enviando="processing" disposicion="carril">
                    <template v-if="pendientes > 0" #nota>
                        {{ pendientes === 1 ? 'Falta 1 obligatorio' : `Faltan ${pendientes} obligatorios` }}
                    </template>

                    <Button type="submit" size="lg" :disabled="processing">
                        {{ processing ? 'Guardando…' : etiquetaEnviar }}
                    </Button>
                </BarraAcciones>
            </aside>
        </div>

        <BarraAcciones v-if="!carrilVisible" :url-cancelar="urlCancelar" :sucio="sucio" :enviando="processing">
            <template v-if="pendientes > 0" #nota>
                {{ pendientes === 1 ? 'Falta 1 campo obligatorio' : `Faltan ${pendientes} campos obligatorios` }}
            </template>

            <Button type="submit" :disabled="processing">
                {{ processing ? 'Guardando…' : etiquetaEnviar }}
            </Button>
        </BarraAcciones>
    </Form>
</template>
