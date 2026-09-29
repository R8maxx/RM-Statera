<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import HiloCarga from '@/components/HiloCarga.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import FiltroFuentes from '@/components/calendario/FiltroFuentes.vue';
import PanelDia from '@/components/calendario/PanelDia.vue';
import ResumenVencidos from '@/components/calendario/ResumenVencidos.vue';
import BarraFiltros from '@/components/tabla/BarraFiltros.vue';
import { Button } from '@/components/ui/button';
import { useFiltrosServidor } from '@/composables/useFiltrosServidor';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFecha } from '@/lib/celdas';
import { tono } from '@/lib/tonos';
import { Link } from '@inertiajs/vue3';
import { CalendarDaysIcon, ChevronLeftIcon, ChevronRightIcon } from '@lucide/vue';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { curva, duracion, transicionSalida } from '@/lib/motion';
import { AnimatePresence, motion } from 'motion-v';
import { computed, nextTick, ref, toRef } from 'vue';

type Vencimiento = App.Domain.Aviso.Vencimiento;
type Filtro = App.Http.Resources.Definicion.Filtro;

interface Dia {
    dia: string;
    numero: number;
    delMes: boolean;
    esHoy: boolean;
    finDeSemana: boolean;
}

const props = defineProps<{
    rejilla: {
        mes: string;
        etiqueta: string;
        anterior: string;
        siguiente: string;
        primerDia: string;
        ultimoDia: string;
        dias: Dia[];
        /** El número ISO de cada fila, de arriba abajo. */
        semanas: number[];
    };
    vencimientos: Vencimiento[];
    /** Lo pasado de fecha, caiga en el mes que caiga. */
    vencidos: Vencimiento[];
    filtros: Filtro[];
    filtrosAplicados: Record<string, string | string[]>;
    /** Lo que el filtro de responsable deja fuera por no tener uno. */
    excluidasPorResponsable: string[];
}>();

/**
 * Cuántas filas caben en una casilla, contando la salida.
 *
 * Con cuatro vencimientos o más se enseñan dos y «+N más»: la casilla tiene
 * siempre el mismo alto, y un día cargado no estira la fila entera.
 */
const FILAS = 3;

const cabeceras = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

/*
 * El mes viaja en la URL, así que hay que devolvérselo al servidor en cada
 * recarga: sin esto, filtrar te manda al mes de hoy.
 */
const {
    cargando,
    filtros: valores,
    hayFiltrosActivos,
    aplicarFiltro,
    limpiarFiltros,
} = useFiltrosServidor({
    aplicados: toRef(props, 'filtrosAplicados'),
    only: ['vencimientos', 'vencidos', 'filtrosAplicados'],
    extras: () => ({ mes: props.rejilla.mes }),
});

const busqueda = computed<Filtro | null>(
    () => props.filtros.find((filtro) => filtro.tipo === 'busqueda') ?? null,
);

/*
 * El filtro de fuente sale de aquí: sube a la fila de chips, que es a la vez
 * filtro y leyenda. Calcularlo excluyéndolo —en vez de duplicar la lista— es lo
 * que impide que el control acabe viviendo en dos sitios.
 *
 * **Y la misma lista va a `todos`.** Sólo se excluía de `sueltos`, así que
 * `chipsDe()` —que recorre `todos`— seguía pintando «Qué: Tarea» como chip
 * descartable al lado de los chips de `FiltroFuentes`.
 */
const filtroFuente = computed<Filtro | null>(
    () => props.filtros.find((filtro) => filtro.clave === 'fuente') ?? null,
);

const sueltos = computed(() =>
    props.filtros.filter((filtro) => filtro.tipo !== 'busqueda' && filtro.clave !== 'fuente'),
);

/** Lo que el servidor aplicó de verdad, que es lo que marcan los chips. */
const fuentesActivas = computed<string[]>(() => {
    const aplicado = props.filtrosAplicados.fuente;

    if (aplicado === undefined) {
        return [];
    }

    return Array.isArray(aplicado) ? aplicado : [aplicado];
});

