<script setup lang="ts">
import AvatarUsuario from '@/components/AvatarUsuario.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { useRecorrido } from '@/composables/useRecorrido';
import { useTema, type PreferenciaTema } from '@/composables/useTema';
import { formatoFecha, formatoFechaHora } from '@/lib/celdas';
import type { UsuarioAutenticado } from '@/types';
import { Link, router, useHttp } from '@inertiajs/vue3';
import {
    BellIcon,
    ChevronDownIcon,
    ClockIcon,
    EyeIcon,
    ListChecksIcon,
    LogOutIcon,
    MonitorIcon,
    MoonIcon,
    PenLineIcon,
    RouteIcon,
    ServerIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    SunIcon,
    UserRoundCogIcon,
} from '@lucide/vue';
import { computed, ref, watch, type Component } from 'vue';

/**
 * El menú de la cuenta, arriba a la derecha.
 *
 * Contesta tres preguntas que no son del trabajo sino de quien lo hace: **qué
 * hay a mi nombre**, **cómo está protegida mi cuenta** y **cuándo entré la
 * última vez**. La tercera es la que delata un acceso que no has hecho tú, y
 * por eso va al pie y con los intentos fallidos al lado.
 *
 * **Lo pide al abrirse** (`/perfil/menu`) y no viaja con cada página: son cinco
 * consultas para un menú que casi nunca se abre. Mientras llega se pinta el
 * hueco; lo que ya se sabe —nombre, correo, segundo factor— sale al instante
 * de los props compartidos.
 *
 * El tema vive aquí y no en un botón suelto de la cabecera: es una preferencia
 * de la persona, como todo lo demás de este menú.
 */
const props = defineProps<{
    usuario: UsuarioAutenticado;
    organizacion: string | null;
}>();

interface DatosMenu {
    rol: string | null;
    tareas: { abiertas: number; vencidas: number; url: string } | null;
    porLeer: { total: number; url: string };
    porFirmar: { total: number; url: string } | null;
    avisos: { activos: boolean } | null;
    alcance: { hasta: string | null; sistemas: string[] } | null;
    entradaAnterior: { fecha: string; ip: string | null; fallidosDesde: number } | null;
}

const abierto = ref(false);
const datos = ref<DatosMenu | null>(null);
const peticion = useHttp<Record<string, never>, DatosMenu>({});

/* Cada vez que se abre: una cifra de hace una hora en un menú que dice «lo tuyo» miente. */
watch(abierto, async (ahora) => {
    if (!ahora) {
        return;
    }

    try {
        datos.value = await peticion.get('/perfil/menu');
    } catch {
        /* Sin cifras el menú sigue sirviendo: se quedan las de la última vez. */
    }
});

const { preferencia, fijar } = useTema();
const { abrir: abrirRecorrido } = useRecorrido();

const temas: { valor: PreferenciaTema; etiqueta: string; icono: Component }[] = [
    { valor: 'claro', etiqueta: 'Claro', icono: SunIcon },
    { valor: 'oscuro', etiqueta: 'Oscuro', icono: MoonIcon },
    { valor: 'sistema', etiqueta: 'El del sistema', icono: MonitorIcon },
];

/*
 * `@select.prevent` en las tres: elegir tema no cierra el menú, para que se
 * vea el cambio y se pueda volver atrás sin abrirlo otra vez.
 */
const elegirTema = (valor: unknown): void => {
    const elegido = temas.find((tema) => tema.valor === valor);

    if (elegido) {
        fijar(elegido.valor);
    }
};

/** Lo que tiene cifra: si todo está a cero, el bloque dice que no hay nada. */
const pendientes = computed(() => {
    const d = datos.value;

    if (d === null) {
        return [];
    }

    return [
        d.tareas && d.tareas.abiertas > 0
            ? { clave: 'tareas', titulo: 'Mis tareas', icono: ListChecksIcon, url: d.tareas.url, total: d.tareas.abiertas, vencidas: d.tareas.vencidas }
            : null,
        d.porLeer.total > 0
            ? { clave: 'leer', titulo: 'Documentos por leer', icono: EyeIcon, url: d.porLeer.url, total: d.porLeer.total, vencidas: 0 }
            : null,
        d.porFirmar && d.porFirmar.total > 0
            ? { clave: 'firmar', titulo: 'Pendientes de firma', icono: PenLineIcon, url: d.porFirmar.url, total: d.porFirmar.total, vencidas: 0 }
            : null,
    ].filter((fila) => fila !== null);
});

/** Días enteros de hoy a una fecha `AAAA-MM-DD`, contando en local. */
function diasHasta(fecha: string): number {
    const [anio, mes, dia] = fecha.split('-').map(Number);
    const hoy = new Date();
    const destino = new Date(anio, mes - 1, dia);

    return Math.round((destino.getTime() - new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate()).getTime()) / 86_400_000);
}

