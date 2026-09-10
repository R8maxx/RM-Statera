<script setup lang="ts">
import EstadoVacio from '@/components/EstadoVacio.vue';
import BarraFiltros from '@/components/tabla/BarraFiltros.vue';
import ConfirmacionAccion from '@/components/tabla/ConfirmacionAccion.vue';
import FilaDetalle from '@/components/tabla/FilaDetalle.vue';
import FiltroColumna from '@/components/tabla/FiltroColumna.vue';
import IconoAccion from '@/components/tabla/IconoAccion.vue';
import MenuColumna from '@/components/tabla/MenuColumna.vue';
import PaginacionTabla from '@/components/tabla/PaginacionTabla.vue';
import SelectorColumnas from '@/components/tabla/SelectorColumnas.vue';
import CeldaValor from '@/components/tabla/celdas/CeldaValor.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { useTablaServidor, type ValorFiltro } from '@/composables/useTablaServidor';
import { textoDeCelda } from '@/lib/celdas';
import { estaActivo, terminosPorColumna } from '@/lib/filtros';
import { descargarCsv } from '@/lib/csv';
import { barraContextual } from '@/lib/motion';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowDownIcon,
    ArrowUpDownIcon,
    ArrowUpIcon,
    DownloadIcon,
    InboxIcon,
    ListFilterIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    MoreHorizontalIcon,
    Rows2Icon,
    Rows3Icon,
} from '@lucide/vue';
import {
    columnOrderingFeature,
    columnPinningFeature,
    columnVisibilityFeature,
    rowSelectionFeature,
    tableFeatures,
    useTable,
} from '@tanstack/vue-table';
import { useResizeObserver, useStorage } from '@vueuse/core';
import { AnimatePresence, motion } from 'motion-v';
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue';

type Accion = App.Http.Resources.Definicion.Accion;
type Columna = App.Http.Resources.Definicion.Columna;
type DefinicionRecurso = App.Http.Resources.Definicion.DefinicionRecurso;
type Filtro = App.Http.Resources.Definicion.Filtro;
type MetaTabla = App.Http.Resources.Definicion.MetaTabla;

/**
 * Una fila tal y como la serializa `ConsultaRecurso`: el identificador, los
 * extras del recurso y un valor por columna. Los valores son heterogéneos por
 * naturaleza — texto, número, fecha ISO, `ValorEtiquetado` — y `CeldaValor` los
 * estrecha según el tipo de la columna.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type Fila = { id: number | string } & Record<string, any>;

const props = defineProps<{
    recurso: DefinicionRecurso;
    filas: Fila[];
    meta: MetaTabla;
}>();

const emit = defineEmits<{
    /**
     * Una acción masiva no puede resolverse aquí: cada recurso pide sus propios
     * datos (a qué estado, con qué nota). La tabla entrega la acción y la
     * selección, y la página decide qué hacer con ellas.
     */
    masiva: [accion: Accion, ids: (number | string)[]];
}>();

/*
 * Paginación, orden y filtros son de servidor. TanStack se queda con lo que sí
 * es de cliente: el modelo de columnas, su visibilidad, orden, anclado y ancho,
 * y la selección de filas.
 */
const {
    cargando,
    filtros,
    orden,
    hayFiltrosActivos,
    ordenarPor,
    irAPagina,
    cambiarTamano,
    aplicarFiltro,
    limpiarFiltros,
} = useTablaServidor({
    meta: toRef(props, 'meta'),
    ordenPorDefecto: props.recurso.ordenPorDefecto,
});

const { reducido } = useMovimientoReducido();

/**
 * La vista es preferencia de quien mira, no del recurso, y se guarda por
 * recurso: no se lee igual una tabla de seis columnas que una de doce, y quien
 * revisa implantaciones a diario no quiere volver a ocultar las mismas cuatro
 * columnas cada mañana.
 *
 * Se guarda en el navegador a propósito: es preferencia de un puesto, no un
 * dato de la organización, y no tiene que cruzar la frontera del tenant.
 */
interface Vista {
    compacta: boolean;
    filtrosVisibles: boolean;
    /** `null` significa «nunca se tocó»: manda lo que declare el recurso. */
    visibilidad: Record<string, boolean> | null;
    orden: string[] | null;
    anclado: string[] | null;
}

const clavesDeclaradas = props.recurso.columnas.map((columna) => columna.clave);
const conocidas = new Set(clavesDeclaradas);

const visibilidadDeclarada = Object.fromEntries(
    props.recurso.columnas.filter((columna) => columna.ocultaPorDefecto).map((columna) => [columna.clave, false]),
);
const ancladasDeclaradas = props.recurso.columnas
    .filter((columna) => columna.anclada)
    .map((columna) => columna.clave);

const vista = useStorage<Vista>(
    `statera.tabla.${props.recurso.clave}.vista`,
    { compacta: false, filtrosVisibles: true, visibilidad: null, orden: null, anclado: null },
    undefined,
    { mergeDefaults: true },
);

