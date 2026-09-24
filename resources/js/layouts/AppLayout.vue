<script setup lang="ts">
import AvatarUsuario from '@/components/AvatarUsuario.vue';
import GrupoSidebar from '@/components/GrupoSidebar.vue';
import Logotipo from '@/components/Logotipo.vue';
import PaletaComandos from '@/components/PaletaComandos.vue';
import RecorridoGuiado from '@/components/RecorridoGuiado.vue';
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
import { useRecorrido } from '@/composables/useRecorrido';
import { useTema } from '@/composables/useTema';
import { entradaDe, esSeccionActiva, navegacionPara, type GrupoNavegacion } from '@/lib/navegacion';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    BuildingIcon,
    ChevronRightIcon,
    LogOutIcon,
    MenuIcon,
    MonitorIcon,
    MoonIcon,
    PanelLeftIcon,
    RouteIcon,
    SearchIcon,
    ShieldCheckIcon,
    SunIcon,
    UserRoundCogIcon,
} from '@lucide/vue';
import { useStorage } from '@vueuse/core';
import { MotionConfig, motion } from 'motion-v';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import 'vue-sonner/style.css';

defineProps<{ titulo: string }>();

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

/* La preferencia de sidebar es del navegador, como la del tema. */
const plegado = useStorage('statera.sidebar.plegado', false);

/* Sólo lo que la sesión puede abrir: un enlace a un 403 no se pinta. */
const grupos = computed(() => navegacionPara(pagina.props.auth.permisos));

/*
 * Los grupos plegados, por título, también del navegador. Se guarda lo que se
 * plegó y no lo que se abrió: un grupo nuevo que llegue con un módulo nuevo
 * sale abierto, que es lo que tiene que pasar con algo que nadie ha visto.
 */
const gruposPlegados = useStorage<string[]>('statera.sidebar.grupos-plegados', []);

const contieneLaRuta = (grupo: GrupoNavegacion): boolean =>
    grupo.entradas.some((entrada) => esSeccionActiva(entrada.href, rutaActual.value));

/*
 * Con el recorrido guiado en marcha se abren todos: sus pasos señalan entradas
 * del sidebar (`nav-sistemas`, `nav-documentos`…), y un foco sobre algo plegado
 * no señala nada.
 */
const grupoAbierto = (grupo: GrupoNavegacion): boolean =>
    recorridoAbierto.value || contieneLaRuta(grupo) || !gruposPlegados.value.includes(grupo.titulo);

function alternarGrupo(grupo: GrupoNavegacion): void {
    gruposPlegados.value = gruposPlegados.value.includes(grupo.titulo)
        ? gruposPlegados.value.filter((titulo) => titulo !== grupo.titulo)
        : [...gruposPlegados.value, grupo.titulo];
}
const menuMovil = ref(false);

