<script setup lang="ts">
import AvatarUsuario from '@/components/AvatarUsuario.vue';
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoOpciones from '@/components/formulario/CampoOpciones.vue';
import CampoPassword from '@/components/formulario/CampoPassword.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoSwitch from '@/components/formulario/CampoSwitch.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import SeccionFormulario from '@/components/formulario/SeccionFormulario.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { useTema, type PreferenciaTema } from '@/composables/useTema';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible, formatoFechaHora } from '@/lib/celdas';
import { entradaDe } from '@/lib/navegacion';
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    BuildingIcon,
    CheckIcon,
    CircleDashedIcon,
    CircleIcon,
    DownloadIcon,
    MinusIcon,
    MonitorIcon,
    ShieldAlertIcon,
    XIcon,
} from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, onMounted, onUnmounted, ref, type Component } from 'vue';

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

interface Sesion {
    /** La huella de la sesión, nunca su id: el id es la cookie. */
    clave: string;
    navegador: string;
    sistema: string;
    ip: string | null;
    ultimaActividad: string;
    actual: boolean;
}

interface Acceso {
    fecha: string;
    ip: string | null;
    correcto: boolean;
}

interface Ficha {
    rol: string | null;
    puesto: string | null;
    organizacion: string | null;
    desde: string | null;
    passwordCambiadaEn: string | null;
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
    ficha: Ficha | null;
    sesiones: Sesion[];
    accesos: Acceso[];
    preferencias: {
        tema: PreferenciaTema | null;
        paginaInicio: string | null;
        paginasInicio: { valor: string; etiqueta: string }[];
    };
    /** Nulo cuando el rol no recibe el resumen diario. */
    avisos: { activos: boolean } | null;
}>();

const { variantesEntrada, reducido: movimientoReducido } = useMovimientoReducido();

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

/* ── La tira de arriba: cómo está protegida la cuenta ─────────────────────── */

type Tono = 'bien' | 'mal' | 'a-medias' | 'neutro';

const iconoTono: Record<Tono, Component> = { bien: CheckIcon, mal: XIcon, 'a-medias': CircleDashedIcon, neutro: CircleIcon };
const colorTono: Record<Tono, string> = {
    bien: 'text-estado-implantado',
    mal: 'text-destructive',
    'a-medias': 'text-estado-en-progreso',
    neutro: 'text-muted-foreground',
};

/** Días enteros desde una fecha ISO hasta hoy. */
const diasDesde = (valor: string): number => Math.floor((Date.now() - new Date(valor).getTime()) / 86_400_000);

const hace = (dias: number): string => (dias === 0 ? 'hoy' : dias === 1 ? 'ayer' : `hace ${dias} días`);

/*
 * Cuatro casillas y no una puntuación: «2 de 4» invita a pensar que las cuatro
 * pesan lo mismo, y las passkeys son opcionales mientras el segundo factor lo
 * pide el ENS. Cada casilla dice su estado con icono y texto (DESIGN.md §3).
 */
const proteccion = computed<{ titulo: string; detalle: string; tono: Tono }[]>(() => {
    const cambiada = props.ficha?.passwordCambiadaEn;
    const otras = props.sesiones.filter((sesion) => !sesion.actual).length;

    return [
        {
            titulo: 'Contraseña',
            detalle: cambiada ? `Cambiada ${hace(diasDesde(cambiada))}` : 'Sin fecha de cambio registrada',
            tono: cambiada ? 'bien' : 'neutro',
        },
        {
            titulo: 'Segundo factor',
            detalle: props.dosFactores.confirmado ? 'Activo' : props.dosFactores.pendiente ? 'A medio activar' : 'Sin activar',
            tono: props.dosFactores.confirmado ? 'bien' : props.dosFactores.pendiente ? 'a-medias' : 'mal',
        },
        {
            titulo: 'Passkeys',
            detalle:
                props.passkeys.length === 0
                    ? 'Ninguna. Son opcionales'
                    : props.passkeys.length === 1
                      ? '1 registrada'
                      : `${props.passkeys.length} registradas`,
            tono: props.passkeys.length > 0 ? 'bien' : 'neutro',
        },
        {
            titulo: 'Sesiones abiertas',
            detalle: otras === 0 ? 'Sólo ésta' : otras === 1 ? 'Ésta y 1 más' : `Ésta y ${otras} más`,
            tono: 'neutro',
        },
    ];
});