/** Lo que cae cada día, indexado por `Y-m-d`. */
const porDia = computed(() => {
    const mapa = new Map<string, Vencimiento[]>();

    for (const vencimiento of props.vencimientos) {
        const dia = mapa.get(vencimiento.dia);

        if (dia === undefined) {
            mapa.set(vencimiento.dia, [vencimiento]);
        } else {
            dia.push(vencimiento);
        }
    }

    return mapa;
});

const del = (dia: string): Vencimiento[] => porDia.value.get(dia) ?? [];

/** Lo que se enseña en la casilla: todo si cabe, y si no, una fila menos para la salida. */
const visiblesDe = (dia: string): Vencimiento[] => {
    const todos = del(dia);

    return todos.length > FILAS ? todos.slice(0, FILAS - 1) : todos;
};

const ocultosDe = (dia: string): number => del(dia).length - visiblesDe(dia).length;

/** La rejilla en filas, con su número de semana delante. */
const semanas = computed(() =>
    props.rejilla.semanas.map((numero, fila) => ({
        numero,
        dias: props.rejilla.dias.slice(fila * 7, fila * 7 + 7),
    })),
);

/*
 * Cuatro escalones sólidos, sin alfa.
 *
 * Antes eran `bg-muted/40` y `bg-muted/20` sobre `bg-card`: dos transparencias
 * casi idénticas, y las dos en el mismo atributo, así que decidía el orden en
 * que Tailwind emite las clases y no el código. `app.css` ya trae la escalera
 * compuesta para no repetirlo.
 */
function fondoDe(dia: Dia): string {
    if (dia.esHoy) {
        return 'bg-accent';
    }

    if (!dia.delMes) {
        return 'bg-muted';
    }

    return dia.finDeSemana ? 'bg-superficie' : 'bg-card';
}

/*
 * **El icono dice qué es y su tinta dice cómo va.** Antes cada fila llevaba el
 * fondo suave de su estado, y un mes normal era una pared de color donde el rojo
 * de lo vencido no destacaba. Ahora el fondo sólo lo gasta lo vencido, que es lo
 * único que § 3 deja en rojo.
 */
const tintaDe = (vencimiento: Vencimiento): string => tono(vencimiento.estadoTono).texto ?? 'text-muted-foreground';

const esVencido = (vencimiento: Vencimiento): boolean => vencimiento.estadoTono === 'caducada';

/**
 * Lo que se lee en voz alta y lo que sale al pasar el ratón.
 *
 * § 11: un estado no puede depender sólo del color. En la rejilla el título va
 * truncado, así que este texto es además lo único que da la fecha.
 */
const descripcion = (vencimiento: Vencimiento): string =>
    `${vencimiento.titulo} · ${vencimiento.estadoEtiqueta} · ${vencimiento.fecha}`;

/**
 * Los tonos que hay de verdad este mes, para la leyenda.
 *
 * Un tono puede llevar etiquetas distintas según la fuente —el verde es
 * «Vigente» en una evidencia y «Al día» en una obligación—, así que se juntan en
 * vez de quedarse con la última que pasó.
 */
const leyenda = computed(() => {
    const vistos = new Map<string, Set<string>>();

    for (const vencimiento of props.vencimientos) {
        const etiquetas = vistos.get(vencimiento.estadoTono) ?? new Set<string>();

        etiquetas.add(vencimiento.estadoEtiqueta);
        vistos.set(vencimiento.estadoTono, etiquetas);
    }

    return [...vistos].map(([nombre, etiquetas]) => ({
        tono: nombre,
        etiqueta: [...etiquetas].join(' · '),
    }));
});

/**
 * Lo que viene, contado desde hoy. Sólo tiene sentido si hoy está en la
 * rejilla: mirando diciembre, «hoy: 0» sería una cifra que no contesta nada.
 */