/** Una vista guardada no puede resucitar una columna que el recurso ya no tiene. */
function soloConocidas(claves: string[] | null): string[] | null {
    return claves === null ? null : claves.filter((clave) => conocidas.has(clave));
}

const ordenInicial = ((): string[] => {
    const guardado = soloConocidas(vista.value.orden);

    if (guardado === null || guardado.length === 0) {
        return [];
    }

    // Una columna nueva del recurso se coloca al final en vez de desaparecer
    // porque una vista vieja no la nombraba.
    return [...guardado, ...clavesDeclaradas.filter((clave) => !guardado.includes(clave))];
})();

const visibilidadInicial = {
    ...visibilidadDeclarada,
    ...Object.fromEntries(
        Object.entries(vista.value.visibilidad ?? {}).filter(([clave]) => conocidas.has(clave)),
    ),
};

const compacta = computed({
    get: () => vista.value.compacta,
    set: (valor: boolean) => (vista.value.compacta = valor),
});

const filtrosVisibles = computed({
    get: () => vista.value.filtrosVisibles,
    set: (valor: boolean) => (vista.value.filtrosVisibles = valor),
});

const relleno = computed(() => (compacta.value ? 'px-3 py-1.5' : 'px-3 py-2.5'));

const caracteristicas = tableFeatures({
    columnVisibilityFeature,
    columnOrderingFeature,
    columnPinningFeature,
    rowSelectionFeature,
});

const columnas = props.recurso.columnas.map((columna) => ({
    id: columna.clave,
    accessorKey: columna.clave,
    header: columna.etiqueta,
    enableHiding: !columna.anclada,
    size: columna.ancho ? Number.parseFloat(columna.ancho) * 16 : undefined,
}));

const tabla = useTable({
    features: caracteristicas,
    columns: columnas,
    data: toRef(props, 'filas'),
    getRowId: (fila: Fila) => String(fila.id),
    enableRowSelection: props.recurso.seleccionable,
    initialState: {
        columnVisibility: visibilidadInicial,
        columnOrder: ordenInicial,
        columnPinning: {
            start: soloConocidas(vista.value.anclado) ?? ancladasDeclaradas,
            end: [],
        },
    },
});

/* Lo que el usuario cambia en la tabla se guarda; lo guardado se restauró arriba. */
watch(
    () => ({
        visibilidad: { ...(tabla.atoms.columnVisibility?.get() ?? {}) },
        orden: [...(tabla.atoms.columnOrder?.get() ?? [])],
        anclado: [...(tabla.atoms.columnPinning?.get()?.start ?? [])],
    }),
    (estado) => {
        vista.value = { ...vista.value, ...estado };
    },
    { deep: true },
);

/*
 * Filtrar o paginar cambia el conjunto de filas, y una selección hecha sobre el
 * anterior deja de significar nada: se limpia. Mantenerla llevaría a aplicar una
 * acción masiva sobre filas que ya no están a la vista.
 */
watch(
    () => props.meta,
    () => tabla.resetRowSelection(),
);

const porClave = computed<Record<string, Columna>>(() =>
    Object.fromEntries(props.recurso.columnas.map((columna) => [columna.clave, columna])),
);

/*
 * El orden de pintado sale de TanStack, no de la declaración: primero el bloque
 * anclado y después el resto. Leer los átomos aquí es lo que hace que la lista
 * se recalcule al ocultar, mover o anclar una columna.
 */
const columnasTabla = computed(() => {
    void tabla.atoms.columnVisibility?.get();
    void tabla.atoms.columnOrder?.get();
    void tabla.atoms.columnPinning?.get();

    return [
        ...tabla.getStartVisibleLeafColumns(),
        ...tabla.getCenterVisibleLeafColumns(),
        ...tabla.getEndVisibleLeafColumns(),
    ];
});

const visibles = computed<Columna[]>(() =>
    columnasTabla.value
        .map((columna) => porClave.value[columna.id])
        .filter((columna): columna is Columna => columna !== undefined),
);

const ancladas = computed(() => new Set(tabla.atoms.columnPinning?.get()?.start ?? []));
const ocultas = computed(() => props.recurso.columnas.length - visibles.value.length);


const clavesVisibles = computed(() => new Set(visibles.value.map((columna) => columna.clave)));

/**
 * Todas las columnas en el orden real, ocultas incluidas.
 *
 * `visibles` sólo trae las encendidas, y el panel de columnas necesita enseñar
 * también las apagadas: una lista de la que desaparece lo que desactivas no deja
 * volver a encenderlo.
 */
const todasEnOrden = computed<Columna[]>(() => {
    // Las ancladas van delante, como en la tabla. `sort` es estable en JS, así
    // que dentro de cada bloque se conserva el orden guardado.
    const ancladasPrimero = [...ordenActual()].sort(
        (una, otra) => Number(!ancladas.value.has(una)) - Number(!ancladas.value.has(otra)),
    );

    return ancladasPrimero
        .map((clave) => porClave.value[clave])
        .filter((columna): columna is Columna => columna !== undefined);
});

/** Lo declarado pero apagado: exactamente lo que despliega la fila de detalle. */
const columnasOcultasEnFila = computed<Columna[]>(() =>
    todasEnOrden.value.filter((columna) => !clavesVisibles.value.has(columna.clave)),
);

