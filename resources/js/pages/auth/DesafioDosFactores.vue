<script setup lang="ts">
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';

const usandoRecuperacion = ref(false);
</script>

<template>
    <AuthLayout
        titulo="Verificación en dos pasos"
        :descripcion="
            usandoRecuperacion
                ? 'Introduce uno de tus códigos de recuperación.'
                : 'Introduce el código de seis dígitos de tu aplicación de autenticación.'
        "
    >
        <Form action="/two-factor-challenge" method="post" #default="{ errors, processing }" class="grid gap-4">
            <CampoTexto
                v-if="!usandoRecuperacion"
                key="codigo"
                nombre="code"
                etiqueta="Código"
                inputmode="numeric"
                autocomplete="one-time-code"
                :error="errors.code"
                autofocus
                requerido
            />

            <CampoTexto
                v-else
                key="recuperacion"
                nombre="recovery_code"
                etiqueta="Código de recuperación"
                autocomplete="one-time-code"
                :error="errors.recovery_code"
                autofocus
                requerido
            />

            <Button type="submit" :disabled="processing" class="w-full">Verificar</Button>

            <Button type="button" variant="link" size="sm" @click="usandoRecuperacion = !usandoRecuperacion">
                {{ usandoRecuperacion ? 'Usar la aplicación de autenticación' : 'Usar un código de recuperación' }}
            </Button>
        </Form>
    </AuthLayout>
</template>
