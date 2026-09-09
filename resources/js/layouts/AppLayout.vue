<script setup lang="ts">
import Logotipo from '@/components/Logotipo.vue';
import PaletaComandos from '@/components/PaletaComandos.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Toaster } from '@/components/ui/sonner';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { usePaletaComandos } from '@/composables/usePaletaComandos';
import { useTema } from '@/composables/useTema';
import { entradaDe, esSeccionActiva, navegacion } from '@/lib/navegacion';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    BuildingIcon,
    ChevronRightIcon,
    LogOutIcon,
    MenuIcon,
    MonitorIcon,
    MoonIcon,
    PanelLeftIcon,
    SearchIcon,
    ShieldCheckIcon,
    SunIcon,
    UserRoundCogIcon,
} from '@lucide/vue';
import { useStorage } from '@vueuse/core';
import { MotionConfig, motion } from 'motion-v';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import 'vue-sonner/style.css';

defineProps<{ titulo: string }>();

const pagina = usePage();

const usuario = computed(() => pagina.props.auth.usuario);
const organizacion = computed(() => pagina.props.organizacion);
const iniciales = computed(() =>
    (usuario.value?.nombre ?? '?')
        .split(' ')
        .slice(0, 2)
        .map((parte) => parte.charAt(0).toUpperCase())
        .join(''),
);

const rutaActual = computed(() => new URL(pagina.url, 'http://x').pathname);
const seccion = computed(() => entradaDe(rutaActual.value));

/* La preferencia de sidebar es del navegador, como la del tema. */
const plegado = useStorage('statera.sidebar.plegado', false);
const menuMovil = ref(false);

const { preferencia, esOscuro, fijar } = useTema();
const { abrir: abrirPaleta } = usePaletaComandos();
const { variantesEntrada } = useMovimientoReducido();

/*
 * El flash llega por el canal propio de Inertia v3, no como prop compartido.
 * Un prop se reenvía en cada recarga parcial y el aviso volvía a saltar cada vez
 * que se filtraba o se paginaba; el evento se emite una sola vez y no se guarda
 * en el historial.
 */
let dejarDeEscuchar: (() => void) | undefined;

onMounted(() => {
    dejarDeEscuchar = router.on('flash', (evento) => {
        const flash = evento.detail.flash;

        if (typeof flash.exito === 'string') {
            toast.success(flash.exito);
        }

        if (typeof flash.error === 'string') {
            toast.error(flash.error);
        }
    });
});

onUnmounted(() => dejarDeEscuchar?.());

const salir = (): void => router.post('/logout');
</script>