/*
 * Cada filtro se pinta una sola vez: bajo su columna si está a la vista, y en el
 * desplegable «Filtros» si no. La búsqueda no cuelga de ninguna columna —cruza
 * varias— y se queda siempre en la barra.
 */
const busqueda = computed<Filtro | null>(
    () => props.recurso.filtros.find((filtro) => filtro.tipo === 'busqueda') ?? null,
);

function tieneColumnaVisible(filtro: Filtro): boolean {
    return filtro.columna !== null && clavesVisibles.value.has(filtro.columna);
}

const filtroPorColumna = computed<Record<string, Filtro>>(() =>
    Object.fromEntries(
        props.recurso.filtros
            .filter(tieneColumnaVisible)
            .map((filtro) => [filtro.columna as string, filtro]),
    ),
);

const filtrosSueltos = computed(() =>
    props.recurso.filtros.filter(
        (filtro) => filtro.tipo !== 'busqueda' && !tieneColumnaVisible(filtro),
    ),
);

const hayFiltrosDeColumna = computed(() => Object.keys(filtroPorColumna.value).length > 0);
const filaDeFiltros = computed(() => hayFiltrosDeColumna.value && filtrosVisibles.value);

/*
 * Qué columnas están estrechando la tabla. Se marca la cabecera, que es lo
 * único que se ve con la fila de filtros plegada: si no, un filtro puesto ayer
 * y guardado en la URL explica un resultado corto sin decir por qué.
 */
const columnasFiltradas = computed(
    () =>
        new Set(
            Object.entries(filtroPorColumna.value)
                .filter(([, filtro]) => estaActivo(filtros.value[filtro.clave] ?? null))
                .map(([clave]) => clave),
        ),
);

/** Lo buscado, por columna, para marcarlo dentro de la celda. */
const terminos = computed(() => terminosPorColumna(props.recurso.filtros, filtros.value));

/*
 * ── Filas desplegadas ──────────────────────────────────────────────────────
 *
 * Estado local y no `rowExpandingFeature`: esa característica modela sub-filas
 * del propio modelo de datos —agrupaciones, jerarquías— y aquí no hay sub-filas.
 * Hay las mismas columnas de siempre, repartidas de otra manera porque no caben
 * a lo ancho.
 */
const desplegadas = ref(new Set<string>());

function alternarDespliegue(id: number | string): void {
    const clave = String(id);
    const siguiente = new Set(desplegadas.value);

    if (!siguiente.delete(clave)) {
        siguiente.add(clave);
    }

    desplegadas.value = siguiente;
}

/* Cambiar de página o de filtro deja abiertas filas que ya no están. */
watch(
    () => props.meta,
    () => (desplegadas.value = new Set()),
);

/*
 * Un control que nunca hace nada enseña a ignorar los controles: con todas las
 * columnas a la vista, la de desplegar no se pinta.
 */
const hayQueDesplegar = computed(() => columnasOcultasEnFila.value.length > 0);

const seleccionadas = computed(() => Object.keys(tabla.atoms.rowSelection?.get() ?? {}));
const todasSeleccionadas = computed(
    () => props.filas.length > 0 && seleccionadas.value.length === props.filas.length,
);

/** Columnas de datos más la de selección y la de acciones, para los `colspan`. */
const anchoTabla = computed(
    () =>
        visibles.value.length +
        (props.recurso.seleccionable ? 1 : 0) +
        (hayQueDesplegar.value ? 1 : 0) +
        (props.recurso.accionesFila.length > 0 ? 1 : 0),
);

/*
 * ── Columnas ancladas ──────────────────────────────────────────────────────
 *
 * El desplazamiento de cada columna pegada se mide, no se calcula con los
 * anchos declarados: la mitad de las columnas no declara ninguno y el navegador
 * reparte el sobrante como quiere. Sin medir, la segunda columna anclada se
 * monta encima de la primera en cuanto la tabla es más ancha que su caja.
 */
const filaCabecera = ref<HTMLTableRowElement | null>(null);
const anchos = ref<Record<string, number>>({});

/*
 * El ancho VISIBLE de la tabla, que no es el suyo: con trece columnas la tabla
 * mide bastante más que su caja y por eso hay desplazamiento horizontal.
 *
 * La fila de detalle lo necesita porque ocupa todas las columnas: repartida a lo
 * ancho de la tabla real, su tercera columna cae fuera de la pantalla y hay que
 * desplazarse para leerla — exactamente lo que la fila de detalle venía a
 * evitar. Se ancla a este ancho y se queda pegada a la izquierda.
 */
const contenedor = ref<HTMLElement | null>(null);
const anchoVisible = ref(0);

useResizeObserver(contenedor, () => (anchoVisible.value = contenedor.value?.clientWidth ?? 0));
onMounted(() => (anchoVisible.value = contenedor.value?.clientWidth ?? 0));

