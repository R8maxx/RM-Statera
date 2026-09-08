<script setup lang="ts">
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form, Link } from '@inertiajs/vue3';

defineProps<{ estado?: string | null }>();
</script>

<template>
    <AuthLayout
        titulo="Restablecer la contraseña"
        descripcion="Te enviamos un enlace para elegir una nueva."
    >
        <p v-if="estado" class="mb-4 text-sm font-medium text-estado-implantado">{{ estado }}</p>

        <Form action="/forgot-password" method="post" #default="{ errors, processing }" class="grid gap-4">
            <CampoTexto
                nombre="email"
                etiqueta="Correo electrónico"
                tipo="email"
                autocomplete="username"
                :error="errors.email"
                autofocus
                requerido
            />

            <Button type="submit" :disabled="processing" class="w-full">Enviar el enlace</Button>

            <Link href="/login" class="text-center text-sm text-primary underline-offset-4 hover:underline">
                Volver a iniciar sesión
            </Link>
        </Form>
    </AuthLayout>
</template>
