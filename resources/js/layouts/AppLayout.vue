<script setup lang="ts">
import GrupoSidebar from '@/components/GrupoSidebar.vue';
import Logotipo from '@/components/Logotipo.vue';
import MenuCuenta from '@/components/MenuCuenta.vue';
import MenuOrganizacion from '@/components/MenuOrganizacion.vue';
import PaletaComandos from '@/components/PaletaComandos.vue';
import RecorridoGuiado from '@/components/RecorridoGuiado.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Toaster } from '@/components/ui/sonner';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { usePaletaComandos } from '@/composables/usePaletaComandos';
import { useRecorrido } from '@/composables/useRecorrido';
import { entradaDe, esSeccionActiva, navegacionPara, type GrupoNavegacion } from '@/lib/navegacion';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ChevronRightIcon,
    ChevronsUpDownIcon,
    MenuIcon,
    PanelLeftCloseIcon,
    PanelLeftOpenIcon,
    SearchIcon,
} from '@lucide/vue';
import { useStorage } from '@vueuse/core';
import { MotionConfig, motion } from 'motion-v';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import 'vue-sonner/style.css';

withDefaults(
    defineProps<{
        titulo: string;
        /**
         * Cuánto ancho usa la pantalla (DESIGN.md §5).
         *
         * `contenido` —panel, fichas, formularios— se queda en 1440 px y centrado
         * en el hueco que deja el sidebar: son pantallas que se leen, y a 1920 una
         * ficha estirada separa la etiqueta de su dato. `completo` —tablas,
         * tablero, calendario, grafos— usa todo el ancho, porque ahí cada
         * columna que cabe es una columna que no hay que desplazar.
         */
        ancho?: 'contenido' | 'completo';
    }>(),
    { ancho: 'contenido' },
);

const pagina = usePage();

const usuario = computed(() => pagina.props.auth.usuario);
const organizacion = computed(() => pagina.props.organizacion);

/**
 * Si la sesión tiene un permiso, para decidir qué se PINTA.
 *
 * Primer lector de `auth.permisos`, que `HandleInertiaRequests` comparte desde
 * el principio y que hasta ahora no usaba nadie en el cliente. Su comentario ya
 * decía para qué era: «el frontend solo decide qué pinta, nunca qué autoriza».
 * Sin esto, un enlace a una pantalla de un solo rol se le pintaría a los tres y
 * dos se llevarían un 403 — que es lo que ya le pasa a `/plantillas-documento`
 * y no hay por qué repetir.
 */
const puede = (permiso: string): boolean => pagina.props.auth.permisos.includes(permiso);

const rutaActual = computed(() => new URL(pagina.url, 'http://x').pathname);
const seccion = computed(() => entradaDe(rutaActual.value));

const { abrir: abrirPaleta } = usePaletaComandos();
const { abierto: recorridoAbierto } = useRecorrido();

/* La preferencia de sidebar es del navegador: es del puesto y no de la persona. */
const plegado = useStorage('statera.sidebar.plegado', false);

/*
 * Con el recorrido guiado en marcha el sidebar se enseña desplegado, lo haya
 * plegado quien lo haya plegado: sus pasos señalan entradas (`nav-sistemas`,
 * `nav-documentos`…) y en el riel sólo hay grupos.
 */
const compacto = computed(() => plegado.value && !recorridoAbierto.value);

/* Sólo lo que la sesión puede abrir: un enlace a un 403 no se pinta. */
const navegacionVisible = computed(() => navegacionPara(pagina.props.auth.permisos));

/*
 * El panel es la portada y va suelto, encima de los grupos: plegado dentro de
 * «Estado» habría que abrir un grupo para volver a casa. Es una decisión del
 * sidebar y no del mapa: la paleta y las migas lo siguen viendo en su grupo.
 */
