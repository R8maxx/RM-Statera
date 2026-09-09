<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { Form, Link } from '@inertiajs/vue3';
import type { Method } from '@inertiajs/core';
import { AlertCircleIcon } from '@lucide/vue';
import { motion } from 'motion-v';

/**
 * La envoltura común de los formularios de un recurso.
 *
 * Va sobre el componente `<Form>` de Inertia v3, así que los campos no
 * necesitan `v-model`: el formulario lee el `FormData` y los errores llegan tal
 * cual los devuelve el `FormRequest`, que es la única fuente de verdad de la
 * validación.
 *
 * La barra de acciones queda pegada al pie de la ventana porque los formularios
 * de este dominio son largos —una valoración de dimensiones no cabe en una
 * pantalla— y bajar hasta el final para guardar es trabajo que no aporta nada.
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

/**
 * Lleva el foco al campo que falla.
 *
 * El resumen anterior decía cuántos errores había y ahí se acababa: en un
 * formulario largo, saber que hay tres no ayuda a encontrarlos. Se busca por
 * `name`, que es lo que comparten el error del `FormRequest` y el control.
 */
function irAlCampo(nombre: string): void {
    const campo = document.querySelector<HTMLElement>(`[name="${CSS.escape(nombre)}"]`);

    campo?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    campo?.focus({ preventScroll: true });
}
</script>

<template>
    <Form
        :action="action"
        :method="method"
        #default="{ errors, processing, hasErrors }"
        class="mx-auto w-full max-w-4xl pb-24"
    >
        <motion.div :variants="variantesEntrada" initial="oculto" animate="visible">
            <header class="mb-8">
                <h1 class="text-xl font-semibold tracking-tight">{{ titulo }}</h1>
                <p v-if="descripcion" class="mt-1.5 max-w-2xl text-sm text-muted-foreground">
                    {{ descripcion }}
                </p>
            </header>

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

        <div
            class="fixed inset-x-0 bottom-0 z-20 border-t bg-background/90 px-4 py-3 backdrop-blur-sm sm:px-6 lg:px-8"
        >
            <div class="mx-auto flex max-w-4xl items-center justify-end gap-2">
                <Link :href="urlCancelar">
                    <Button type="button" variant="ghost">Cancelar</Button>
                </Link>
                <Button type="submit" :disabled="processing">
                    {{ processing ? 'Guardando…' : etiquetaEnviar }}
                </Button>
            </div>
        </div>
    </Form>
</template>
