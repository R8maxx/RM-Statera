<script setup lang="ts">
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form } from '@inertiajs/vue3';
</script>

<template>
    <AuthLayout titulo="Confirma tu contraseña" descripcion="Esta zona es sensible: vuelve a identificarte para seguir.">
        <Form action="/user/confirm-password" method="post" #default="{ errors, processing }" class="grid gap-4">
            <CampoTexto
                nombre="password"
                etiqueta="Contraseña"
                tipo="password"
                autocomplete="current-password"
                :error="errors.password"
                autofocus
                requerido
            />

            <Button type="submit" :disabled="processing" class="w-full">Confirmar</Button>
        </Form>
    </AuthLayout>
</template>