/* ── El índice lateral ────────────────────────────────────────────────────── */

const secciones = computed(() => [
    { id: 'identidad', titulo: 'Identidad', pendiente: false },
    { id: 'contrasena', titulo: 'Contraseña', pendiente: false },
    { id: 'dos-pasos', titulo: 'Dos pasos', pendiente: !props.dosFactores.confirmado },
    { id: 'passkeys', titulo: 'Passkeys', pendiente: false },
    { id: 'sesiones', titulo: 'Sesiones y accesos', pendiente: false },
    { id: 'avisos', titulo: 'Avisos por correo', pendiente: false },
    { id: 'preferencias', titulo: 'Preferencias', pendiente: false },
    { id: 'permisos', titulo: 'Qué puedes hacer', pendiente: false },
    { id: 'datos', titulo: 'Tus datos', pendiente: false },
]);

/*
 * La sección que se está leyendo, para marcarla en el índice. La que cruza la
 * franja alta de la ventana: con el umbral en el centro, una sección corta al
 * final no llegaba nunca a marcarse.
 */
const seccionVisible = ref('identidad');
let observador: IntersectionObserver | undefined;

/*
 * El salto se hace a mano y no con el `href` a secas: el clic en un ancla no
 * llegaba a mover la página. Y al llegar desde el menú de la cuenta
 * (`/perfil#dos-pasos`) se salta también, porque cuando Inertia termina la
 * visita el contenido todavía está entrando.
 */
function irA(id: string): void {
    document.getElementById(id)?.scrollIntoView({ behavior: movimientoReducido.value ? 'auto' : 'smooth', block: 'start' });
    history.replaceState(history.state, '', `#${id}`);
    seccionVisible.value = id;
}

onMounted(() => {
    const deLaUrl = window.location.hash.slice(1);

    if (deLaUrl !== '' && secciones.value.some((seccion) => seccion.id === deLaUrl)) {
        requestAnimationFrame(() => irA(deLaUrl));
    }

    observador = new IntersectionObserver(
        (entradas) => {
            const primera = entradas.find((entrada) => entrada.isIntersecting);

            if (primera) {
                seccionVisible.value = primera.target.id;
            }
        },
        { rootMargin: '-15% 0px -75% 0px' },
    );

    for (const seccion of secciones.value) {
        const elemento = document.getElementById(seccion.id);

        if (elemento) {
            observador.observe(elemento);
        }
    }
});

onUnmounted(() => observador?.disconnect());

/* ── Sesiones ─────────────────────────────────────────────────────────────── */

/*
 * Cerrar pide la contraseña, en un diálogo y en la misma petición: sin eso,
 * quien encuentre una sesión olvidada abierta echa al dueño de las demás.
 * `null` en `cerrando` es «cerrar todas las demás».
 */
const cierre = useForm({ password: '' });
const cerrando = ref<Sesion | null | undefined>(undefined);
const dialogoCierre = computed({
    get: () => cerrando.value !== undefined,
    set: (abierto: boolean) => {
        if (!abierto) {
            cerrando.value = undefined;
            cierre.reset();
            cierre.clearErrors();
        }
    },
});

function cerrarSesiones(): void {
    const url = cerrando.value ? `/perfil/sesiones/${cerrando.value.clave}` : '/perfil/sesiones';

    cierre.delete(url, {
        preserveScroll: true,
        onSuccess: () => (dialogoCierre.value = false),
    });
}

const hayOtrasSesiones = computed(() => props.sesiones.some((sesion) => !sesion.actual));

