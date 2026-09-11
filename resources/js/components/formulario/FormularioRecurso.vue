<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import BarraAcciones from '@/components/formulario/BarraAcciones.vue';
import { Button } from '@/components/ui/button';
import { proveerObligatorios } from '@/composables/useCamposObligatorios';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { Form } from '@inertiajs/vue3';
import type { Method } from '@inertiajs/core';
import { AlertCircleIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, onMounted, ref } from 'vue';

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
 */
withDefaults(
    defineProps<{
        titulo: string;
        descripcion?: string;
        action: string;
        method?: Method;
        etiquetaEnviar?: string;
        urlCancelar: string;
    }>(),
    { method: 'post', etiquetaEnviar: 'Guardar' },
);

const { variantesEntrada } = useMovimientoReducido();

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

const { pendientes, sucio } = proveerObligatorios(formulario);

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
        class="mx-auto w-full max-w-4xl pb-24"
    >
        <div ref="ancla" class="contents" />

        <motion.div :variants="variantesEntrada" initial="oculto" animate="visible">
            <CabeceraPagina :titulo="titulo" :descripcion="descripcion">
                <p class="mt-2 text-xs text-muted-foreground">{{ leyendaObligatorios }}</p>
            </CabeceraPagina>

            <div
                v-if="hasErrors"
                role="alert"
                class="mb-8 rounded-xl border border-destructive/40 bg-destructive/5 p-4"
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
        </motion.div>

        <BarraAcciones :url-cancelar="urlCancelar" :sucio="sucio" :enviando="processing">
            <template v-if="pendientes > 0" #nota>
                {{ pendientes === 1 ? 'Falta 1 campo obligatorio' : `Faltan ${pendientes} campos obligatorios` }}
            </template>

            <Button type="submit" :disabled="processing">
                {{ processing ? 'Guardando…' : etiquetaEnviar }}
            </Button>
        </BarraAcciones>
    </Form>
</template>