const proximos = computed(() => {
    if (!props.rejilla.dias.some((dia) => dia.esHoy)) {
        return null;
    }

    return {
        hoy: props.vencimientos.filter((vencimiento) => vencimiento.dias === 0).length,
        semana: props.vencimientos.filter((vencimiento) => vencimiento.dias >= 1 && vencimiento.dias <= 7).length,
        despues: props.vencimientos.filter((vencimiento) => vencimiento.dias > 7).length,
    };
});

/** Sólo los días con algo, para la agenda de móvil. */
const agenda = computed(() =>
    props.rejilla.dias
        .filter((dia) => del(dia.dia).length > 0)
        .map((dia) => ({ ...dia, vencimientos: del(dia.dia) })),
);

const fechaLarga = (dia: string): string => formatoFecha.format(new Date(`${dia}T00:00:00`));

const nombreDelDia = (dia: Dia): string => {
    const cuantos = del(dia.dia).length;
    const recuento = cuantos === 0 ? 'sin vencimientos' : cuantos === 1 ? '1 vencimiento' : `${cuantos} vencimientos`;

    return `${fechaLarga(dia.dia)}, ${recuento}`;
};

/*
 * ── El día abierto ─────────────────────────────────────────────────────────
 *
 * El panel no es modal, así que no hay foco atrapado que devolver solo: se
 * recuerda quién lo abrió y se le devuelve al cerrar. Sin esto, cerrar con
 * Escape dejaba el foco en `<body>` y el tabulador volvía al principio de la
 * página.
 */
const diaAbierto = ref<string | null>(null);
let abiertoDesde: HTMLElement | null = null;

function abrir(dia: string, evento: Event): void {
    abiertoDesde = evento.currentTarget instanceof HTMLElement ? evento.currentTarget : null;
    diaAbierto.value = diaAbierto.value === dia ? null : dia;
}

async function cerrar(): Promise<void> {
    diaAbierto.value = null;
    await nextTick();
    abiertoDesde?.focus();
}

/*
 * ── De qué lado viene el mes ───────────────────────────────────────────────
 *
 * Cambiar de mes es una navegación de Inertia: la página se monta de nuevo y el
 * componente no recuerda de dónde venía. El módulo sí —no se vuelve a evaluar
 * mientras la aplicación viva—, y es el único sitio donde cabe este dato.
 *
 * No se guarda en `sessionStorage` a propósito: no es una preferencia ni un
 * estado que deba sobrevivir a una recarga. Si alguien llega al calendario
 * escribiendo la URL, no viene de ningún lado y la rejilla entra sin dirección,
 * que es exactamente lo correcto.
 */
let mesVisitado: string | null = null;

function ladoDeEntrada(mes: string, anterior: string | null): boolean | null {
    if (anterior === null || anterior === mes) {
        return null;
    }

    /* Los meses llegan como `AAAA-MM`, así que se ordenan como texto. */
    return mes > anterior;
}

const desdeLaDerecha = ladoDeEntrada(props.rejilla.mes, mesVisitado);

mesVisitado = props.rejilla.mes;

/*
 * Lo que el filtro de responsable esconde, dicho.
 *
 * Filtrar por responsable **excluye** la formación en vez de dejarla intacta:
 * dejarla sería el filtro mintiendo, porque lo que vence ahí es que a una persona
 * le toca renovar y esa persona es la fila, no su jefe. Excluirla se puede
 * explicar; desaparecer sin más, no.
 */
const avisoDeExcluidas = computed(() =>
    props.excluidasPorResponsable.length === 0
        ? ''
        : ` Filtrando por responsable no se ve ${props.excluidasPorResponsable.join(', ').toLowerCase()}: no tiene uno.`,
);

const { reducido } = useMovimientoReducido();

const entradaRejilla = computed(() => {
    if (reducido.value || desdeLaDerecha === null) {
        return { opacity: 0 };
    }

    return { opacity: 0, x: desdeLaDerecha ? 24 : -24 };
});

