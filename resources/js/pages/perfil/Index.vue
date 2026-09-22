<script setup lang="ts">
import AvatarUsuario from '@/components/AvatarUsuario.vue';
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoPassword from '@/components/formulario/CampoPassword.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { Button } from '@/components/ui/button';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFechaHora } from '@/lib/celdas';
import { entradaDe } from '@/lib/navegacion';
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { BuildingIcon, CheckIcon, MinusIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, ref } from 'vue';

interface Passkey {
    id: number;
    nombre: string;
    autenticador: string | null;
    ultimoUso: string | null;
    alta: string | null;
}

interface Verbo {
    clave: string;
    etiqueta: string;
    tiene: boolean;
}

interface ModuloPermitido {
    clave: string;
    href: string;
    /** Sólo para los prefijos que no son módulos del mapa. Normalmente nulo. */
    etiqueta: string | null;
    verbos: Verbo[];
}

interface Permisos {
    roles: { clave: string; etiqueta: string; descripcion: string | null }[];
    modulos: ModuloPermitido[];
    sinAcceso: { clave: string; href: string; etiqueta: string | null }[];
}

const props = defineProps<{
    usuario: { nombre: string | null; email: string | null; foto: string | null };
    dosFactores: { confirmado: boolean; pendiente: boolean };
    passkeys: Passkey[];
    permisos: Permisos;
    /**
     * Sólo llega desde `/perfil/dos-factores`, que va detrás de la
     * reconfirmación de contraseña.
     */
    secreto: { qr: string; clave: string; codigos: string[] } | null;
}>();

const { variantesEntrada } = useMovimientoReducido();

/*
 * El formulario arranca CON los valores actuales. Antes eran dos cadenas
 * vacías con el valor real puesto de `placeholder`: quien pulsaba «Guardar»
 * sin reescribir los dos campos recibía un error de validación, y quien
 * cambiaba sólo el nombre mandaba el correo vacío.
 */
const datos = useForm({ name: props.usuario.nombre ?? '', email: props.usuario.email ?? '' });
const password = useForm({ current_password: '', password: '', password_confirmation: '' });
const confirmacion = useForm({ code: '' });
const foto = useForm<{ foto: File | null }>({ foto: null });

/* El nombre del passkey lo escribe la persona: «MacBook del trabajo». */
const nombrePasskey = ref('');
const selectorFoto = ref<HTMLInputElement | null>(null);

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

/**
 * El título y el icono de un módulo salen de `lib/navegacion.ts`, que es el
 * mapa único de la aplicación: aquí se lee, no se reescribe. Que todo prefijo
 * de permiso tenga entrada allí lo clava `PermisosDeLaCuentaTest`, porque
 * `entradaDe()` devuelve `undefined` en silencio y la fila saldría sin nombre.
 */