const caducidad = computed(() => {
    const hasta = datos.value?.alcance?.hasta;

    if (!hasta) {
        return null;
    }

    const dias = diasHasta(hasta);
    const [anio, mes, dia] = hasta.split('-').map(Number);

    return {
        fecha: formatoFecha.format(new Date(anio, mes - 1, dia)),
        resto: dias === 0 ? 'Hoy es el último día.' : dias === 1 ? 'Queda 1 día.' : `Quedan ${dias} días.`,
        cerca: dias <= 7,
    };
});

const salir = (): void => router.post('/logout');
</script>

<template>
    <DropdownMenu v-model:open="abierto">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="sm" class="gap-2" aria-label="Menú de la cuenta" data-recorrido="menu-cuenta">
                <span class="relative flex">
                    <AvatarUsuario :nombre="usuario.nombre" :foto="usuario.foto" tamano="sm" clase="text-xs" />
                    <!-- El punto es una señal, no el mensaje: el mensaje está dentro,
                         con icono y texto. Sin él nadie abre el menú para enterarse. -->
                    <span
                        v-if="!usuario.dosFactores"
                        aria-hidden="true"
                        class="absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full border-2 border-superficie bg-destructive"
                    />
                </span>
                <span class="hidden max-w-48 truncate sm:inline">{{ usuario.nombre }}</span>
                <ChevronDownIcon class="hidden size-3.5 text-muted-foreground sm:block" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-80">
            <!-- ── Quién ─────────────────────────────────────────────────── -->
            <DropdownMenuLabel class="flex items-start gap-3 px-2 py-2.5 font-normal">
                <AvatarUsuario :nombre="usuario.nombre" :foto="usuario.foto" clase="size-10 text-sm" />
                <span class="min-w-0 space-y-0.5">
                    <span class="block truncate text-sm font-semibold text-foreground">{{ usuario.nombre }}</span>
                    <span class="block truncate text-xs text-muted-foreground">{{ usuario.email }}</span>
                    <span class="flex flex-wrap gap-1.5 pt-1.5">
                        <Badge v-if="datos?.rol" variant="outline">{{ datos.rol }}</Badge>
                        <Skeleton v-else-if="peticion.processing" class="h-5 w-24 rounded-full" />
                        <Badge v-if="organizacion" variant="secondary" class="max-w-full truncate">{{ organizacion }}</Badge>
                    </span>
                </span>
            </DropdownMenuLabel>

            <!-- ── Cómo está protegida ───────────────────────────────────── -->
            <DropdownMenuItem v-if="!usuario.dosFactores" as-child>
                <Link
                    href="/perfil#dos-pasos"
                    class="mx-1 mb-1 items-start! gap-2.5 rounded-md border border-destructive/30 bg-destructive/5 px-3! py-2.5! focus:bg-destructive/10!"
                >
                    <ShieldAlertIcon class="mt-0.5 size-4 text-destructive" />
                    <span class="space-y-0.5">
                        <span class="block text-sm font-semibold text-destructive">Sin segundo factor</span>
                        <span class="block text-xs text-foreground">Tu cuenta entra sólo con la contraseña.</span>
                        <span class="block pt-1 text-sm font-medium text-primary">Activarlo ahora</span>
                    </span>
                </Link>
            </DropdownMenuItem>
            <p v-else class="flex items-center gap-2 px-2 pb-1.5 text-xs text-muted-foreground">
                <ShieldCheckIcon class="size-3.5 text-estado-implantado" />
                <span><span class="font-medium text-estado-implantado">Segundo factor activo</span></span>
            </p>

            <!-- ── El alcance del auditor: hasta cuándo y sobre qué ───────── -->
            <div v-if="datos?.alcance" class="mx-1 mb-1 space-y-2 rounded-md border bg-superficie px-3 py-2.5">
                <p v-if="caducidad" class="flex items-start gap-2.5">
                    <ClockIcon class="mt-0.5 size-4 shrink-0" :class="caducidad.cerca ? 'text-destructive' : 'text-primary'" />
                    <span>
                        <span class="block text-sm font-semibold">Acceso hasta el {{ caducidad.fecha }}</span>
                        <span class="block text-xs text-muted-foreground">
                            {{ caducidad.resto }} Después la cuenta deja de entrar.
                        </span>
                    </span>
                </p>
                <p v-if="datos.alcance.sistemas.length > 0" class="flex items-start gap-2.5">
                    <ServerIcon class="mt-0.5 size-4 shrink-0 text-primary" />
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold">
                            {{ datos.alcance.sistemas.length === 1 ? 'Ves 1 sistema' : `Ves ${datos.alcance.sistemas.length} sistemas` }}
                        </span>
                        <span class="block text-xs text-muted-foreground">{{ datos.alcance.sistemas.join(' · ') }}</span>
                    </span>
                </p>
            </div>

            <!-- ── Lo tuyo ────────────────────────────────────────────────── -->
            <DropdownMenuLabel class="pt-2 text-xs font-medium text-muted-foreground">Lo tuyo</DropdownMenuLabel>

            <template v-if="datos === null">
                <div class="space-y-2 px-2 py-1.5" aria-hidden="true">
                    <Skeleton class="h-5 w-full" />
                    <Skeleton class="h-5 w-3/4" />
                </div>
            </template>

            <template v-else-if="pendientes.length > 0">
                <DropdownMenuItem v-for="fila in pendientes" :key="fila.clave" as-child>
                    <Link :href="fila.url">
                        <component :is="fila.icono" class="size-4" :class="fila.clave === 'firmar' ? 'text-acento' : ''" />
                        {{ fila.titulo }}
                        <span class="ml-auto flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span v-if="fila.vencidas > 0" class="font-medium text-destructive">
                                {{ fila.vencidas === 1 ? '1 vencida' : `${fila.vencidas} vencidas` }} ·
                            </span>
                            <span class="cifra">{{ fila.total }}</span>
                        </span>
                    </Link>
                </DropdownMenuItem>
            </template>

            <p v-else class="px-2 pb-1.5 text-sm text-muted-foreground">Nada pendiente a tu nombre.</p>

            <DropdownMenuSeparator />

            <!-- ── La cuenta ──────────────────────────────────────────────── -->
            <DropdownMenuItem as-child>
                <Link href="/perfil">
                    <UserRoundCogIcon class="size-4" />
                    Mi cuenta
                </Link>
            </DropdownMenuItem>

            <DropdownMenuItem v-if="datos?.avisos" as-child>
                <Link href="/perfil#avisos">
                    <BellIcon class="size-4" />
                    Avisos por correo
                    <span class="ml-auto text-xs text-muted-foreground">
                        {{ datos.avisos.activos ? 'Resumen diario' : 'Apagados' }}
                    </span>
                </Link>
            </DropdownMenuItem>

            <!--
                El tema como grupo de radio del propio menú y no como tres
                botones sueltos: así las flechas llegan a ellos igual que al
                resto de entradas, y el lector de pantalla anuncia cuál está
                marcada.
            -->
            <div class="flex items-center gap-2 px-2 py-1">
                <span id="menu-cuenta-tema" class="text-sm font-medium">Tema</span>
                <DropdownMenuRadioGroup
                    :model-value="preferencia"
                    aria-labelledby="menu-cuenta-tema"
                    class="ml-auto flex gap-0.5 rounded-md bg-muted p-0.5"
                    @update:model-value="elegirTema"
                >
                    <DropdownMenuRadioItem
                        v-for="tema in temas"
                        :key="tema.valor"
                        :value="tema.valor"
                        :aria-label="tema.etiqueta"
                        :title="tema.etiqueta"
                        class="h-7 w-8 justify-center rounded-sm p-0! text-muted-foreground data-[state=checked]:bg-card data-[state=checked]:text-foreground data-[state=checked]:shadow-sombra-1"
                        @select.prevent
                    >
                        <template #indicator-icon><span /></template>
                        <component :is="tema.icono" class="size-3.5" />
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
            </div>

            <DropdownMenuSeparator />

            <!-- El recorrido se ofrece solo una vez; a partir de ahí hay que
                 poder encontrarlo, y éste es el menú de lo que es del usuario
                 y no del trabajo. -->
            <DropdownMenuItem @select="abrirRecorrido()">
                <RouteIcon class="size-4" />
                Recorrido guiado
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem @select="salir">
                <LogOutIcon class="size-4" />
                Cerrar sesión
            </DropdownMenuItem>

            <p v-if="datos?.entradaAnterior" class="px-2 pt-1 pb-1.5 text-xs text-muted-foreground">
                Entrada anterior: {{ formatoFechaHora.format(new Date(datos.entradaAnterior.fecha))
                }}<template v-if="datos.entradaAnterior.ip">, desde <span class="cifra">{{ datos.entradaAnterior.ip }}</span></template>.
            </p>

            <!-- Un intento fallido es lo único de este pie que pide hacer algo,
                 y por eso es una entrada del menú y no texto: se llega con el
                 teclado. -->
            <DropdownMenuItem v-if="datos?.entradaAnterior && datos.entradaAnterior.fallidosDesde > 0" as-child>
                <Link href="/perfil#sesiones" class="text-xs! font-medium text-destructive">
                    <ShieldAlertIcon class="size-3.5 text-destructive" />
                    {{
                        datos.entradaAnterior.fallidosDesde === 1
                            ? '1 intento fallido desde entonces'
                            : `${datos.entradaAnterior.fallidosDesde} intentos fallidos desde entonces`
                    }}
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