const { preferencia, esOscuro, fijar } = useTema();
const { abrir: abrirPaleta } = usePaletaComandos();
const { abierto: recorridoAbierto, abrir: abrirRecorrido } = useRecorrido();

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
                <aside
                    class="hidden shrink-0 flex-col border-r bg-superficie transition-[width] duration-(--duracion) ease-marca md:flex"
                    :class="plegado ? 'w-[4.25rem]' : 'w-60'"
                >
                    <div class="flex h-16 items-center border-b px-4">
                        <Link href="/panel" class="flex min-w-0 items-center rounded-md" data-recorrido="logotipo">
                            <Logotipo :variante="plegado ? 'simbolo' : 'completo'" :respaldo="!plegado" />
                        </Link>
                    </div>

                    <nav class="flex-1 space-y-6 overflow-y-auto p-3">
                        <GrupoSidebar
                            v-for="grupo in grupos"
                            :key="grupo.titulo"
                            :titulo="grupo.titulo"
                            :abierto="grupoAbierto(grupo)"
                            :compacto="plegado"
                            @alternar="alternarGrupo(grupo)"
                        >
                            <Tooltip v-for="entrada in grupo.entradas" :key="entrada.href">
                                <TooltipTrigger as-child>
                                    <Link
                                        :href="entrada.href"
                                        :data-recorrido="anclaRecorrido(entrada.href)"
                                        class="relative flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors"
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
                        </GrupoSidebar>
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
                                        <span class="block text-xs text-muted-foreground">Organización</span>
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
                                    <!--
                                        El logo del cliente donde estaba el
                                        escudo. Statera se queda arriba del
                                        panel: esto es co-branding y no marca
                                        blanca, que sigue fuera de alcance.
                                        `DESIGN.md` §2 pide que no se compongan
                                        en la misma pieza, y aquí los separa el
                                        alto del sidebar entero.
                                    -->
                                    <img
                                        v-if="organizacion.logo"
                                        :src="organizacion.logo"
                                        alt=""
                                        class="h-5 w-auto max-w-[5rem] object-contain"
                                    />
                                    <ShieldCheckIcon v-else class="size-4 text-primary" />
                                    {{ organizacion.nombre }}
                                </DropdownMenuItem>
                                <DropdownMenuItem v-else disabled>Sin contexto de organización</DropdownMenuItem>

                                <!--
                                    La ficha del tenant no está en
                                    `lib/navegacion.ts` —ese fichero es el mapa
                                    de MÓDULOS y esto no lo es—, así que su
                                    puerta es este desplegable, que es donde ya
                                    se mira para preguntarse de qué organización
                                    hablamos. Mismo precedente que la metodología
                                    de riesgo, que se enlaza desde la ficha de un
                                    riesgo y tampoco tiene entrada de menú.
                                -->
                                <template v-if="organizacion && puede('organizacion.gestionar')">
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem as-child>
                                        <Link href="/organizacion">
                                            <BuildingIcon class="size-4" />
                                            La ficha de la organización
                                        </Link>
                                    </DropdownMenuItem>
                                </template>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Button
                            variant="ghost"
                            size="sm"
                            class="mt-1 w-full justify-center text-muted-foreground"
                            :aria-label="plegado ? 'Desplegar la navegación' : 'Plegar la navegación'"
                            @click="plegado = !plegado"
                        >
                            <PanelLeftIcon class="size-4 transition-transform duration-(--duracion)" :class="plegado && 'rotate-180'" />
                            <span v-if="!plegado">Plegar</span>
                        </Button>
                    </div>
                </aside>

                <!-- ── Columna principal ──────────────────────────────────── -->
                <div class="flex min-w-0 flex-1 flex-col">
                    <header
                        class="sticky top-0 z-(--z-cabecera) h-16 border-b bg-background/85 backdrop-blur-sm"
                    >
                        <!-- El mismo contenedor y los mismos márgenes que el
                             `<main>`: con la cabecera a todo el ancho, a 1920 el
                             usuario y el buscador quedaban lejos del contenido
                             que acompañan. El borde y el velo sí van de lado a
                             lado, que es lo que separa la cabecera del lienzo. -->
                        <div
                            class="flex h-full w-full max-w-[90rem] items-center justify-between gap-3 px-4 sm:px-6 lg:px-8"
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
                                            <div v-for="grupo in grupos" :key="grupo.titulo" class="space-y-1">
                                                <p class="px-3 pb-1 text-xs font-medium text-muted-foreground">
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
                                            <AvatarUsuario
                                                :nombre="usuario?.nombre"
                                                :foto="usuario?.foto"
                                                tamano="sm"
                                                clase="text-xs"
                                            />
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
                                        <!-- El recorrido se ofrece solo una vez; a
                                             partir de ahí hay que poder encontrarlo,
                                             y este es el menú donde ya se busca todo
                                             lo que es del usuario y no del trabajo. -->
                                        <DropdownMenuItem @select="abrirRecorrido">
                                            <RouteIcon class="size-4" />
                                            Recorrido guiado
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @select="salir">
                                            <LogOutIcon class="size-4" />
                                            Cerrar sesión
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </header>

                    <!--
                        `tabindex="-1"` no es para tabular hasta aquí: es lo que
                        hace que el elemento pueda RECIBIR el foco. Sin él,
                        «Saltar al contenido» desplaza la página pero deja el
                        foco donde estaba, y el siguiente tabulador vuelve al
                        principio de la navegación.

                        El contenedor es de 1440 px (DESIGN.md §5) y va pegado a
                        la izquierda, junto al sidebar: a 1920 las tablas y las
                        fichas se estiraban hasta 1600 y una fila de doce
                        columnas ya no se leía de un vistazo.

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
                        class="w-full max-w-[90rem] min-w-0 flex-1 space-y-8 px-4 pt-6 pb-10 outline-none sm:px-6 lg:px-8"
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