const RUTA_INICIO = '/panel';
const inicio = computed(() =>
    navegacionVisible.value.flatMap((grupo) => grupo.entradas).find((entrada) => entrada.href === RUTA_INICIO),
);
const grupos = computed(() =>
    navegacionVisible.value
        .map((grupo) => ({ ...grupo, entradas: grupo.entradas.filter((entrada) => entrada.href !== RUTA_INICIO) }))
        .filter((grupo) => grupo.entradas.length > 0),
);

const entradaActual = (grupo: GrupoNavegacion): string | undefined =>
    grupo.entradas.find((entrada) => esSeccionActiva(entrada.href, rutaActual.value))?.titulo;

/*
 * Un grupo abierto cada vez: el de la pantalla actual al llegar, y el que se
 * pulse después. No se guarda en el navegador a propósito —cada pantalla abre
 * el suyo—, que es lo que deja el sidebar en siete filas en vez de treinta.
 */
const grupoDeLaRuta = computed(() => grupos.value.find((grupo) => entradaActual(grupo) !== undefined)?.titulo ?? null);
const grupoElegido = ref<string | null>(grupoDeLaRuta.value);

watch(grupoDeLaRuta, (titulo) => {
    grupoElegido.value = titulo;
});

/* Con el recorrido en marcha se abren todos: un foco sobre algo plegado no señala nada. */
const grupoAbierto = (grupo: GrupoNavegacion): boolean => recorridoAbierto.value || grupoElegido.value === grupo.titulo;

function alternarGrupo(grupo: GrupoNavegacion): void {
    grupoElegido.value = grupoElegido.value === grupo.titulo ? null : grupo.titulo;
}

/* Las iniciales de la organización, para cuando no ha subido logo. */
const inicialesOrganizacion = computed(
    () =>
        (organizacion.value?.nombre ?? '')
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((parte) => parte.charAt(0).toUpperCase())
            .join('') || '?',
);

const menuMovil = ref(false);

/**
 * El ancla que el recorrido guiado busca para cada módulo.
 *
 * Se deriva de la ruta en vez de escribirse entrada por entrada: `navegacion.ts`
 * es el mapa único de la aplicación y un módulo nuevo no tiene que acordarse de
 * declarar también su ancla. `/implantaciones` → `nav-implantaciones`.
 */
const anclaRecorrido = (href: string): string => `nav-${href.replace(/^\//, '').replace(/\//g, '-')}`;
const { variantesEntrada } = useMovimientoReducido();

/*
 * El flash llega por el canal propio de Inertia v3, no como prop compartido.
 * Un prop se reenvía en cada recarga parcial y el aviso volvía a saltar cada vez
 * que se filtraba o se paginaba; el evento se emite una sola vez y no se guarda
 * en el historial.
 */
let dejarDeEscuchar: (() => void) | undefined;
let dejarDeEscucharErrores: (() => void) | undefined;

/*
 * Un error de validación que no se ve en ninguna parte se anuncia.
 *
 * Las fichas cambian de estado, descartan o vinculan con `router.post` suelto,
 * y un 422 del `FormRequest` dejaba la pantalla igual: el botón no hacía nada y
 * nadie decía por qué. Añadir `onError` a cada una de las ~60 llamadas era
 * fiarse de que la siguiente se acuerde, así que el criterio es uno y está aquí:
 * **si el mensaje no aparece pintado en la pantalla, sale en un toast**. Un
 * formulario que ya lo pinta junto a su campo no lo duplica.
 *
 * Se comprueba en el `nextTick`: Inertia deja los errores en las props antes de
 * emitir el evento, y Vue los pinta en el repintado siguiente. No con
 * `requestAnimationFrame`, que no corre con la pestaña en segundo plano.
 */
function anunciarErroresSinSitio(errores: Record<string, string>): void {
    void nextTick(() => {
        // El `body` y no `#contenido`: un diálogo se pinta fuera, teletransportado.
        const visible = document.body.innerText;
        const sinSitio = Object.values(errores).find((mensaje) => !visible.includes(mensaje));

        if (sinSitio !== undefined) {
            toast.error(sinSitio);
        }
    });
}

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

    dejarDeEscucharErrores = router.on('error', (evento) => anunciarErroresSinSitio(evento.detail.errors));
});

