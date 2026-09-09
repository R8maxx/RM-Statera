<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * El segundo factor.
 *
 * El código va en un único campo ancho en monoespaciada, no en seis casillas
 * separadas: las casillas se ven modernas y rompen el pegado desde el gestor de
 * contraseñas y el autorrelleno del SMS. Con `autocomplete="one-time-code"` el
 * sistema operativo lo ofrece solo.
 */
const usandoRecuperacion = ref(false);
</script>

<template>
    <AuthLayout
        titulo="Verificación en dos pasos"
        :descripcion="
            usandoRecuperacion
                ? 'Introduce uno de los códigos de recuperación que guardaste al activar el segundo factor.'
                : 'Abre tu aplicación de autenticación e introduce el código de seis dígitos.'
        "
    >
        <Form action="/two-factor-challenge" method="post" #default="{ errors, processing }" class="grid gap-5">
            <Aviso v-if="errors.code || errors.recovery_code" tono="error" titulo="El código no es válido">
                {{ errors.code ?? errors.recovery_code }}
            </Aviso>

            <CampoTexto
                v-if="!usandoRecuperacion"
                key="codigo"
                nombre="code"
                etiqueta="Código de verificación"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                placeholder="000000"
                class="cifra h-14 text-center text-2xl tracking-[0.5em]"
                autofocus
                requerido
            />

            <CampoTexto
                v-else
                key="recuperacion"
                nombre="recovery_code"
                etiqueta="Código de recuperación"
                autocomplete="one-time-code"
                class="cifra h-12 text-center"
                ayuda="Cada código sirve una sola vez."
                autofocus
                requerido
            />

            <Button type="submit" :disabled="processing" class="w-full">
                {{ processing ? 'Verificando…' : 'Verificar' }}
            </Button>

            <button
                type="button"
                class="rounded text-center text-sm text-primary underline-offset-4 hover:underline"
                @click="usandoRecuperacion = !usandoRecuperacion"
            >
                {{
                    usandoRecuperacion
                        ? 'Volver a la aplicación de autenticación'
                        : 'No tengo acceso a la aplicación'
                }}
            </button>
        </Form>

        <template #pie>
            Si has perdido el dispositivo y los códigos de recuperación, sólo el responsable de seguridad de tu
            organización puede devolverte el acceso.
        </template>
    </AuthLayout>
</template>