function medir(): void {
    const celdas = filaCabecera.value?.children;

    if (!celdas) {
        return;
    }

    const medidas: Record<string, number> = {};

    for (const celda of Array.from(celdas)) {
        const clave = (celda as HTMLElement).dataset.columna;

        if (clave !== undefined) {
            medidas[clave] = (celda as HTMLElement).getBoundingClientRect().width;
        }
    }

    anchos.value = medidas;
}

useResizeObserver(filaCabecera, medir);
onMounted(medir);
watch([visibles, () => props.filas, compacta], () => nextTick(medir));

const desplazamientos = computed<Record<string, number>>(() => {
    const salida: Record<string, number> = {};

    if (ancladas.value.size === 0) {
        return salida;
    }

    let acumulado = 0;

    // El mismo problema que ya tuvo la casilla: sin contarla, la primera columna
    // anclada se monta encima de ella al desplazar en horizontal.
    if (hayQueDesplegar.value) {
        salida.__despliegue = 0;
        acumulado = anchos.value.__despliegue ?? 0;
    }

    if (props.recurso.seleccionable) {
        salida.__seleccion = acumulado;
        acumulado += anchos.value.__seleccion ?? 0;
    }

    for (const columna of visibles.value) {
        // `visibles` trae el bloque anclado delante: al primer no anclado se acabó.
        if (!ancladas.value.has(columna.clave)) {
            break;
        }

        salida[columna.clave] = acumulado;
        acumulado += anchos.value[columna.clave] ?? 0;
    }

    return salida;
});

const ultimaPegada = computed<string | null>(() => {
    const claves = Object.keys(desplazamientos.value);

    return claves.length === 0 ? null : (claves[claves.length - 1] ?? null);
});

function estiloPegada(clave: string): { left: string } | undefined {
    const izquierda = desplazamientos.value[clave];

    return izquierda === undefined ? undefined : { left: `${izquierda}px` };
}

function clasePegada(clave: string, capa: 'cabecera' | 'cuerpo'): string | false {
    if (desplazamientos.value[clave] === undefined) {
        return false;
    }

    return cn(
        'sticky',
        capa === 'cabecera' ? 'z-30' : 'z-10 bg-inherit',
        ultimaPegada.value === clave && 'border-r',
    );
}

/*
 * ── Acciones sobre las columnas ────────────────────────────────────────────
 */
function anclar(clave: string, anclada: boolean): void {
    tabla.getColumn(clave)?.pin(anclada ? 'start' : false);
}

function ocultar(clave: string): void {
    tabla.getColumn(clave)?.toggleVisibility(false);
}

/** El orden completo, ocultas incluidas: es lo que guarda TanStack. */
function ordenActual(): string[] {
    const guardado = tabla.atoms.columnOrder?.get() ?? [];

    return guardado.length > 0 ? [...guardado] : [...clavesDeclaradas];
}

function puedeMover(clave: string): { izquierda: boolean; derecha: boolean } {
    const posicion = visibles.value.findIndex((columna) => columna.clave === clave);

    // Mover una columna fuera de su bloque no la desancla ni la ancla: sólo
    // dejaría el orden diciendo una cosa y el anclado otra.
    const mismaRegion = (indice: number): boolean => {
        const vecina = visibles.value[indice];

        return vecina !== undefined && ancladas.value.has(vecina.clave) === ancladas.value.has(clave);
    };

    return { izquierda: mismaRegion(posicion - 1), derecha: mismaRegion(posicion + 1) };
}

function mover(clave: string, paso: -1 | 1): void {
    const posicion = visibles.value.findIndex((columna) => columna.clave === clave);
    const vecina = visibles.value[posicion + paso];

    if (vecina === undefined) {
        return;
    }

    const completo = ordenActual();
    const desde = completo.indexOf(clave);
    const hasta = completo.indexOf(vecina.clave);

    if (desde < 0 || hasta < 0) {
        return;
    }

    [completo[desde], completo[hasta]] = [completo[hasta] as string, completo[desde] as string];

    tabla.setColumnOrder(completo);
}

/**
 * Coloca `clave` en la posición que ocupa `destino`, desplazando el resto.
 *
 * No es un intercambio como el del menú de la cabecera: arrastrar la última
 * columna a la primera posición tiene que dejar las once de en medio en su
 * orden, no mandar la primera al final.
 */
function reordenar(clave: string, destino: string): void {
    const completo = ordenActual();
    const desde = completo.indexOf(clave);
    const hasta = completo.indexOf(destino);

    if (desde < 0 || hasta < 0 || desde === hasta) {
        return;
    }

    completo.splice(hasta, 0, ...completo.splice(desde, 1));

    tabla.setColumnOrder(completo);
}

function restablecerVista(): void {
    tabla.setColumnVisibility({ ...visibilidadDeclarada });
    tabla.setColumnOrder([]);
    tabla.setColumnPinning({ start: [...ancladasDeclaradas], end: [] });

    compacta.value = false;
    filtrosVisibles.value = true;
}

/** Lo que se ve, tal y como se ve. No es un documento del SGSI. */
function exportar(): void {
    descargarCsv(
        props.recurso.clave,
        visibles.value.map((columna) => columna.etiqueta),
        props.filas.map((fila) => visibles.value.map((columna) => textoDeCelda(columna, fila[columna.clave]))),
    );
}

