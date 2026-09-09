<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CampoPassword from '@/components/formulario/CampoPassword.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form, Link } from '@inertiajs/vue3';

defineProps<{ puedeRestablecer: boolean; estado?: string | null }>();
</script>

<template>
    <AuthLayout titulo="Entrar en Statera" descripcion="Usa la cuenta que te dio de alta el responsable de seguridad.">
        <Form action="/login" method="post" #default="{ errors, processing }" class="grid gap-5">
            <!--
                Fortify devuelve el fallo de acceso en el campo `email`, pero no
                es un error de ese campo: es que la pareja no vale. Enseñarlo
                colgando del correo hacía que la gente corrigiera el correo, que
                casi siempre estaba bien. Arriba y como aviso dice lo que es.
            -->
            <Aviso v-if="errors.email" tono="error" titulo="No hemos podido entrar">
                {{ errors.email }}
            </Aviso>

            <Aviso v-else-if="estado" tono="exito">{{ estado }}</Aviso>

            <CampoTexto
                nombre="email"
                etiqueta="Correo electrónico"
                tipo="email"
                autocomplete="username"
                requerido
                autofocus
            />

            <CampoPassword
                nombre="password"
                etiqueta="Contraseña"
                autocomplete="current-password"
                :error="errors.password"
                requerido
            />

            <div class="flex items-center justify-between gap-4">
                <Label class="gap-2 text-sm font-normal">
                    <Checkbox name="remember" value="1" />
                    Mantener la sesión
                </Label>

                <Link
                    v-if="puedeRestablecer"
                    href="/forgot-password"
                    class="rounded text-sm text-primary underline-offset-4 hover:underline"
                >
                    He olvidado la contraseña
                </Link>
            </div>

            <Button type="submit" :disabled="processing" class="w-full">
                {{ processing ? 'Entrando…' : 'Entrar' }}
            </Button>
        </Form>

        <template #pie>
            Statera no tiene alta self-service: las cuentas las crea el responsable de seguridad de cada
            organización. Si no tienes acceso, habla con quien lleve el SGSI.
        </template>
    </AuthLayout>
</template>
