<script setup lang="ts">
import CampoPassword from '@/components/formulario/CampoPassword.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form } from '@inertiajs/vue3';

/**
 * Aceptar una invitación: la primera contraseña de una cuenta (§ 4.19).
 *
 * Es la hermana de `RestablecerPassword` y no la misma pantalla: la frase de
 * arriba es otra —aquí nadie ha olvidado nada— y el envío va a
 * `/invitacion`, que valida contra el broker de invitaciones.
 */
const props = defineProps<{ email: string; token: string }>();
</script>

<template>
    <AuthLayout titulo="Tu cuenta en Statera" descripcion="Fija tu contraseña para entrar. Elige una que no uses en ningún otro sitio.">
        <Form action="/invitacion" method="post" #default="{ errors, processing }" class="grid gap-5">
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

            <CampoPassword
                nombre="password"
                etiqueta="Contraseña"
                autocomplete="new-password"
                :error="errors.password"
                ayuda="Al menos ocho caracteres. Un gestor de contraseñas se encarga mejor que tu memoria."
                autofocus
                requerido
            />

            <CampoPassword
                nombre="password_confirmation"
                etiqueta="Repite la contraseña"
                autocomplete="new-password"
                :error="errors.password_confirmation"
                requerido
            />

            <Button type="submit" :disabled="processing" class="w-full">
                {{ processing ? 'Guardando…' : 'Fijar la contraseña' }}
            </Button>
        </Form>
    </AuthLayout>
</template>
