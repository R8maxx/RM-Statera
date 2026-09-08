<script setup lang="ts">
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import MensajeError from '@/components/formulario/MensajeError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form, Link } from '@inertiajs/vue3';

defineProps<{ puedeRestablecer: boolean; estado?: string | null }>();
</script>

<template>
    <AuthLayout titulo="Iniciar sesión" descripcion="Las cuentas las da de alta el responsable de seguridad.">
        <p v-if="estado" class="mb-4 text-sm font-medium text-estado-implantado">{{ estado }}</p>

        <Form action="/login" method="post" #default="{ errors, processing }" class="grid gap-4">
            <CampoTexto
                nombre="email"
                etiqueta="Correo electrónico"
                tipo="email"
                autocomplete="username"
                :error="errors.email"
                requerido
                autofocus
            />

            <CampoTexto
                nombre="password"
                etiqueta="Contraseña"
                tipo="password"
                autocomplete="current-password"
                :error="errors.password"
                requerido
            />

            <div class="flex items-center justify-between">
                <Label class="gap-2 text-sm font-normal">
                    <Checkbox name="remember" value="1" />
                    Mantener la sesión
                </Label>

                <Link
                    v-if="puedeRestablecer"
                    href="/forgot-password"
                    class="text-sm text-primary underline-offset-4 hover:underline"
                >
                    He olvidado la contraseña
                </Link>
            </div>

            <MensajeError :mensaje="errors.remember" />

            <Button type="submit" :disabled="processing" class="w-full">Entrar</Button>
        </Form>
    </AuthLayout>
</template>
