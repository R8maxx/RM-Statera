<script setup lang="ts">
import CampoPassword from '@/components/formulario/CampoPassword.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form } from '@inertiajs/vue3';
</script>

<template>
    <AuthLayout
        titulo="Confirma tu contraseña"
        descripcion="Vas a entrar en una zona sensible. Identifícate otra vez para seguir."
    >
        <Form
            action="/user/confirm-password"
            method="post"
            #default="{ errors, processing }"
            class="grid gap-5"
        >
            <CampoPassword
                nombre="password"
                etiqueta="Contraseña"
                autocomplete="current-password"
                :error="errors.password"
                autofocus
                requerido
            />

            <Button type="submit" :disabled="processing" class="w-full">
                {{ processing ? 'Comprobando…' : 'Confirmar' }}
            </Button>
        </Form>

        <template #pie>
            Esta confirmación vale para las tres horas siguientes. La pide todo lo que toca el segundo factor o las
            claves de acceso.
        </template>
    </AuthLayout>
</template>