const nombreDe = (modulo: { href: string; etiqueta: string | null }): string =>
    entradaDe(modulo.href)?.titulo ?? modulo.etiqueta ?? modulo.href.replace(/^\//, '');
const iconoDe = (href: string) => entradaDe(href)?.icono;

/** El verbo sin su módulo: `activos.gestionar` → «gestionar». */
const verbo = (clave: string): string => clave.split('.')[1] ?? clave;

const sinSegundoFactor = computed(() => !props.dosFactores.confirmado && !props.dosFactores.pendiente);

function guardarDatos(): void {
    datos.put('/user/profile-information', { preserveScroll: true });
}

function cambiarPassword(): void {
    password.put('/user/password', {
        preserveScroll: true,
        onSuccess: () => password.reset(),
    });
}

function alElegirFoto(evento: Event): void {
    const elegido = (evento.target as HTMLInputElement).files?.[0];

    if (!elegido) {
        return;
    }

    /*
     * Se envía al elegir y no con un botón aparte. Una foto no es un campo de
     * un formulario que se revisa antes de guardar: es un gesto completo, y un
     * «Guardar» detrás sólo añade un paso que se olvida con el fichero ya
     * elegido y la pantalla diciendo que no se ha cambiado nada.
     */
    foto.foto = elegido;

    foto.post('/perfil/foto', {
        preserveScroll: true,
        onFinish: () => {
            foto.reset();

            // Sin esto, volver a elegir el MISMO fichero no dispara `change`.
            if (selectorFoto.value) {
                selectorFoto.value.value = '';
            }
        },
    });
}

function quitarFoto(): void {
    router.delete('/perfil/foto', { preserveScroll: true });
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
            <!--
                Lo único que va mal de verdad en esta pantalla sube arriba, en
                vez de quedarse enterrado en su sección. Es lo que ya hace el
                panel con sus rojos, y es lo que permite ordenar el resto por
                lectura —quién soy, cómo entro, qué puedo hacer— sin que el
                segundo factor pierda el sitio que tenía.
            -->
            <Aviso v-if="sinSegundoFactor" tono="error" titulo="Sin segundo factor">
                <p>
                    La cuenta se protege sólo con la contraseña. Una contraseña robada no debería bastar para
                    llegar al inventario de activos de una organización entera.
                </p>
                <Button size="sm" class="mt-3" @click="activar">Activar ahora</Button>
            </Aviso>

            <!-- ── Identidad ──────────────────────────────────────────────── -->
            <SeccionFormulario
                titulo="Identidad"
                ayuda="El nombre es el que aparece en la traza de auditoría y en el histórico de cada requisito: es lo que un auditor lee cuando pregunta quién hizo un cambio."
            >
                <div class="flex flex-wrap items-center gap-4">
                    <AvatarUsuario
                        :nombre="usuario.nombre"
                        :foto="usuario.foto"
                        clase="size-16 text-lg"
                    />

                    <div class="flex flex-wrap items-center gap-2">
                        <!--
                            El `<input type="file">` va oculto y lo dispara el
                            botón: el control nativo se pinta distinto en cada
                            navegador y aquí no hay un formulario alrededor que
                            lo justifique. Sigue siendo el mismo control, así
                            que el teclado y el lector de pantalla lo alcanzan.
                        -->
                        <input
                            ref="selectorFoto"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            aria-label="Elegir una foto de perfil"
                            @change="alElegirFoto"
                        />

                        <Button variant="outline" size="sm" :disabled="foto.processing" @click="selectorFoto?.click()">
                            {{ foto.processing ? 'Subiendo…' : usuario.foto ? 'Cambiar la foto' : 'Subir una foto' }}
                        </Button>

                        <Button v-if="usuario.foto" variant="ghost" size="sm" @click="quitarFoto">
                            Quitarla
                        </Button>

                        <p class="w-full text-xs text-muted-foreground">
                            JPG, PNG o WebP, hasta 4 MB. Se recorta al cuadrado y se guarda a 256 px.
                        </p>
                    </div>
                </div>

                <p v-if="foto.errors.foto" class="text-sm text-destructive">{{ foto.errors.foto }}</p>

                <CampoTexto
                    v-model="datos.name"
                    nombre="name"
                    etiqueta="Nombre"
                    autocomplete="name"
                    :error="datos.errors.name"
                    requerido
                />

                <CampoTexto
                    v-model="datos.email"
                    nombre="email"
                    etiqueta="Correo electrónico"
                    tipo="email"
                    autocomplete="email"
                    :error="datos.errors.email"
                    requerido
                />

                <div class="flex justify-end">
                    <Button :disabled="datos.processing" @click="guardarDatos">
                        {{ datos.processing ? 'Guardando…' : 'Guardar' }}
                    </Button>
                </div>
            </SeccionFormulario>

            <!-- ── Contraseña ─────────────────────────────────────────────── -->
            <SeccionFormulario
                titulo="Contraseña"
                ayuda="Se pide la actual para que una sesión olvidada abierta no pueda cambiarla."
            >
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

                <div class="flex justify-end">
                    <Button :disabled="password.processing" @click="cambiarPassword">
                        {{ password.processing ? 'Guardando…' : 'Cambiar la contraseña' }}
                    </Button>
                </div>
            </SeccionFormulario>

            <!-- ── Segundo factor ─────────────────────────────────────────── -->
            <SeccionFormulario
                titulo="Verificación en dos pasos"
                ayuda="Obligatoria para quien pueda escribir. Una contraseña robada no debería bastar para llegar al inventario de activos de una organización entera."
            >
                <Aviso v-if="dosFactores.confirmado" tono="exito" titulo="Segundo factor activo">
                    Cada vez que entres se te pedirá el código de tu aplicación de autenticación.
                </Aviso>

                <Aviso v-else-if="dosFactores.pendiente" tono="info" titulo="A medio activar">
                    El secreto está generado pero nadie ha confirmado todavía que la aplicación de
                    autenticación funciona. Hasta que se confirme, el segundo factor no protege nada.
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

                    <div v-if="!dosFactores.confirmado" class="space-y-3">
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

                <div class="flex flex-wrap justify-end gap-2">
                    <Link v-if="dosFactores.confirmado || dosFactores.pendiente" href="/perfil/dos-factores">
                        <Button variant="outline">
                            {{ secreto ? 'Recargar el código' : 'Ver el código y los de recuperación' }}
                        </Button>
                    </Link>

                    <Button v-if="sinSegundoFactor" @click="activar">Activar</Button>
                    <Button v-else variant="ghost" @click="desactivar">Desactivar</Button>
                </div>
            </SeccionFormulario>

            <!-- ── Passkeys ───────────────────────────────────────────────── -->
            <SeccionFormulario
                titulo="Passkeys"
                ayuda="Entrar con la huella, la cara o una llave física. No hay contraseña que robar ni código que interceptar, y por eso el phishing no funciona contra ellas."
            >
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
            </SeccionFormulario>

            <!-- ── Permisos ───────────────────────────────────────────────── -->
            <SeccionFormulario
                titulo="Qué puedes hacer"
                ayuda="Lo que tu rol permite en cada módulo. No se cambia desde aquí: quién tiene qué rol lo decide la organización."
            >
                <div v-for="rol in permisos.roles" :key="rol.clave">
                    <p class="text-sm font-medium">{{ rol.etiqueta }}</p>
                    <p v-if="rol.descripcion" class="mt-1 text-sm text-muted-foreground">{{ rol.descripcion }}</p>
                </div>

                <p v-if="permisos.roles.length === 0" class="text-sm text-muted-foreground">
                    Esta cuenta no tiene ningún rol asignado, así que no ve ni puede hacer nada. Lo arregla
                    quien administre la organización.
                </p>

                <!--
                    Una fila por módulo y no una por permiso: cuarenta y tres
                    líneas con su palomita es la fila de ceros del inventario
                    otra vez. Lo que NO se tiene se pinta igual, porque la
                    pregunta que trae a alguien aquí es «¿por qué no me sale
                    este botón?» y una lista de sólo lo concedido no la
                    contesta.
                -->
                <ul v-if="permisos.modulos.length > 0" class="divide-y divide-border">
                    <li
                        v-for="modulo in permisos.modulos"
                        :key="modulo.clave"
                        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-2.5 first:pt-0 last:pb-0"
                    >
                        <span class="flex min-w-0 items-center gap-2 text-sm">
                            <component
                                :is="iconoDe(modulo.href) ?? BuildingIcon"
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="truncate">{{ nombreDe(modulo) }}</span>
                        </span>

                        <span class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <!--
                                El icono no es decoración: §11 no deja que el
                                estado dependa del color, y aquí la diferencia
                                entre lo que se tiene y lo que no es
                                exactamente un estado.
                            -->
                            <span
                                v-for="verboPermiso in modulo.verbos"
                                :key="verboPermiso.clave"
                                class="flex items-center gap-1 text-xs"
                                :class="verboPermiso.tiene ? 'text-foreground' : 'text-muted-foreground line-through'"
                                :title="verboPermiso.etiqueta"
                            >
                                <CheckIcon v-if="verboPermiso.tiene" class="size-3.5 text-estado-implantado" />
                                <MinusIcon v-else class="size-3.5" />
                                {{ verbo(verboPermiso.clave) }}
                            </span>
                        </span>
                    </li>
                </ul>

                <!--
                    Hoy no se pinta: los tres roles del § 4.19 llevan el `.ver`
                    de los diecisiete módulos. Aparece el día que haya un rol
                    más estrecho, y hasta entonces no ocupa sitio.
                -->
                <p v-if="permisos.sinAcceso.length > 0" class="text-sm text-muted-foreground">
                    No ves:
                    {{ permisos.sinAcceso.map(nombreDe).join(' · ') }}.
                </p>
            </SeccionFormulario>
        </motion.div>
    </AppLayout>
</template>
