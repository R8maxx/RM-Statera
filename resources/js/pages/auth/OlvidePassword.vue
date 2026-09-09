<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form, Link } from '@inertiajs/vue3';

defineProps<{ estado?: string | null }>();
</script>

<template>
    <AuthLayout
        titulo="Restablecer la contraseña"
        descripcion="Te enviamos un enlace al correo para que elijas una nueva."
    >
        <Form action="/forgot-password" method="post" #default="{ errors, processing }" class="grid gap-5">
            <Aviso v-if="estado" tono="exito">{{ estado }}</Aviso>

            <CampoTexto
                nombre="email"
                etiqueta="Correo electrónico"
                tipo="email"
                autocomplete="username"
                :error="errors.email"
                autofocus
                requerido
            />

            <Button type="submit" :disabled="processing" class="w-full">
                {{ processing ? 'Enviando…' : 'Enviar el enlace' }}
            </Button>

            <Link
                href="/login"
                class="rounded text-center text-sm text-primary underline-offset-4 hover:underline"
            >
                Volver a entrar
            </Link>
        </Form>

        <template #pie>
            El enlace caduca a los sesenta minutos. Si no llega, revisa la carpeta de correo no deseado antes de
            pedir otro.
        </template>
    </AuthLayout>
</template>
