<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, Link } from '@inertiajs/vue3';
import type { Method } from '@inertiajs/core';

/**
 * La envoltura común de los formularios de un recurso.
 *
 * Va sobre el componente `<Form>` de Inertia v3, así que los campos no
 * necesitan `v-model`: el formulario lee el `FormData` y los errores llegan tal
 * cual los devuelve el `FormRequest`, que es la única fuente de verdad de la
 * validación.
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
</script>

<template>
    <Form
        :action="action"
        :method="method"
        #default="{ errors, processing, hasErrors }"
        class="mx-auto w-full max-w-2xl"
    >
        <Card>
            <CardHeader>
                <CardTitle>{{ titulo }}</CardTitle>
                <CardDescription v-if="descripcion">{{ descripcion }}</CardDescription>
            </CardHeader>

            <CardContent class="grid gap-5">
                <div
                    v-if="hasErrors"
                    role="alert"
                    class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    Revisa los campos marcados: hay {{ Object.keys(errors).length }} sin resolver.
                </div>

                <slot :errors="errors" :processing="processing" />
            </CardContent>

            <CardFooter class="justify-end gap-2">
                <Link :href="urlCancelar">
                    <Button type="button" variant="outline">Cancelar</Button>
                </Link>
                <Button type="submit" :disabled="processing">{{ etiquetaEnviar }}</Button>
            </CardFooter>
        </Card>
    </Form>
</template>
