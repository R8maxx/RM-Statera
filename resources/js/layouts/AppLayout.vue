<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Toaster } from '@/components/ui/sonner';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ClipboardCheckIcon,
    LayoutDashboardIcon,
    LogOutIcon,
    MoonIcon,
    ServerIcon,
    SunIcon,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
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

const navegacion = [
    { titulo: 'Panel', href: '/panel', icono: LayoutDashboardIcon },
    { titulo: 'Sistemas', href: '/sistemas', icono: ServerIcon },
    { titulo: 'Implantaciones', href: '/implantaciones', icono: ClipboardCheckIcon },
];

const rutaActual = computed(() => new URL(pagina.url, 'http://x').pathname);
const enSeccion = (href: string): boolean => rutaActual.value === href || rutaActual.value.startsWith(`${href}/`);

/* Tema: la preferencia vive en el navegador, no en el servidor. */
const oscuro = ref(false);

onMounted(() => {
    oscuro.value = document.documentElement.classList.contains('dark');
});

watch(oscuro, (valor) => {
    document.documentElement.classList.toggle('dark', valor);
    try {
        localStorage.setItem('statera.tema', valor ? 'oscuro' : 'claro');
    } catch {
        /* almacenamiento no disponible: el tema dura lo que la pestaña */
    }
});

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

    <div class="flex min-h-screen">
        <aside class="hidden w-60 shrink-0 flex-col border-r bg-card md:flex">
            <div class="border-b px-5 py-4">
                <p class="text-lg font-semibold tracking-tight">Statera</p>
                <p class="text-[11px] text-muted-foreground">un producto de RM Technology</p>
            </div>

            <nav class="flex-1 space-y-1 p-3">
                <Link
                    v-for="entrada in navegacion"
                    :key="entrada.href"
                    :href="entrada.href"
                    class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    :class="
                        enSeccion(entrada.href)
                            ? 'bg-accent text-accent-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    <component :is="entrada.icono" class="size-4" />
                    {{ entrada.titulo }}
                </Link>
            </nav>

            <div v-if="organizacion" class="border-t px-5 py-3">
                <p class="text-[11px] uppercase tracking-wide text-muted-foreground">Organización</p>
                <p class="truncate text-sm font-medium">{{ organizacion.nombre }}</p>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-14 items-center justify-between gap-4 border-b bg-background px-6">
                <h1 class="truncate text-base font-semibold tracking-tight">{{ titulo }}</h1>

                <div class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="oscuro ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
                        @click="oscuro = !oscuro"
                    >
                        <SunIcon v-if="oscuro" />
                        <MoonIcon v-else />
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="sm" class="gap-2">
                                <span
                                    class="flex size-6 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-primary-foreground"
                                >
                                    {{ iniciales }}
                                </span>
                                <span class="hidden sm:inline">{{ usuario?.nombre }}</span>
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuLabel>
                                <p class="text-sm font-medium">{{ usuario?.nombre }}</p>
                                <p class="text-xs font-normal text-muted-foreground">{{ usuario?.email }}</p>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @select="salir">
                                <LogOutIcon class="size-4" />
                                Cerrar sesión
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </header>

            <main class="min-w-0 flex-1 p-6">
                <slot />
            </main>
        </div>

        <Toaster position="top-right" rich-colors />
    </div>
</template>