const alineaciones: Record<string, string> = {
    izquierda: 'text-left',
    centro: 'text-center',
    derecha: 'text-right',
};

const confirmando = ref<{ accion: Accion; fila: Fila } | null>(null);

function url(accion: Accion, fila: Fila): string {
    return accion.url.replace('{id}', String(fila.id));
}

function ejecutar(accion: Accion, fila: Fila): void {
    if (accion.confirmacion !== null && confirmando.value === null) {
        confirmando.value = { accion, fila };

        return;
    }

    confirmando.value = null;
    router.visit(url(accion, fila), { method: accion.metodo, preserveScroll: true });
}

/*
 * ── Doble clic sobre la fila ───────────────────────────────────────────────
 *
 * Es un ACELERADOR, no un camino. No llega por teclado, así que el menú «⋯» se
 * queda tal cual: sigue siendo el único sitio donde está escrito lo que se puede
 * hacer con una fila.
 *
 * Qué abre lo declara el recurso en el servidor y llega ya cribado —existe, está
 * permitido y es `GET`—. Aun así se vuelve a comprobar aquí: cuesta dos líneas y
 * lo que evita —un borrado de dos clics— no se deshace.
 */
function accionPorClave(clave: string | null): Accion | null {
    if (clave === null) {
        return null;
    }

    const accion = props.recurso.accionesFila.find((una) => una.clave === clave) ?? null;

    if (accion === null || accion.destructiva || accion.metodo !== 'get') {
        return null;
    }

    return accion;
}

function abrirFila(fila: Fila, evento: MouseEvent): void {
    // Dentro de la casilla o del menú ya hay un control que hace otra cosa.
    if ((evento.target as HTMLElement | null)?.closest('[data-sin-doble-clic]') !== null) {
        return;
    }

    const conModificador = evento.metaKey || evento.ctrlKey;
    const accion = accionPorClave(
        conModificador ? props.recurso.accionAlternativa : props.recurso.accionPorDefecto,
    );

    if (accion === null) {
        return;
    }

    // El doble clic deja media fila seleccionada en azul si no se limpia.
    window.getSelection()?.removeAllRanges();

    router.visit(url(accion, fila));
}

function iconoOrden(clave: string) {
    if (orden.value.clave !== clave) {
        return ArrowUpDownIcon;
    }

    return orden.value.descendente ? ArrowDownIcon : ArrowUpIcon;
}

function sentidoDe(clave: string): 'asc' | 'desc' | null {
    if (orden.value.clave !== clave) {
        return null;
    }

    return orden.value.descendente ? 'desc' : 'asc';
}

/** Qué anuncia el lector de pantalla al pulsar la cabecera de una columna. */
function anuncioOrden(columna: Columna): 'ascending' | 'descending' | 'none' {
    if (orden.value.clave !== columna.clave) {
        return 'none';
    }

    return orden.value.descendente ? 'descending' : 'ascending';
}

const claseCabecera =
    'sticky top-0 z-20 h-10 border-b bg-card/95 px-3 text-xs font-semibold tracking-[0.06em] whitespace-nowrap text-muted-foreground uppercase backdrop-blur-sm';
const claseFiltro = 'sticky top-10 z-20 border-b bg-card/95 px-2 py-1.5 backdrop-blur-sm';
</script>