/* ── Avisos y preferencias ────────────────────────────────────────────────── */

/*
 * El interruptor guarda al pulsarlo: un «Guardar» debajo de un único
 * interruptor es un paso que se olvida con la pantalla diciendo lo contrario
 * de lo que está guardado.
 */
const avisosActivos = ref(props.avisos?.activos ?? false);

function cambiarAvisos(valor: boolean): void {
    avisosActivos.value = valor;
    router.put('/perfil/preferencias', { avisos_por_correo: valor }, { preserveScroll: true });
}

/* El tema se aplica y se guarda al elegirlo, igual que desde el menú. */
const { preferencia: temaActual, fijar: fijarTema } = useTema();
const opcionesTema = [
    { valor: 'claro', etiqueta: 'Claro' },
    { valor: 'oscuro', etiqueta: 'Oscuro' },
    { valor: 'sistema', etiqueta: 'El del sistema' },
];

function elegirTema(valor: string | undefined): void {
    if (valor === 'claro' || valor === 'oscuro' || valor === 'sistema') {
        fijarTema(valor);
    }
}

const inicio = useForm({ pagina_inicio: props.preferencias.paginaInicio ?? 'panel' });

function guardarInicio(): void {
    inicio.put('/perfil/preferencias', { preserveScroll: true });
}

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
            descripcion="Quién eres, cómo entras y qué puedes hacer. Statera entra en el alcance del propio SGSI: lo que se configura aquí es una medida de seguridad, no sólo una preferencia."
        />

        <motion.div
            :variants="variantesEntrada"
            initial="oculto"
            animate="visible"
            class="mx-auto w-full max-w-7xl space-y-8"
        >
            <!--
                Cómo está protegida la cuenta, arriba y de un vistazo. Lo único
                que va mal de verdad —el segundo factor— lleva el único botón
                fuerte de la pantalla; lo demás se ordena por lectura debajo.
            -->
            <section aria-labelledby="proteccion" class="space-y-4 rounded-xl border bg-card p-5 sm:p-6">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="min-w-0 flex-1">
                        <h2 id="proteccion" class="text-base font-semibold">Cómo está protegida tu cuenta</h2>
                        <p class="mt-1 max-w-2xl text-sm text-pretty text-muted-foreground">
                            Esta cuenta ve el inventario, los riesgos y las evidencias de la organización.
                            <template v-if="sinSegundoFactor">
                                Una contraseña robada no debería bastar para llegar a todo eso.
                            </template>
                        </p>
                    </div>

                    <Button v-if="sinSegundoFactor" @click="activar">
                        <ShieldAlertIcon />
                        Activar el segundo factor
                    </Button>
                </div>

                <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <li
                        v-for="casilla in proteccion"
                        :key="casilla.titulo"
                        class="flex gap-2.5 rounded-lg p-3"
                        :class="casilla.tono === 'mal' ? 'bg-destructive/5 ring-1 ring-destructive/30 ring-inset' : 'bg-superficie'"
                    >
                        <component
                            :is="iconoTono[casilla.tono]"
                            class="mt-0.5 size-4 shrink-0"
                            :class="colorTono[casilla.tono]"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <p class="text-sm font-medium" :class="casilla.tono === 'mal' ? 'text-destructive' : undefined">
                                {{ casilla.titulo }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ casilla.detalle }}</p>
                        </div>
                    </li>
                </ul>
            </section>

            <div class="grid gap-8 lg:grid-cols-[12.5rem_minmax(0,1fr)]">
                <!--
                    Nueve bloques ya no se recorren desplazando a ciegas. El
                    índice sólo aparece donde cabe; por debajo de `lg` la página
                    se lee de arriba abajo, que con una columna es lo natural.
                -->
                <nav aria-label="Secciones de la cuenta" class="hidden lg:block">
                    <ul class="sticky top-6 space-y-0.5">
                        <li v-for="seccion in secciones" :key="seccion.id">
                            <a
                                :href="`#${seccion.id}`"
                                @click.prevent="irA(seccion.id)"
                                class="flex h-8 items-center gap-2 rounded-md px-2.5 text-sm font-medium transition-colors"
                                :class="
                                    seccionVisible === seccion.id
                                        ? 'bg-accent text-accent-foreground'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                "
                                :aria-current="seccionVisible === seccion.id ? 'location' : undefined"
                            >
                                {{ seccion.titulo }}
                                <span
                                    v-if="seccion.pendiente"
                                    class="ml-auto size-2 rounded-full bg-destructive"
                                    aria-label="pendiente"
                                />
                            </a>
                        </li>
                    </ul>
                </nav>

                <div class="min-w-0 space-y-6">
                    <!-- ── Identidad ──────────────────────────────────────── -->
                    <div id="identidad" class="scroll-mt-6">
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


                            <!-- Lo que no se cambia aquí, pero se quiere ver:
                                 el rol y el puesto los decide la organización. -->
                            <dl v-if="ficha" class="grid gap-x-6 gap-y-3 rounded-lg bg-superficie px-4 py-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <dt class="text-xs text-muted-foreground">Rol</dt>
                                    <dd class="mt-0.5 font-medium">{{ ficha.rol ?? 'Sin rol' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">Puesto</dt>
                                    <dd class="mt-0.5 font-medium">{{ ficha.puesto ?? 'Sin puesto asignado' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">Organización</dt>
                                    <dd class="mt-0.5 font-medium">{{ ficha.organizacion ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">Cuenta activa desde</dt>
                                    <dd class="mt-0.5 font-medium">{{ fechaLegible(ficha.desde) }}</dd>
                                </div>
                            </dl>

                            <div class="flex justify-end">
                                <Button variant="outline" :disabled="datos.processing" @click="guardarDatos">
                                    {{ datos.processing ? 'Guardando…' : 'Guardar' }}
                                </Button>
                            </div>
                        </SeccionFormulario>
                    </div>

                    <!-- ── Contraseña ─────────────────────────────────────── -->
                    <div id="contrasena" class="scroll-mt-6">
                        <SeccionFormulario
                            titulo="Contraseña"
                            :ayuda="
                                ficha?.passwordCambiadaEn
                                    ? `Cambiada por última vez el ${fechaLegible(ficha.passwordCambiadaEn)} (${hace(diasDesde(ficha.passwordCambiadaEn))}). Se pide la actual para que una sesión olvidada abierta no pueda cambiarla.`
                                    : 'Se pide la actual para que una sesión olvidada abierta no pueda cambiarla.'
                            "
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
                            <Button variant="outline" :disabled="password.processing" @click="cambiarPassword">
                                {{ password.processing ? 'Guardando…' : 'Cambiar la contraseña' }}
                            </Button>
                        </div>
                        </SeccionFormulario>
                    </div>

                    <!-- ── Segundo factor ─────────────────────────────────── -->
                    <div id="dos-pasos" class="scroll-mt-6">
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
                            <Button v-if="dosFactores.confirmado || dosFactores.pendiente" as-child variant="outline">
                                <Link href="/perfil/dos-factores">
                                    {{ secreto ? 'Recargar el código' : 'Ver el código y los de recuperación' }}
                                </Link>
                            </Button>

                            <Button v-if="sinSegundoFactor" variant="outline" @click="activar">Activar</Button>
                            <Button v-else variant="ghost" @click="desactivar">Desactivar</Button>
                        </div>
                        </SeccionFormulario>
                    </div>

                    <!-- ── Passkeys ───────────────────────────────────────── -->
                    <div id="passkeys" class="scroll-mt-6">
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
                    </div>

                    <!-- ── Sesiones y accesos ─────────────────────────────── -->
                    <div id="sesiones" class="scroll-mt-6">
                        <SeccionFormulario
                            titulo="Sesiones y accesos"
                            ayuda="Dónde tienes la cuenta abierta ahora y las últimas veces que se ha entrado. Si no reconoces algo, cierra esa sesión y cambia la contraseña."
                        >
                            <div class="space-y-2">
                                <p class="text-sm font-medium">Abiertas ahora</p>
                                <ul class="divide-y rounded-xl border bg-card">
                                    <li
                                        v-for="sesion in sesiones"
                                        :key="sesion.clave"
                                        class="flex flex-wrap items-center gap-3 px-4 py-3"
                                    >
                                        <MonitorIcon class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium">{{ sesion.navegador }} en {{ sesion.sistema }}</p>
                                            <p class="mt-0.5 text-xs text-muted-foreground">
                                                <span v-if="sesion.ip" class="cifra">{{ sesion.ip }}</span>
                                                <template v-if="sesion.ip"> · </template>
                                                {{ sesion.actual ? 'la que estás usando' : `última actividad ${formatoFechaHora.format(new Date(sesion.ultimaActividad))}` }}
                                            </p>
                                        </div>
                                        <Badge v-if="sesion.actual" variant="outline">Este navegador</Badge>
                                        <Button v-else variant="ghost" size="sm" @click="cerrando = sesion">Cerrar</Button>
                                    </li>
                                    <!-- Con el driver de sesión en base de datos siempre
                                         hay al menos ésta; sin él, la tabla está vacía. -->
                                    <li v-if="sesiones.length === 0" class="px-4 py-3 text-sm text-muted-foreground">
                                        No hay registro de sesiones en este entorno.
                                    </li>
                                </ul>

                                <Button v-if="hayOtrasSesiones" variant="outline" size="sm" @click="cerrando = null">
                                    Cerrar las demás sesiones
                                </Button>
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-medium">Últimos accesos</p>
                                <ul v-if="accesos.length > 0" class="divide-y rounded-xl border bg-card text-sm">
                                    <li
                                        v-for="(acceso, indice) in accesos"
                                        :key="`${acceso.fecha}-${indice}`"
                                        class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 px-4 py-2.5 sm:grid-cols-[12rem_minmax(0,1fr)_auto]"
                                    >
                                        <span>{{ formatoFechaHora.format(new Date(acceso.fecha)) }}</span>
                                        <span class="cifra order-3 col-span-2 text-xs text-muted-foreground sm:order-none sm:col-span-1 sm:text-sm">
                                            {{ acceso.ip ?? 'dirección desconocida' }}
                                        </span>
                                        <Badge
                                            :variant="acceso.correcto ? 'secondary' : 'destructive'"
                                            :class="acceso.correcto ? 'bg-estado-implantado-suave text-estado-implantado' : undefined"
                                        >
                                            <CheckIcon v-if="acceso.correcto" data-icon="inline-start" />
                                            <XIcon v-else data-icon="inline-start" />
                                            {{ acceso.correcto ? 'Correcto' : 'Contraseña incorrecta' }}
                                        </Badge>
                                    </li>
                                </ul>
                                <p v-else class="text-sm text-muted-foreground">Todavía no hay accesos registrados.</p>
                            </div>
                        </SeccionFormulario>
                    </div>

                    <!-- ── Avisos por correo ──────────────────────────────── -->
                    <div id="avisos" class="scroll-mt-6">
                        <SeccionFormulario
                            titulo="Avisos por correo"
                            ayuda="Lo que te llega fuera de la herramienta. Es un resumen diario de lo que vence y de lo que ya se ha pasado de fecha, y sólo sale si hay algo que contar."
                        >
                            <CampoSwitch
                                v-if="avisos"
                                :model-value="avisosActivos"
                                nombre="avisos_por_correo"
                                etiqueta="Recibir el resumen diario de vencimientos"
                                ayuda="Evidencias que caducan, tareas que vencen, revisiones documentales, formación y obligaciones del calendario."
                                @update:model-value="cambiarAvisos"
                            />
                            <p v-else class="text-sm text-muted-foreground">
                                El resumen diario le llega a quien tiene el rol de responsable de seguridad, que es quien
                                responde de toda la organización. Lo tuyo lo ves al entrar, en el menú de tu cuenta.
                            </p>
                        </SeccionFormulario>
                    </div>

                    <!-- ── Preferencias ───────────────────────────────────── -->
                    <div id="preferencias" class="scroll-mt-6">
                        <SeccionFormulario
                            titulo="Preferencias"
                            ayuda="Se guardan en tu cuenta y te siguen de un navegador a otro."
                        >
                            <CampoOpciones
                                :model-value="temaActual"
                                nombre="tema"
                                etiqueta="Tema"
                                :opciones="opcionesTema"
                                ayuda="Se aplica al elegirlo."
                                @update:model-value="elegirTema"
                            />

                            <CampoSelect
                                v-model="inicio.pagina_inicio"
                                nombre="pagina_inicio"
                                etiqueta="Al entrar, abrir"
                                :opciones="preferencias.paginasInicio"
                                :error="inicio.errors.pagina_inicio"
                                ayuda="«Mis tareas» es el plan de acción con lo tuyo filtrado."
                            />

                            <div class="flex justify-end">
                                <Button variant="outline" :disabled="inicio.processing || !inicio.isDirty" @click="guardarInicio">
                                    {{ inicio.processing ? 'Guardando…' : 'Guardar' }}
                                </Button>
                            </div>
                        </SeccionFormulario>
                    </div>

                    <!-- ── Permisos ───────────────────────────────────────── -->
                    <div id="permisos" class="scroll-mt-6">
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
                    </div>

                    <!-- ── Tus datos ──────────────────────────────────────── -->
                    <div id="datos" class="scroll-mt-6">
                        <SeccionFormulario
                            titulo="Tus datos"
                            ayuda="Lo que la herramienta guarda de tu cuenta, y qué pasa con ello cuando te vas."
                        >
                            <div class="flex flex-wrap items-center gap-3 rounded-xl border bg-card px-4 py-3">
                                <DownloadIcon class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">Descargar una copia</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        Tu ficha, tus preferencias, tus accesos y lo que has firmado o acusado, en JSON.
                                        Sin contraseñas ni secretos.
                                    </p>
                                </div>
                                <Button as-child variant="outline" size="sm">
                                    <a href="/perfil/mis-datos" download>Descargar</a>
                                </Button>
                            </div>

                            <!-- Lo que dice `personas.md` del punto 36, sin
                                 prometer más: la cuenta no se seudonimiza. -->
                            <p class="max-w-2xl text-sm text-pretty text-muted-foreground">
                                Una cuenta no se borra cuando alguien se va: se desactiva, porque su nombre firma el
                                histórico —quién aprobó un documento, quién cambió un estado— y un auditor tiene que
                                poder leerlo. Si además constas en la plantilla, esa ficha sigue el plazo de retención
                                que fije la organización.
                            </p>
                        </SeccionFormulario>
                    </div>
                </div>
            </div>
        </motion.div>

        <Dialog v-model:open="dialogoCierre">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{ cerrando ? `Cerrar la sesión de ${cerrando.navegador} en ${cerrando.sistema}` : 'Cerrar las demás sesiones' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            cerrando
                                ? 'Ese navegador tendrá que volver a entrar con la contraseña.'
                                : 'Todos los navegadores menos éste tendrán que volver a entrar con la contraseña.'
                        }}
                        Si no la reconoces, cambia también la contraseña.
                    </DialogDescription>
                </DialogHeader>

                <CampoPassword
                    v-model="cierre.password"
                    nombre="password"
                    etiqueta="Tu contraseña"
                    autocomplete="current-password"
                    :error="cierre.errors.password"
                    requerido
                    @keydown.enter="cerrarSesiones"
                />

                <DialogFooter>
                    <Button variant="outline" @click="dialogoCierre = false">Cancelar</Button>
                    <Button :disabled="cierre.processing || cierre.password === ''" @click="cerrarSesiones">
                        {{ cierre.processing ? 'Cerrando…' : 'Cerrar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
