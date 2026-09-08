<script setup lang="ts">
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form } from '@inertiajs/vue3';

const props = defineProps<{ email: string; token: string }>();
</script>

<template>
    <AuthLayout titulo="Nueva contraseña">
        <Form action="/reset-password" method="post" #default="{ errors, processing }" class="grid gap-4">
            <input type="hidden" name="token" :value="props.token" />

            <CampoTexto
                nombre="email"
                etiqueta="Correo electrónico"
                tipo="email"
                autocomplete="username"
                :valor-inicial="props.email"
                :error="errors.email"
                requerido
            />

            <CampoTexto
                nombre="password"
                etiqueta="Nueva contraseña"
                tipo="password"
                autocomplete="new-password"
                :error="errors.password"
                autofocus
                requerido
            />

            <CampoTexto
                nombre="password_confirmation"
                etiqueta="Repite la contraseña"
                tipo="password"
                autocomplete="new-password"
                :error="errors.password_confirmation"
                requerido
            />

            <Button type="submit" :disabled="processing" class="w-full">Guardar</Button>
        </Form>
    </AuthLayout>
</template>