<template>
    <section class="space-y-3">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <BarraFiltros
                v-if="recurso.filtros.length > 0"
                class="sm:flex-1"
                :busqueda="busqueda"
                :sueltos="filtrosSueltos"
                :todos="recurso.filtros"
                :valores="filtros"
                :hay-filtros-activos="hayFiltrosActivos"
                @aplicar="(clave: string, valor: ValorFiltro) => aplicarFiltro(clave, valor)"
                @limpiar="limpiarFiltros"
            />
            <div v-else class="sm:flex-1" />

            <div class="flex shrink-0 items-center justify-end gap-2">
                <Tooltip v-if="hayFiltrosDeColumna">
                    <TooltipTrigger as-child>
                        <Button
                            :variant="filtrosVisibles ? 'secondary' : 'outline'"
                            size="icon-sm"
                            class="size-9"
                            aria-label="Filtros por columna"
                            :aria-pressed="filtrosVisibles"
                            @click="filtrosVisibles = !filtrosVisibles"
                        >
                            <ListFilterIcon />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        {{ filtrosVisibles ? 'Ocultar los filtros por columna' : 'Filtrar por columna' }}
                    </TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon-sm"
                            class="size-9"
                            :aria-label="compacta ? 'Filas cómodas' : 'Filas compactas'"
                            :aria-pressed="compacta"
                            @click="compacta = !compacta"
                        >
                            <Rows2Icon v-if="compacta" />
                            <Rows3Icon v-else />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ compacta ? 'Filas cómodas' : 'Filas compactas' }}</TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon-sm"
                            class="size-9"
                            :disabled="filas.length === 0"
                            aria-label="Exportar la página en CSV"
                            @click="exportar"
                        >
                            <DownloadIcon />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Exportar esta página en CSV</TooltipContent>
                </Tooltip>

                <SelectorColumnas
                    :columnas="todasEnOrden"
                    :visibles="clavesVisibles"
                    :ancladas="ancladas"
                    :ocultas="ocultas"
                    @alternar="(clave: string) => tabla.getColumn(clave)?.toggleVisibility()"
                    @reordenar="reordenar"
                    @restablecer="restablecerVista"
                />

                <Link v-for="accion in recurso.accionesGenerales" :key="accion.clave" :href="accion.url">
                    <Button size="sm" class="h-9 gap-1.5">
                        <IconoAccion :nombre="accion.icono" />
                        {{ accion.etiqueta }}
                    </Button>
                </Link>
            </div>
        </header>

        <!-- La barra de selección entra y sale en lugar de aparecer de golpe:
             el salto seco desplazaba la tabla y hacía perder el sitio. -->
        <AnimatePresence>
            <motion.div
                v-if="recurso.seleccionable && seleccionadas.length > 0"
                :variants="reducido ? undefined : barraContextual"
                :initial="reducido ? false : 'oculto'"
                animate="visible"
                exit="oculto"
                class="overflow-hidden"
            >
                <div
                    class="flex flex-wrap items-center gap-3 rounded-md border border-primary/30 bg-accent px-3 py-2 text-sm"
                >
                    <span class="font-medium">
                        <span class="cifra">{{ seleccionadas.length }}</span>
                        seleccionadas
                    </span>

                    <Button
                        v-for="accion in recurso.accionesMasivas"
                        :key="accion.clave"
                        size="sm"
                        variant="outline"
                        class="gap-1.5"
                        @click="emit('masiva', accion, seleccionadas)"
                    >
                        <IconoAccion :nombre="accion.icono" />
                        {{ accion.etiqueta }}
                    </Button>

                    <Button size="sm" variant="ghost" @click="tabla.resetRowSelection()">Deseleccionar</Button>
                </div>
            </motion.div>
        </AnimatePresence>

        <!-- Mismo envoltorio que una `Card`: `rounded-xl` con anillo en vez de
             borde. Una tabla con un radio y una tarjeta con otro en la misma
             pantalla se nota, aunque nadie sepa señalar qué falla. -->
        <div class="relative overflow-hidden rounded-xl bg-card shadow-xs ring-1 ring-foreground/10">
            <!-- Filtrar es una consulta de servidor: sin este hilo, escribir en
                 un filtro deja la tabla quieta y parece que no ha pasado nada. -->
            <div
                v-if="cargando && filas.length > 0"
                class="absolute inset-x-0 top-0 z-40 h-0.5 overflow-hidden bg-primary/10"
                aria-hidden="true"
            >
                <div :class="reducido ? 'h-full w-full bg-primary/40' : 'h-full w-1/3 bg-primary hilo-de-carga'" />
            </div>

            <!-- `max-h` + cabecera pegajosa: con cien requisitos en pantalla,
                 perder los nombres de columna al bajar deja la tabla ilegible. -->
            <div ref="contenedor" class="max-h-[calc(100dvh-19rem)] overflow-auto" :aria-busy="cargando">
                <table class="w-full caption-bottom border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr ref="filaCabecera">
                            <th
                                v-if="hayQueDesplegar"
                                data-columna="__despliegue"
                                :class="cn(claseCabecera, 'w-9', clasePegada('__despliegue', 'cabecera'))"
                                :style="estiloPegada('__despliegue')"
                            >
                                <span class="sr-only">Desplegar</span>
                            </th>

                            <th
                                v-if="recurso.seleccionable"
                                data-columna="__seleccion"
                                :class="cn(claseCabecera, 'w-10', clasePegada('__seleccion', 'cabecera'))"
                                :style="estiloPegada('__seleccion')"
                            >
                                <Checkbox
                                    :model-value="todasSeleccionadas"
                                    aria-label="Seleccionar todas las filas de la página"
                                    @update:model-value="tabla.toggleAllRowsSelected(!todasSeleccionadas)"
                                />
                            </th>

                            <th
                                v-for="columna in visibles"
                                :key="columna.clave"
                                :data-columna="columna.clave"
                                :style="{
                                    ...(columna.ancho ? { width: columna.ancho } : {}),
                                    ...estiloPegada(columna.clave),
                                }"
                                :aria-sort="columna.ordenable ? anuncioOrden(columna) : undefined"
                                :class="
                                    cn(
                                        claseCabecera,
                                        alineaciones[columna.alineacion],
                                        columnasFiltradas.has(columna.clave) &&
                                            'border-b-2 border-b-primary text-foreground',
                                        clasePegada(columna.clave, 'cabecera'),
                                    )
                                "
                            >
                                <div
                                    class="group/columna flex items-center gap-1"
                                    :class="{
                                        'justify-end': columna.alineacion === 'derecha',
                                        'justify-center': columna.alineacion === 'centro',
                                    }"
                                >
                                    <!-- `uppercase` explícito: el navegador le pone
                                         `text-transform: none` a los `<button>`, así
                                         que las columnas ordenables salían en caja
                                         normal y las demás en versalitas. -->
                                    <button
                                        v-if="columna.ordenable"
                                        type="button"
                                        class="inline-flex min-w-0 items-center gap-1 rounded uppercase transition-colors hover:text-foreground"
                                        :title="columna.ayuda ?? undefined"
                                        @click="ordenarPor(columna.clave)"
                                    >
                                        <span class="truncate">{{ columna.etiqueta }}</span>
                                        <component
                                            :is="iconoOrden(columna.clave)"
                                            class="size-3.5 shrink-0 transition-opacity"
                                            :class="orden.clave === columna.clave ? 'text-primary opacity-100' : 'opacity-40'"
                                        />
                                    </button>
                                    <span v-else class="min-w-0 truncate" :title="columna.ayuda ?? undefined">
                                        {{ columna.etiqueta }}
                                    </span>

                                    <span v-if="columnasFiltradas.has(columna.clave)" class="sr-only">
                                        , con filtro
                                    </span>

                                    <MenuColumna
                                        :columna="columna"
                                        :sentido="sentidoDe(columna.clave)"
                                        :anclada="ancladas.has(columna.clave)"
                                        :puede-anclar="!columna.anclada"
                                        :puede-ocultar="!columna.anclada"
                                        :puede-mover="puedeMover(columna.clave)"
                                        @ordenar="(descendente: boolean) => ordenarPor(columna.clave, descendente)"
                                        @anclar="(valor: boolean) => anclar(columna.clave, valor)"
                                        @ocultar="ocultar(columna.clave)"
                                        @mover="(paso: -1 | 1) => mover(columna.clave, paso)"
                                    />
                                </div>
                            </th>

                            <th v-if="recurso.accionesFila.length > 0" :class="cn(claseCabecera, 'w-12')">
                                <span class="sr-only">Acciones</span>
                            </th>
                        </tr>

                        <!-- La fila de filtros: cada control debajo del dato que
                             estrecha, que es donde se busca. Se pliega entera
                             desde la barra y la preferencia se recuerda. -->
                        <tr v-if="filaDeFiltros">
                            <th
                                v-if="hayQueDesplegar"
                                :class="cn(claseFiltro, clasePegada('__despliegue', 'cabecera'))"
                                :style="estiloPegada('__despliegue')"
                            >
                                <span class="sr-only">Sin filtro</span>
                            </th>

                            <th
                                v-if="recurso.seleccionable"
                                :class="cn(claseFiltro, clasePegada('__seleccion', 'cabecera'))"
                                :style="estiloPegada('__seleccion')"
                            >
                                <span class="sr-only">Sin filtro</span>
                            </th>

                            <th
                                v-for="columna in visibles"
                                :key="columna.clave"
                                :class="cn(claseFiltro, clasePegada(columna.clave, 'cabecera'))"
                                :style="estiloPegada(columna.clave)"
                            >
                                <FiltroColumna
                                    v-if="filtroPorColumna[columna.clave]"
                                    :filtro="filtroPorColumna[columna.clave]!"
                                    :valor="filtros[filtroPorColumna[columna.clave]!.clave] ?? null"
                                    @aplicar="(clave: string, valor: ValorFiltro) => aplicarFiltro(clave, valor)"
                                />
                            </th>

                            <th v-if="recurso.accionesFila.length > 0" :class="claseFiltro" />
                        </tr>
                    </thead>

                    <tbody>
                        <!-- El esqueleto imita la tabla real, columna a columna:
                             cinco barras de ancho completo no reservaban el sitio
                             de nada y el contenido saltaba al llegar. -->
                        <template v-if="cargando && filas.length === 0">
                            <tr v-for="n in 6" :key="`esqueleto-${n}`">
                                <td v-if="hayQueDesplegar" class="border-b px-3 py-2.5">
                                    <Skeleton class="size-4 rounded-sm" />
                                </td>
                                <td v-if="recurso.seleccionable" class="border-b px-3 py-2.5">
                                    <Skeleton class="size-4 rounded-sm" />
                                </td>
                                <td v-for="columna in visibles" :key="columna.clave" class="border-b px-3 py-2.5">
                                    <Skeleton
                                        class="h-4"
                                        :class="columna.tipo === 'badge' ? 'w-20 rounded-full' : 'w-full'"
                                    />
                                </td>
                                <td v-if="recurso.accionesFila.length > 0" class="border-b px-3 py-2.5">
                                    <Skeleton class="ml-auto size-5 rounded-md" />
                                </td>
                            </tr>
                        </template>

                        <!-- Ocultar la última columna deja una tabla que no es un
                             vacío ni un error: hay datos, no hay dónde ponerlos. -->
                        <tr v-else-if="visibles.length === 0">
                            <td :colspan="anchoTabla || 1" class="px-3 py-14 text-center">
                                <p class="text-sm font-medium">No queda ninguna columna a la vista</p>
                                <Button variant="outline" size="sm" class="mt-4" @click="restablecerVista">
                                    Restablecer la vista
                                </Button>
                            </td>
                        </tr>

                        <tr v-else-if="filas.length === 0">
                            <td :colspan="anchoTabla" class="p-0">
                                <div v-if="hayFiltrosActivos" class="px-3 py-14 text-center">
                                    <p class="text-sm font-medium">Ningún resultado con estos filtros</p>
                                    <p class="mt-1.5 text-sm text-muted-foreground">
                                        Prueba a quitar alguno o a ampliar el rango.
                                    </p>
                                    <Button variant="outline" size="sm" class="mt-4" @click="limpiarFiltros">
                                        Quitar los filtros
                                    </Button>
                                </div>

                                <EstadoVacio
                                    v-else
                                    :icono="InboxIcon"
                                    :titulo="recurso.etiquetas.vacio ?? 'Todavía no hay nada aquí'"
                                    :accion="
                                        recurso.accionesGenerales[0]
                                            ? {
                                                  etiqueta: recurso.accionesGenerales[0].etiqueta,
                                                  href: recurso.accionesGenerales[0].url,
                                              }
                                            : undefined
                                    "
                                />
                            </td>
                        </tr>

                        <template v-for="fila in filas" v-else :key="fila.id">
                        <tr
                            :class="
                                cn(
                                    'transition-colors hover:bg-muted/50 has-[:focus-visible]:bg-muted/50',
                                    // El fondo va en la fila y las celdas ancladas lo
                                    // heredan con `bg-inherit`; sin un fondo opaco, el
                                    // contenido que pasa por debajo se ve a través.
                                    tabla.getRow(String(fila.id))?.getIsSelected() ? 'bg-accent/40' : 'bg-card',
                                    // Sólo apunta a mano si hay a dónde ir.
                                    recurso.accionPorDefecto !== null && 'cursor-pointer',
                                )
                            "
                            @dblclick="(evento: MouseEvent) => abrirFila(fila, evento)"
                        >
                            <td
                                v-if="hayQueDesplegar"
                                data-sin-doble-clic
                                :class="cn('border-b', relleno, clasePegada('__despliegue', 'cuerpo'))"
                                :style="estiloPegada('__despliegue')"
                            >
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    :aria-expanded="desplegadas.has(String(fila.id))"
                                    :aria-label="
                                        desplegadas.has(String(fila.id))
                                            ? 'Ocultar el resto de columnas'
                                            : 'Ver el resto de columnas'
                                    "
                                    @click="alternarDespliegue(fila.id)"
                                >
                                    <ChevronDownIcon v-if="desplegadas.has(String(fila.id))" />
                                    <ChevronRightIcon v-else />
                                </Button>
                            </td>

                            <td
                                v-if="recurso.seleccionable"
                                data-sin-doble-clic
                                :class="cn('border-b', relleno, clasePegada('__seleccion', 'cuerpo'))"
                                :style="estiloPegada('__seleccion')"
                            >
                                <Checkbox
                                    :model-value="tabla.getRow(String(fila.id))?.getIsSelected() ?? false"
                                    :aria-label="`Seleccionar la fila ${fila.id}`"
                                    @update:model-value="tabla.getRow(String(fila.id))?.toggleSelected()"
                                />
                            </td>

                            <td
                                v-for="columna in visibles"
                                :key="columna.clave"
                                :class="
                                    cn(
                                        'border-b',
                                        relleno,
                                        alineaciones[columna.alineacion],
                                        clasePegada(columna.clave, 'cuerpo'),
                                    )
                                "
                                :style="estiloPegada(columna.clave)"
                            >
                                <CeldaValor
                                    :columna="columna"
                                    :valor="fila[columna.clave]"
                                    :terminos="terminos[columna.clave]"
                                />
                            </td>

                            <td
                                v-if="recurso.accionesFila.length > 0"
                                data-sin-doble-clic
                                :class="cn('border-b text-right', relleno)"
                            >
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon-sm" aria-label="Acciones de la fila">
                                            <MoreHorizontalIcon />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            v-for="accion in recurso.accionesFila"
                                            :key="accion.clave"
                                            :class="accion.destructiva ? 'text-destructive' : undefined"
                                            @select="ejecutar(accion, fila)"
                                        >
                                            <IconoAccion :nombre="accion.icono" />
                                            {{ accion.etiqueta }}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>

                        <tr v-if="hayQueDesplegar && desplegadas.has(String(fila.id))">
                            <td :colspan="anchoTabla" class="border-b bg-card p-0">
                                <div
                                    class="sticky left-0"
                                    :style="anchoVisible > 0 ? { width: `${anchoVisible}px` } : undefined"
                                >
                                    <FilaDetalle :columnas="columnasOcultasEnFila" :fila="fila" />
                                </div>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <PaginacionTabla
                :meta="meta"
                :tamanos="recurso.tamanosPagina"
                @pagina="irAPagina"
                @tamano="cambiarTamano"
            />
        </div>

        <ConfirmacionAccion
            v-if="confirmando"
            :accion="confirmando.accion"
            @cancelar="confirmando = null"
            @confirmar="ejecutar(confirmando.accion, confirmando.fila)"
        />
    </section>
</template>