onUnmounted(() => {
    dejarDeEscuchar?.();
    dejarDeEscucharErrores?.();
});

</script>

<template>
    <Head :title="titulo" />

    <!-- `reduced-motion="user"` desactiva de raíz lo que anime motion-v cuando
         el sistema lo pide. Lo demás lo cubre el bloque global de `app.css`. -->
    <MotionConfig reduced-motion="user">
        <TooltipProvider :delay-duration="400">
            <div class="flex min-h-[100dvh] bg-background">
                <!--
                    Saltar al contenido. Con un sidebar de veintiséis módulos,
                    llegar a la tabla con el teclado significaba tabular por toda
                    la navegación en cada carga de página.
                -->
                <a
                    href="#contenido"
                    class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-(--z-recorrido) focus:rounded-md focus:bg-primary focus:px-3 focus:py-2 focus:text-sm focus:font-medium focus:text-primary-foreground"
                >
                    Saltar al contenido
                </a>

                <!-- ── Sidebar de escritorio ──────────────────────────────── -->
                <!--
                    El ancho es lo único que se anima con disposición (DESIGN.md
                    §10), y el contenido no se reacomoda mientras tanto: cada modo
                    tiene su ancho fijo, el que sale se funde recortado por el
                    `overflow-hidden` y el que entra aparece cuando el ancho ya ha
                    llegado. Sin eso, los textos se partían a mitad del pliegue.

                    Pegajoso y a la altura de la ventana: crecía con la página, y
                    en una tabla larga la organización y el botón de plegar se
                    quedaban al fondo del documento.
                -->
                <aside
                    class="sticky top-0 hidden h-[100dvh] shrink-0 flex-col overflow-hidden border-r bg-superficie transition-[width] duration-(--duracion) ease-marca md:flex"
                    :class="compacto ? 'w-16' : 'w-62'"
                >
                    <Transition name="modo-sidebar" mode="out-in">
                        <!-- Desplegado: grupos plegables, uno abierto cada vez. -->
                        <div v-if="!compacto" key="completo" class="flex min-h-0 w-62 flex-1 flex-col">
                            <!-- Sólo Statera: el respaldo no va en la barra de
                                 navegación (DESIGN.md §2, Logotipo). -->
                            <div class="flex h-16 shrink-0 items-center border-b px-4">
                                <Link href="/panel" class="flex min-w-0 items-center rounded-md" data-recorrido="logotipo">
                                    <Logotipo />
                                </Link>
                            </div>

                            <nav aria-label="Módulos" class="flex-1 space-y-0.5 overflow-y-auto p-2">
                                <Link
                                    v-if="inicio"
                                    :href="inicio.href"
                                    :data-recorrido="anclaRecorrido(inicio.href)"
                                    class="mb-2 flex h-9 items-center gap-2.5 rounded-md px-3 text-sm font-medium transition-colors"
                                    :class="
                                        esSeccionActiva(inicio.href, rutaActual)
                                            ? 'bg-accent font-semibold text-accent-foreground'
                                            : 'text-secondary-foreground hover:bg-muted'
                                    "
                                    :aria-current="esSeccionActiva(inicio.href, rutaActual) ? 'page' : undefined"
                                >
                                    <component
                                        :is="inicio.icono"
                                        class="size-4 shrink-0"
                                        :class="esSeccionActiva(inicio.href, rutaActual) ? 'text-primary' : 'text-muted-foreground'"
                                    />
                                    {{ inicio.titulo }}
                                </Link>

                                <GrupoSidebar
                                    v-for="grupo in grupos"
                                    :key="grupo.titulo"
                                    :titulo="grupo.titulo"
                                    :icono="grupo.icono"
                                    :abierto="grupoAbierto(grupo)"
                                    :cantidad="grupo.entradas.length"
                                    :actual="entradaActual(grupo)"
                                    @alternar="alternarGrupo(grupo)"
                                >
                                    <!-- Sin `transition-colors`: el color y la
                                         entrada escalonada los lleva el grupo. -->
                                    <Link
                                        v-for="entrada in grupo.entradas"
                                        :key="entrada.href"
                                        :href="entrada.href"
                                        :data-recorrido="anclaRecorrido(entrada.href)"
                                        class="relative flex h-8 items-center gap-2.5 rounded-md px-2.5 text-sm font-medium"
                                        :class="
                                            esSeccionActiva(entrada.href, rutaActual)
                                                ? 'bg-accent font-semibold text-accent-foreground'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                        "
                                        :aria-current="esSeccionActiva(entrada.href, rutaActual) ? 'page' : undefined"
                                    >
                                        <!-- La activa pinta su tramo de la guía. -->
                                        <span
                                            v-if="esSeccionActiva(entrada.href, rutaActual)"
                                            class="absolute inset-y-1.5 -left-[9px] w-0.5 rounded-full bg-primary"
                                            aria-hidden="true"
                                        />
                                        <component
                                            :is="entrada.icono"
                                            class="size-4 shrink-0"
                                            :class="esSeccionActiva(entrada.href, rutaActual) && 'text-primary'"
                                        />
                                        <span class="truncate">{{ entrada.titulo }}</span>
                                    </Link>
                                </GrupoSidebar>
                            </nav>

                            <!-- Organización y plegar en una sola fila. -->
                            <div class="flex shrink-0 items-center gap-1 border-t p-2">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="flex h-12 min-w-0 flex-1 items-center gap-2.5 rounded-md px-2 text-left transition-colors hover:bg-muted data-[state=open]:bg-muted"
                                        >
                                            <span
                                                class="flex size-8 shrink-0 items-center justify-center rounded-md border bg-card text-xs font-semibold text-secondary-foreground"
                                                aria-hidden="true"
                                            >
                                                {{ inicialesOrganizacion }}
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-medium">
                                                    {{ organizacion?.nombre ?? 'Sin contexto' }}
                                                </span>
                                                <span class="block text-xs text-muted-foreground">Organización activa</span>
                                            </span>
                                            <ChevronsUpDownIcon class="size-3.5 shrink-0 text-muted-foreground" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <MenuOrganizacion :organizacion="organizacion" :gestionar="puede('organizacion.gestionar')" />
                                </DropdownMenu>

                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            class="text-muted-foreground"
                                            aria-label="Plegar la navegación"
                                            @click="plegado = true"
                                        >
                                            <PanelLeftCloseIcon />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">Plegar la navegación</TooltipContent>
                                </Tooltip>
                            </div>
                        </div>

                        <!-- Plegado: el riel, un botón por grupo con su menú. -->
                        <div v-else key="riel" class="flex min-h-0 w-16 flex-1 flex-col items-center">
                            <div class="flex h-16 w-full shrink-0 items-center justify-center border-b">
                                <Link href="/panel" class="flex rounded-md" data-recorrido="logotipo">
                                    <Logotipo variante="simbolo" />
                                </Link>
                            </div>

                            <nav aria-label="Módulos" class="flex w-full flex-1 flex-col items-center gap-1 overflow-y-auto py-2">
                                <Tooltip v-if="inicio">
                                    <TooltipTrigger as-child>
                                        <Link
                                            :href="inicio.href"
                                            class="relative flex size-10 items-center justify-center rounded-md transition-colors"
                                            :class="
                                                esSeccionActiva(inicio.href, rutaActual)
                                                    ? 'bg-accent text-primary'
                                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                            "
                                            :aria-label="inicio.titulo"
                                            :aria-current="esSeccionActiva(inicio.href, rutaActual) ? 'page' : undefined"
                                        >
                                            <span
                                                v-if="esSeccionActiva(inicio.href, rutaActual)"
                                                class="absolute inset-y-2.5 -left-3 w-[3px] rounded-full bg-primary"
                                                aria-hidden="true"
                                            />
                                            <component :is="inicio.icono" class="size-4.5" />
                                        </Link>
                                    </TooltipTrigger>
                                    <TooltipContent side="right">{{ inicio.titulo }}</TooltipContent>
                                </Tooltip>

                                <span class="my-1 h-px w-6 shrink-0 bg-border" aria-hidden="true" />

                                <DropdownMenu v-for="grupo in grupos" :key="grupo.titulo">
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="relative flex size-10 shrink-0 items-center justify-center rounded-md transition-colors"
                                            :class="
                                                entradaActual(grupo)
                                                    ? 'bg-accent text-primary'
                                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground data-[state=open]:bg-muted data-[state=open]:text-foreground'
                                            "
                                            :aria-label="entradaActual(grupo) ? `${grupo.titulo}, donde está ${entradaActual(grupo)}` : grupo.titulo"
                                        >
                                            <span
                                                v-if="entradaActual(grupo)"
                                                class="absolute inset-y-2.5 -left-3 w-[3px] rounded-full bg-primary"
                                                aria-hidden="true"
                                            />
                                            <component :is="grupo.icono" class="size-4.5" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent side="right" align="start" :side-offset="14" class="w-60">
                                        <DropdownMenuLabel class="flex items-center justify-between gap-2 text-xs font-medium text-muted-foreground">
                                            {{ grupo.titulo }}
                                            <span class="cifra">{{ grupo.entradas.length }}</span>
                                        </DropdownMenuLabel>
                                        <DropdownMenuItem v-for="entrada in grupo.entradas" :key="entrada.href" as-child>
                                            <Link
                                                :href="entrada.href"
                                                :class="esSeccionActiva(entrada.href, rutaActual) && 'bg-accent font-semibold text-accent-foreground'"
                                                :aria-current="esSeccionActiva(entrada.href, rutaActual) ? 'page' : undefined"
                                            >
                                                <component
                                                    :is="entrada.icono"
                                                    class="size-4"
                                                    :class="esSeccionActiva(entrada.href, rutaActual) && 'text-primary'"
                                                />
                                                {{ entrada.titulo }}
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </nav>

                            <div class="flex w-full shrink-0 flex-col items-center gap-1 border-t py-2">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="flex size-10 items-center justify-center rounded-md transition-colors hover:bg-muted data-[state=open]:bg-muted"
                                            :aria-label="`Organización activa: ${organizacion?.nombre ?? 'sin contexto'}`"
                                        >
                                            <span
                                                class="flex size-8 items-center justify-center rounded-md border bg-card text-xs font-semibold text-secondary-foreground"
                                                aria-hidden="true"
                                            >
                                                {{ inicialesOrganizacion }}
                                            </span>
                                        </button>
                                    </DropdownMenuTrigger>
                                    <MenuOrganizacion side="right" :organizacion="organizacion" :gestionar="puede('organizacion.gestionar')" />
                                </DropdownMenu>

                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            class="text-muted-foreground"
                                            aria-label="Desplegar la navegación"
                                            @click="plegado = false"
                                        >
                                            <PanelLeftOpenIcon />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent side="right">Desplegar la navegación</TooltipContent>
                                </Tooltip>
                            </div>
                        </div>
                    </Transition>
                </aside>

                <!-- ── Columna principal ──────────────────────────────────── -->
                <div class="flex min-w-0 flex-1 flex-col">
                    <header
                        class="sticky top-0 z-(--z-cabecera) h-16 border-b bg-background/85 backdrop-blur-sm"
                    >
                        <!-- A todo el ancho y con los márgenes del `<main>`: el
                             contenido cambia de ancho según la pantalla, y una
                             cabecera que siguiera a uno de los dos quedaría
                             desalineada con el otro. Sus extremos coinciden con
                             los de una tabla, que es lo más ancho del producto. -->
                        <div class="flex h-full w-full items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
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
                                            <Logotipo />
                                        </div>

                                        <nav class="flex-1 space-y-6 overflow-y-auto p-3">
                                            <Link
                                                v-if="inicio"
                                                :href="inicio.href"
                                                class="flex items-center gap-2.5 rounded-md px-3 py-2.5 text-sm font-medium transition-colors"
                                                :class="
                                                    esSeccionActiva(inicio.href, rutaActual)
                                                        ? 'bg-accent text-accent-foreground'
                                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                                "
                                                @click="menuMovil = false"
                                            >
                                                <component :is="inicio.icono" class="size-4" />
                                                {{ inicio.titulo }}
                                            </Link>
                                            <div v-for="grupo in grupos" :key="grupo.titulo" class="space-y-1">
                                                <p class="flex items-center gap-2 px-3 pb-1 text-xs font-medium text-muted-foreground">
                                                    <component :is="grupo.icono" class="size-3.5" />
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
                                            <p class="text-xs text-muted-foreground">Organización</p>
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
                                    <kbd class="cifra rounded border bg-muted px-1 text-xs">⌘K</kbd>
                                </Button>

                                <!-- El tema vive dentro: es una preferencia de la persona,
                                     como todo lo demás de ese menú. -->
                                <MenuCuenta
                                    v-if="usuario"
                                    :usuario="usuario"
                                    :organizacion="organizacion?.nombre ?? null"
                                />
                            </div>
                        </div>
                    </header>

                    <!--
                        `tabindex="-1"` no es para tabular hasta aquí: es lo que
                        hace que el elemento pueda RECIBIR el foco. Sin él,
                        «Saltar al contenido» desplaza la página pero deja el
                        foco donde estaba, y el siguiente tabulador vuelve al
                        principio de la navegación.

                        El ancho lo decide la pantalla con `ancho` (DESIGN.md
                        §5): 1440 px centrados para lo que se lee, todo el ancho
                        para lo que se recorre. Con un tope único pegado a la
                        izquierda las tablas se quedaban estrechas y sobraba un
                        hueco a la derecha.

                        `space-y-8` es el RITMO VERTICAL de la página —32 px
                        entre bloques, el de §5—, y vive
                        aquí a propósito: antes lo ponía cada componente por su
                        cuenta —`CabeceraPagina` y `TiraIndicadores` llevaban
                        `mb-6`, `DataTable` y `Card` no llevan nada—, así que
                        una tarjeta intercalada entre dos bloques salía pegada
                        a lo de abajo. Se veía en `/personas`, con la tarjeta de
                        cobertura del 5.3 a tope con la barra de la tabla.

                        El contenedor es quien sabe separar a sus hijos; un
                        componente no puede saber si tiene algo debajo. Por eso
                        los `mb-6` salieron de los dos componentes: dejarlos
                        sumaría el doble de lo que toca.
                    -->
                    <motion.main
                        id="contenido"
                        tabindex="-1"
                        :key="rutaActual"
                        :variants="variantesEntrada"
                        initial="oculto"
                        animate="visible"
                        class="w-full min-w-0 flex-1 space-y-8 px-4 pt-6 pb-10 outline-none sm:px-6 lg:px-8"
                        :class="ancho === 'contenido' && 'mx-auto max-w-[90rem]'"
                    >
                        <slot />
                    </motion.main>
                </div>

                <PaletaComandos />
                <RecorridoGuiado />
                <Toaster position="top-right" rich-colors />
            </div>
        </TooltipProvider>
    </MotionConfig>
</template>

<style scoped>
/*
 * El relevo entre el sidebar desplegado y el riel. Sólo opacidad: el ancho ya
 * se mueve, y dos cosas desplazándose a la vez no se siguen. Sale en la
 * duración de salida mientras el ancho encoge o crece, y entra cuando el ancho
 * ya ha llegado.
 */
.modo-sidebar-enter-active {
    transition: opacity var(--duracion-salida) var(--curva) calc(var(--duracion) - var(--duracion-rapida));
}

.modo-sidebar-leave-active {
    transition: opacity var(--duracion-rapida) var(--curva);
}

.modo-sidebar-enter-from,
.modo-sidebar-leave-to {
    opacity: 0;
}
</style>