const entradaPanel = computed(() => (reducido.value ? { opacity: 0 } : { opacity: 0, x: 16 }));
</script>

<template>
    <AppLayout ancho="completo" titulo="Calendario">
        <!--
            Sin conmutador de vistas: esto dejó de ser una de las tres formas de
            mirar el plan de acción. Enseña vencimientos de muchos registros
            distintos, y el conmutador habría seguido diciendo que es del plan.
        -->
        <CabeceraPagina
            titulo="Calendario"
            descripcion="Todo lo que tiene fecha, en el mismo mes: plazos, caducidades, revisiones y lo periódico que la organización se ha declarado."
        />

        <div class="flex flex-wrap items-center gap-2">
            <Button as-child variant="outline" size="icon-sm" aria-label="Mes anterior">
                <Link :href="`/calendario?mes=${rejilla.anterior}`">
                    <ChevronLeftIcon class="size-4" />
                </Link>
            </Button>

            <h2 class="min-w-52 text-center text-lg font-semibold tracking-tight">{{ rejilla.etiqueta }}</h2>

            <Button as-child variant="outline" size="icon-sm" aria-label="Mes siguiente">
                <Link :href="`/calendario?mes=${rejilla.siguiente}`">
                    <ChevronRightIcon class="size-4" />
                </Link>
            </Button>

            <Button as-child variant="ghost" size="sm" class="ml-1">
                <Link href="/calendario">Hoy</Link>
            </Button>

            <BarraFiltros
                v-if="filtros.length > 0"
                class="sm:ml-auto"
                :busqueda="busqueda"
                :sueltos="sueltos"
                :todos="sueltos"
                :valores="valores"
                :hay-filtros-activos="hayFiltrosActivos"
                @aplicar="aplicarFiltro"
                @limpiar="limpiarFiltros"
            />
        </div>

        <!--
            Antes que la rejilla, y fuera de ella: lo que venció en otro mes no
            tiene casilla, y aquí es donde se sigue viendo.
        -->
        <ResumenVencidos
            v-if="vencidos.length > 0"
            :vencidos="vencidos"
            :primer-dia="rejilla.primerDia"
            :ultimo-dia="rejilla.ultimoDia"
            :proximos="proximos"
        />

        <!--
            Las dos claves en la misma fila: los chips dicen QUÉ es cada icono y
            filtran; la leyenda dice CÓMO VA cada tinta. Ver `FiltroFuentes`.
        -->
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
            <FiltroFuentes
                v-if="filtroFuente"
                class="flex-1"
                :filtro="filtroFuente"
                :seleccionadas="fuentesActivas"
                @aplicar="aplicarFiltro"
            />

            <!-- § 3: leyenda siempre que haya dos tonos o más. -->
            <div v-if="leyenda.length > 1" class="flex shrink-0 flex-col gap-1.5 lg:max-w-md lg:pt-1.5">
                <span class="text-xs font-medium text-muted-foreground">Cómo va</span>
                <ul class="flex flex-wrap gap-x-4 gap-y-1">
                    <li
                        v-for="tramo in leyenda"
                        :key="tramo.tono"
                        class="flex items-center gap-1.5 text-xs text-secondary-foreground"
                    >
                        <IconoTipo
                            :nombre="tono(tramo.tono).icono"
                            :clase="`size-3.5 ${tono(tramo.tono).texto ?? 'text-muted-foreground'}`"
                        />
                        {{ tramo.etiqueta }}
                    </li>
                </ul>
            </div>
        </div>

        <EstadoVacio
            v-if="vencimientos.length === 0"
            :icono="CalendarDaysIcon"
            titulo="No vence nada este mes"
            :descripcion="
                hayFiltrosActivos
                    ? `Ningún vencimiento cumple estos filtros. Prueba a quitar alguno o cambia de mes.${avisoDeExcluidas}`
                    : 'Nada con fecha este mes. Cambia de mes para ver otros.'
            "
        />

        <!--
            La rejilla desde `md`. Siete columnas a 400 px no se leen: por debajo
            va la agenda, que es la misma información en la forma que cabe.
        -->
        <div v-else class="relative hidden md:block" :aria-busy="cargando">
            <!-- Filtrar es una consulta de servidor: sin el hilo, la rejilla se
                 quedaba quieta y parecía que el filtro no había hecho nada. -->
            <HiloCarga :activo="cargando" />

            <!--
                El fin de semana, más estrecho: un plazo que cae en sábado casi
                nunca es un plazo, y ese ancho lo aprovechan los títulos de lunes
                a viernes, que es donde está todo.
            -->
            <motion.div
                class="grid grid-cols-[2.75rem_repeat(5,minmax(0,1fr))_repeat(2,minmax(0,0.62fr))] gap-px overflow-hidden rounded-xl border bg-border"
                :initial="entradaRejilla"
                :animate="{ opacity: 1, x: 0 }"
                :transition="{ duration: reducido ? 0 : duracion.normal, ease: curva }"
            >
                <div class="bg-background py-2 text-center text-xs font-medium text-muted-foreground">
                    <abbr title="Semana" class="no-underline">Sem.</abbr>
                </div>
                <div
                    v-for="(nombre, indice) in cabeceras"
                    :key="nombre"
                    class="px-2.5 py-2 text-xs font-medium text-muted-foreground"
                    :class="indice >= 5 ? 'bg-superficie' : 'bg-card'"
                >
                    {{ nombre }}
                </div>

                <template v-for="semana in semanas" :key="semana.numero">
                    <div class="cifra bg-background pt-3 text-center text-xs text-muted-foreground">
                        <span class="sr-only">Semana </span>{{ semana.numero }}
                    </div>

                    <div
                        v-for="dia in semana.dias"
                        :key="dia.dia"
                        class="relative flex h-34 min-w-0 flex-col gap-0.5 p-1.5"
                        :class="[fondoDe(dia), diaAbierto === dia.dia ? 'ring-2 ring-primary ring-inset' : '']"
                    >
                        <!--
                            La barra de hoy, el mismo gesto que marca el ítem activo
                            del sidebar. El color por sí solo no bastaría: `accent`
                            es un teal muy pálido.
                        -->
                        <span v-if="dia.esHoy" class="absolute inset-x-0 top-0 h-0.5 bg-primary" aria-hidden="true" />

                        <div class="flex h-7 items-center gap-1 pl-1">
                            <span v-if="dia.esHoy" class="text-xs font-semibold text-primary">Hoy</span>
                            <!--
                                El número es la puerta al día entero. Es un botón y
                                no un enlace porque no navega: abre el panel.
                            -->
                            <button
                                type="button"
                                class="ml-auto inline-flex size-7 items-center justify-center rounded-full text-[13px] transition-colors"
                                :class="
                                    dia.esHoy
                                        ? 'bg-primary font-semibold text-primary-foreground'
                                        : dia.delMes
                                          ? 'font-medium text-foreground hover:bg-muted'
                                          : 'text-muted-foreground hover:bg-card'
                                "
                                :aria-label="nombreDelDia(dia)"
                                :aria-expanded="diaAbierto === dia.dia"
                                @click="abrir(dia.dia, $event)"
                            >
                                {{ dia.numero }}
                            </button>
                        </div>

                        <ul class="flex min-w-0 flex-col gap-0.5">
                            <li v-for="vencimiento in visiblesDe(dia.dia)" :key="`${vencimiento.fuente}-${vencimiento.id}`">
                                <Link
                                    :href="vencimiento.url"
                                    class="flex h-6 min-w-0 items-center gap-1.5 rounded-md px-1.5 text-xs transition-colors"
                                    :class="
                                        esVencido(vencimiento)
                                            ? 'bg-destructive/10 font-medium hover:bg-destructive/15'
                                            : 'hover:bg-muted'
                                    "
                                    :title="descripcion(vencimiento)"
                                >
                                    <IconoTipo :nombre="vencimiento.icono" :clase="`size-3.5 shrink-0 ${tintaDe(vencimiento)}`" />
                                    <span class="truncate">{{ vencimiento.titulo }}</span>
                                    <!-- El estado en texto, que es lo que § 11 pide
                                         y lo que la tinta por sí sola no da. -->
                                    <span class="sr-only">· {{ vencimiento.estadoEtiqueta }}</span>
                                </Link>
                            </li>
                        </ul>

                        <!--
                            Lo que el tope esconde tiene puerta, y con el tabulador.
                            Abre el mismo panel que el número del día.
                        -->
                        <button
                            v-if="ocultosDe(dia.dia) > 0"
                            type="button"
                            class="self-start rounded-md px-1.5 py-0.5 text-xs font-medium text-primary hover:underline"
                            :aria-expanded="diaAbierto === dia.dia"
                            @click="abrir(dia.dia, $event)"
                        >
                            +{{ ocultosDe(dia.dia) }} más
                        </button>
                    </div>
                </template>
            </motion.div>

            <AnimatePresence>
                <motion.div
                    v-if="diaAbierto"
                    :key="diaAbierto"
                    class="absolute top-3 right-3 bottom-3 z-(--z-pegajoso) flex items-start"
                    :initial="entradaPanel"
                    :animate="{ opacity: 1, x: 0 }"
                    :exit="{ opacity: 0, transition: transicionSalida }"
                    :transition="{ duration: reducido ? 0 : duracion.normal, ease: curva }"
                >
                    <PanelDia :dia="diaAbierto" :vencimientos="del(diaAbierto)" @cerrar="cerrar" />
                </motion.div>
            </AnimatePresence>
        </div>

        <!-- La agenda: la misma información, en la forma que cabe en un móvil. -->
        <div v-if="vencimientos.length > 0" class="relative md:hidden" :aria-busy="cargando">
            <HiloCarga :activo="cargando" />
            <ol class="space-y-4">
                <li v-for="dia in agenda" :key="dia.dia">
                    <h3 class="mb-1.5 text-sm font-medium" :class="dia.esHoy ? 'text-primary' : ''">
                        {{ fechaLarga(dia.dia) }}
                        <span v-if="dia.esHoy" class="text-xs font-normal">· hoy</span>
                    </h3>

                    <ul class="space-y-1.5">
                        <li v-for="vencimiento in dia.vencimientos" :key="`${vencimiento.fuente}-${vencimiento.id}`">
                            <Link
                                :href="vencimiento.url"
                                class="flex min-h-11 items-center gap-2 rounded-md px-3 text-sm"
                                :class="tono(vencimiento.estadoTono).badge"
                            >
                                <IconoTipo :nombre="vencimiento.icono" />
                                <span class="min-w-0 flex-1 truncate">{{ vencimiento.titulo }}</span>
                                <span class="shrink-0 text-xs">{{ vencimiento.estadoEtiqueta }}</span>
                            </Link>
                        </li>
                    </ul>
                </li>
            </ol>
        </div>

        <!--
            **Sin enumerar las fuentes.** El pie decía «se ven los plazos de las
            tareas abiertas y las caducidades de las evidencias» y llevaba siendo
            falso desde el § 4.5, sin que nadie lo notara. La enumeración la hace
            la fila de chips, que se genera del enum.
        -->
        <p v-if="avisoDeExcluidas && vencimientos.length > 0" class="mt-4 text-xs text-muted-foreground">
            {{ avisoDeExcluidas.trim() }}
        </p>

        <p class="mt-4 text-xs text-muted-foreground">
            Lo periódico que no sale de ningún registro —el informe INES, la renovación de conformidad, las
            auditorías— se declara en
            <Link href="/obligaciones" class="underline underline-offset-2">Obligaciones</Link>.
        </p>
    </AppLayout>
</template>