<template>
    <Head :title="titulo" />

    <!-- `reduced-motion="user"` desactiva de raíz lo que anime motion-v cuando
         el sistema lo pide. Lo demás lo cubre el bloque global de `app.css`. -->
    <MotionConfig reduced-motion="user">
        <TooltipProvider :delay-duration="400">
            <div class="flex min-h-[100dvh] bg-background">
                <!--
                    Saltar al contenido. Con un sidebar de diecinueve módulos,
                    llegar a la tabla con el teclado significaba tabular por toda
                    la navegación en cada carga de página.
                -->
                <a
                    href="#contenido"
                    class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-primary focus:px-3 focus:py-2 focus:text-sm focus:font-medium focus:text-primary-foreground"
                >
                    Saltar al contenido
                </a>

                <!-- ── Sidebar de escritorio ──────────────────────────────── -->
                <aside
                    class="hidden shrink-0 flex-col border-r bg-superficie transition-[width] duration-300 ease-marca md:flex"
                    :class="plegado ? 'w-[4.25rem]' : 'w-60'"
                >
                    <div class="flex h-16 items-center border-b px-4">
                        <Link href="/panel" class="flex min-w-0 items-center rounded-md">
                            <Logotipo :variante="plegado ? 'simbolo' : 'completo'" :respaldo="!plegado" />
                        </Link>
                    </div>

                    <nav class="flex-1 space-y-6 overflow-y-auto p-3">
                        <div v-for="grupo in navegacion" :key="grupo.titulo" class="space-y-1">
                            <p
                                v-if="!plegado"
                                class="px-3 pb-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ grupo.titulo }}
                            </p>

                            <Tooltip v-for="entrada in grupo.entradas" :key="entrada.href">
                                <TooltipTrigger as-child>
                                    <Link
                                        :href="entrada.href"
                                        class="relative flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors duration-150"
                                        :class="[
                                            esSeccionActiva(entrada.href, rutaActual)
                                                ? 'bg-accent text-accent-foreground'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                            plegado && 'justify-center px-0',
                                        ]"
                                        :aria-current="esSeccionActiva(entrada.href, rutaActual) ? 'page' : undefined"
                                    >
                                        <span
                                            v-if="esSeccionActiva(entrada.href, rutaActual)"
                                            class="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-primary"
                                            aria-hidden="true"
                                        />
                                        <component :is="entrada.icono" class="size-4 shrink-0" />
                                        <span v-if="!plegado" class="truncate">{{ entrada.titulo }}</span>
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent v-if="plegado" side="right">
                                    {{ entrada.titulo }}
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    </nav>

                    <div class="border-t p-3">
                        <!-- La organización deja de ser texto muerto: es el punto
                             de conmutación que el modelo multi-tenant ya soporta. -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-left transition-colors hover:bg-muted"
                                    :class="plegado && 'justify-center px-0'"
                                >
                                    <span
                                        class="flex size-7 shrink-0 items-center justify-center rounded-md bg-accent text-accent-foreground"
                                    >
                                        <BuildingIcon class="size-3.5" />
                                    </span>
                                    <span v-if="!plegado" class="min-w-0 flex-1">
                                        <span class="block text-[11px] text-muted-foreground">Organización</span>
                                        <span class="block truncate text-sm font-medium">
                                            {{ organizacion?.nombre ?? 'Sin contexto' }}
                                        </span>
                                    </span>
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-56">
                                <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">
                                    Organización activa
                                </DropdownMenuLabel>
                                <DropdownMenuItem v-if="organizacion" disabled>
                                    <ShieldCheckIcon class="size-4 text-primary" />
                                    {{ organizacion.nombre }}
                                </DropdownMenuItem>
                                <DropdownMenuItem v-else disabled>Sin contexto de organización</DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Button
                            variant="ghost"
                            size="sm"
                            class="mt-1 w-full justify-center text-muted-foreground"
                            :aria-label="plegado ? 'Desplegar la navegación' : 'Plegar la navegación'"
                            @click="plegado = !plegado"
                        >
                            <PanelLeftIcon class="size-4 transition-transform duration-300" :class="plegado && 'rotate-180'" />
                            <span v-if="!plegado">Plegar</span>
                        </Button>
                    </div>
                </aside>

                <!-- ── Columna principal ──────────────────────────────────── -->
                <div class="flex min-w-0 flex-1 flex-col">
                    <header
                        class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b bg-background/85 px-4 backdrop-blur-sm sm:px-6"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <!-- La navegación en móvil no existía: por debajo de
                                 768px el sidebar sencillamente desaparecía y no
                                 quedaba forma de cambiar de módulo. -->
                            <Sheet v-model:open="menuMovil">
                                <SheetTrigger as-child>
                                    <Button variant="ghost" size="icon-sm" class="md:hidden" aria-label="Abrir la navegación">
                                        <MenuIcon />
                                    </Button>
                                </SheetTrigger>
                                <SheetContent side="left" class="flex w-72 flex-col p-0">
                                    <SheetTitle class="sr-only">Navegación</SheetTitle>
                                    <SheetDescription class="sr-only">
                                        Los módulos de Statera.
                                    </SheetDescription>

                                    <div class="flex h-16 items-center border-b px-5">
                                        <Logotipo respaldo />
                                    </div>

                                    <nav class="flex-1 space-y-6 overflow-y-auto p-3">
                                        <div v-for="grupo in navegacion" :key="grupo.titulo" class="space-y-1">
                                            <p class="px-3 pb-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                                {{ grupo.titulo }}
                                            </p>
                                            <Link
                                                v-for="entrada in grupo.entradas"
                                                :key="entrada.href"
                                                :href="entrada.href"
                                                class="flex items-center gap-2.5 rounded-md px-3 py-2.5 text-sm font-medium transition-colors"
                                                :class="
                                                    esSeccionActiva(entrada.href, rutaActual)
                                                        ? 'bg-accent text-accent-foreground'
                                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                                "
                                                @click="menuMovil = false"
                                            >
                                                <component :is="entrada.icono" class="size-4" />
                                                {{ entrada.titulo }}
                                            </Link>
                                        </div>
                                    </nav>

                                    <div v-if="organizacion" class="mt-auto border-t px-5 py-3">
                                        <p class="text-[11px] text-muted-foreground">Organización</p>
                                        <p class="truncate text-sm font-medium">{{ organizacion.nombre }}</p>
                                    </div>
                                </SheetContent>
                            </Sheet>

                            <!-- Migas: dónde estoy dentro del mapa, no sólo cómo
                                 se llama la pantalla. -->
                            <nav aria-label="Ruta" class="flex min-w-0 items-center gap-1.5 text-sm">
                                <Link
                                    v-if="seccion && seccion.href !== rutaActual"
                                    :href="seccion.href"
                                    class="hidden shrink-0 rounded text-muted-foreground transition-colors hover:text-foreground sm:inline"
                                >
                                    {{ seccion.titulo }}
                                </Link>
                                <ChevronRightIcon
                                    v-if="seccion && seccion.href !== rutaActual"
                                    class="hidden size-3.5 shrink-0 text-muted-foreground/60 sm:block"
                                />
                                <span class="truncate font-medium">{{ titulo }}</span>
                            </nav>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                class="hidden gap-2 text-muted-foreground sm:flex"
                                @click="abrirPaleta"
                            >
                                <SearchIcon class="size-3.5" />
                                Buscar
                                <kbd class="cifra rounded border bg-muted px-1 text-[10px]">⌘K</kbd>
                            </Button>

                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon-sm" aria-label="Cambiar el tema">
                                        <SunIcon v-if="esOscuro" />
                                        <MoonIcon v-else />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-44">
                                    <DropdownMenuCheckboxItem
                                        :model-value="preferencia === 'claro'"
                                        @select="fijar('claro')"
                                    >
                                        <SunIcon class="size-4" />
                                        Claro
                                    </DropdownMenuCheckboxItem>
                                    <DropdownMenuCheckboxItem
                                        :model-value="preferencia === 'oscuro'"
                                        @select="fijar('oscuro')"
                                    >
                                        <MoonIcon class="size-4" />
                                        Oscuro
                                    </DropdownMenuCheckboxItem>
                                    <DropdownMenuCheckboxItem
                                        :model-value="preferencia === 'sistema'"
                                        @select="fijar('sistema')"
                                    >
                                        <MonitorIcon class="size-4" />
                                        El del sistema
                                    </DropdownMenuCheckboxItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="sm" class="gap-2">
                                        <span
                                            class="flex size-6 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-primary-foreground"
                                        >
                                            {{ iniciales }}
                                        </span>
                                        <span class="hidden truncate sm:inline">{{ usuario?.nombre }}</span>
                                    </Button>
                                </DropdownMenuTrigger>

                                <DropdownMenuContent align="end" class="w-60">
                                    <DropdownMenuLabel>
                                        <p class="text-sm font-medium">{{ usuario?.nombre }}</p>
                                        <p class="truncate text-xs font-normal text-muted-foreground">
                                            {{ usuario?.email }}
                                        </p>
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuLabel class="flex items-center gap-2 text-xs font-normal">
                                        <ShieldCheckIcon
                                            class="size-3.5"
                                            :class="usuario?.dosFactores ? 'text-estado-implantado' : 'text-muted-foreground'"
                                        />
                                        {{ usuario?.dosFactores ? 'Segundo factor activo' : 'Sin segundo factor' }}
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem as-child>
                                        <Link href="/perfil">
                                            <UserRoundCogIcon class="size-4" />
                                            Mi cuenta
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @select="salir">
                                        <LogOutIcon class="size-4" />
                                        Cerrar sesión
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </header>

                    <motion.main
                        id="contenido"
                        :key="rutaActual"
                        :variants="variantesEntrada"
                        initial="oculto"
                        animate="visible"
                        class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8"
                    >
                        <slot />
                    </motion.main>
                </div>

                <PaletaComandos />
                <Toaster position="top-right" rich-colors />
            </div>
        </TooltipProvider>
    </MotionConfig>
</template>
