<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Aviso from '@/components/Aviso.vue';
import CampoPassword from '@/components/formulario/CampoPassword.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFechaHora } from '@/lib/celdas';
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import { ref } from 'vue';

interface Passkey {
    id: number;
    nombre: string;
    autenticador: string | null;
    ultimoUso: string | null;
    alta: string | null;
}

defineProps<{
    usuario: { nombre: string | null; email: string | null };
    dosFactores: { confirmado: boolean; pendiente: boolean };
    passkeys: Passkey[];
    /**
     * Sólo llega desde `/perfil/dos-factores`, que va detrás de la
     * reconfirmación de contraseña.
     */
    secreto: { qr: string; clave: string; codigos: string[] } | null;
}>();

const { variantesEntrada } = useMovimientoReducido();

const datos = useForm({ name: '', email: '' });
const password = useForm({ current_password: '', password: '', password_confirmation: '' });
const confirmacion = useForm({ code: '' });

/* El nombre del passkey lo escribe la persona: «MacBook del trabajo». */
const nombrePasskey = ref('');

const {
    register,
    isLoading: registrando,
    error: errorPasskey,
    isSupported: passkeysSoportados,
} = usePasskeyRegister({
    onSuccess: () => {
        nombrePasskey.value = '';
        router.reload({ only: ['passkeys'] });
    },
});

function guardarDatos(): void {
    datos.put('/user/profile-information', { preserveScroll: true });
}

function cambiarPassword(): void {
    password.put('/user/password', {
        preserveScroll: true,
        onSuccess: () => password.reset(),
    });
}

/* Navegación, no petición de fondo: puede pasar por confirmar la contraseña. */
function activar(): void {
    router.post('/user/two-factor-authentication', {}, { preserveScroll: true });
}

function desactivar(): void {
    router.delete('/user/two-factor-authentication', { preserveScroll: true });
}

function confirmar(): void {
    confirmacion.post('/user/confirmed-two-factor-authentication', {
        preserveScroll: true,
        onSuccess: () => confirmacion.reset(),
    });
}

function regenerarCodigos(): void {
    router.post('/user/two-factor-recovery-codes', {}, { preserveScroll: true });
}

function borrarPasskey(id: number): void {
    router.delete(`/user/passkeys/${id}`, { preserveScroll: true });
}

const cuando = (valor: string | null): string =>
    valor ? formatoFechaHora.format(new Date(valor)) : 'nunca';
</script>

<template>
    <AppLayout titulo="Mi cuenta">
        <CabeceraPagina
            titulo="Mi cuenta"
            descripcion="Statera entra en el alcance del propio SGSI: contiene el inventario, las vulnerabilidades y las evidencias. Lo que se configure aquí es una medida de seguridad, no una preferencia."
        />

        <motion.div
            :variants="variantesEntrada"
            initial="oculto"
            animate="visible"
            class="mx-auto w-full max-w-4xl space-y-6"
        >
            <!-- ── Segundo factor ─────────────────────────────────────────── -->
            <Card>
                <CardHeader>
                    <CardTitle>Verificación en dos pasos</CardTitle>
                    <CardDescription>
                        Obligatoria para quien pueda escribir. Una contraseña robada no debería bastar para llegar
                        al inventario de activos de una organización entera.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-5">
                    <Aviso v-if="dosFactores.confirmado" tono="exito" titulo="Segundo factor activo">
                        Cada vez que entres se te pedirá el código de tu aplicación de autenticación.
                    </Aviso>

                    <Aviso v-else-if="dosFactores.pendiente" tono="info" titulo="A medio activar">
                        El secreto está generado pero nadie ha confirmado todavía que la aplicación de
                        autenticación funciona. Hasta que se confirme, el segundo factor no protege nada.
                    </Aviso>

                    <Aviso v-else tono="error" titulo="Sin segundo factor">
                        La cuenta se protege sólo con la contraseña.
                    </Aviso>

                    <!-- El secreto sólo está aquí si se ha pasado por la
                         reconfirmación de contraseña. -->
                    <template v-if="secreto">
                        <div class="grid gap-5 sm:grid-cols-[auto_1fr] sm:items-start">
                            <div class="rounded-xl border bg-white p-3" v-html="secreto.qr" />

                            <div class="min-w-0 space-y-3">
                                <div>
                                    <p class="text-sm font-medium">Escanea el código</p>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        Con Google Authenticator, 1Password, Aegis o cualquier aplicación TOTP.
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs text-muted-foreground">O introduce la clave a mano</p>
                                    <p class="cifra mt-0.5 text-sm break-all">{{ secreto.clave }}</p>
                                </div>
                            </div>
                        </div>

                        <!--
                            Los códigos de recuperación son la puerta de vuelta
                            cuando se pierde el teléfono. Se enseñan aquí y sólo
                            aquí, y por eso el aviso de guardarlos va con ellos.
                        -->
                        <div class="rounded-xl border p-4">
                            <p class="text-sm font-medium">Códigos de recuperación</p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Guárdalos fuera de este ordenador. Cada uno sirve una sola vez, y son la única
                                forma de entrar si pierdes el teléfono.
                            </p>

                            <ul class="cifra mt-3 grid gap-1 text-sm sm:grid-cols-2">
                                <li v-for="codigo in secreto.codigos" :key="codigo">{{ codigo }}</li>
                            </ul>

                            <Button variant="ghost" size="sm" class="mt-3" @click="regenerarCodigos">
                                Regenerar los códigos
                            </Button>
                        </div>

                        <div v-if="!dosFactores.confirmado" class="max-w-sm space-y-3">
                            <CampoTexto
                                v-model="confirmacion.code"
                                nombre="code"
                                etiqueta="Código de la aplicación"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                placeholder="000000"
                                class="cifra text-center tracking-[0.4em]"
                                :error="confirmacion.errors.code"
                                ayuda="Confirma que la aplicación genera códigos válidos. Hasta entonces el segundo factor no se aplica."
                                requerido
                            />

                            <Button :disabled="confirmacion.processing" @click="confirmar">
                                {{ confirmacion.processing ? 'Comprobando…' : 'Confirmar y activar' }}
                            </Button>
                        </div>
                    </template>
                </CardContent>

                <CardFooter class="justify-end gap-2">
                    <Link v-if="dosFactores.confirmado || dosFactores.pendiente" href="/perfil/dos-factores">
                        <Button variant="outline">
                            {{ secreto ? 'Recargar el código' : 'Ver el código y los de recuperación' }}
                        </Button>
                    </Link>

                    <Button v-if="!dosFactores.confirmado && !dosFactores.pendiente" @click="activar">
                        Activar
                    </Button>

                    <Button v-else variant="ghost" @click="desactivar">Desactivar</Button>
                </CardFooter>
            </Card>

            <!-- ── Passkeys ───────────────────────────────────────────────── -->
            <Card>
                <CardHeader>
                    <CardTitle>Passkeys</CardTitle>
                    <CardDescription>
                        Entrar con la huella, la cara o una llave física. No hay contraseña que robar ni código que
                        interceptar, y por eso el phishing no funciona contra ellas.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-5">
                    <Aviso v-if="!passkeysSoportados" tono="info" titulo="Este navegador no admite passkeys">
                        Puedes seguir entrando con contraseña y segundo factor.
                    </Aviso>

                    <Aviso v-if="errorPasskey" tono="error" titulo="No se ha podido registrar">
                        {{ errorPasskey }}
                    </Aviso>

                    <ul v-if="passkeys.length > 0" class="divide-y divide-border">
                        <li
                            v-for="passkey in passkeys"
                            :key="passkey.id"
                            class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ passkey.nombre }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    <template v-if="passkey.autenticador">{{ passkey.autenticador }} · </template>
                                    alta el {{ cuando(passkey.alta) }} · último uso {{ cuando(passkey.ultimoUso) }}
                                </p>
                            </div>

                            <Button variant="ghost" size="sm" @click="borrarPasskey(passkey.id)">Eliminar</Button>
                        </li>
                    </ul>

                    <p v-else class="text-sm text-muted-foreground">Todavía no hay ninguna passkey registrada.</p>

                    <div v-if="passkeysSoportados" class="flex flex-wrap items-end gap-3">
                        <CampoTexto
                            v-model="nombrePasskey"
                            nombre="nombre_passkey"
                            etiqueta="Nombre"
                            class="w-56"
                            ayuda="Para reconocerla después: «MacBook del trabajo», «llave del cajón»."
                        />

                        <Button
                            variant="outline"
                            :disabled="registrando || nombrePasskey.trim() === ''"
                            @click="register(nombrePasskey)"
                        >
                            {{ registrando ? 'Esperando al dispositivo…' : 'Registrar una passkey' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- ── Datos y contraseña ─────────────────────────────────────── -->
            <Card>
                <CardHeader>
                    <CardTitle>Datos de la cuenta</CardTitle>
                </CardHeader>

                <CardContent>
                    <SeccionFormulario
                        titulo="Identificación"
                        ayuda="El nombre es el que aparece en la traza de auditoría y en el histórico de cada requisito: es lo que un auditor lee cuando pregunta quién hizo un cambio."
                    >
                        <CampoTexto
                            v-model="datos.name"
                            nombre="name"
                            etiqueta="Nombre"
                            :error="datos.errors.name"
                            :placeholder="usuario.nombre ?? ''"
                            requerido
                        />

                        <CampoTexto
                            v-model="datos.email"
                            nombre="email"
                            etiqueta="Correo electrónico"
                            tipo="email"
                            autocomplete="email"
                            :error="datos.errors.email"
                            :placeholder="usuario.email ?? ''"
                            requerido
                        />
                    </SeccionFormulario>
                </CardContent>

                <CardFooter class="justify-end">
                    <Button :disabled="datos.processing" @click="guardarDatos">
                        {{ datos.processing ? 'Guardando…' : 'Guardar' }}
                    </Button>
                </CardFooter>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Contraseña</CardTitle>
                    <CardDescription>
                        Se pide la actual para que una sesión olvidada abierta no pueda cambiarla.
                    </CardDescription>
                </CardHeader>

                <CardContent class="grid max-w-md gap-5">
                    <CampoPassword
                        v-model="password.current_password"
                        nombre="current_password"
                        etiqueta="Contraseña actual"
                        autocomplete="current-password"
                        :error="password.errors.current_password"
                        requerido
                    />

                    <CampoPassword
                        v-model="password.password"
                        nombre="password"
                        etiqueta="Contraseña nueva"
                        autocomplete="new-password"
                        :error="password.errors.password"
                        requerido
                    />

                    <CampoPassword
                        v-model="password.password_confirmation"
                        nombre="password_confirmation"
                        etiqueta="Repite la nueva"
                        autocomplete="new-password"
                        :error="password.errors.password_confirmation"
                        requerido
                    />
                </CardContent>

                <CardFooter class="justify-end">
                    <Button :disabled="password.processing" @click="cambiarPassword">
                        {{ password.processing ? 'Guardando…' : 'Cambiar la contraseña' }}
                    </Button>
                </CardFooter>
            </Card>
        </motion.div>
    </AppLayout>
</template>
