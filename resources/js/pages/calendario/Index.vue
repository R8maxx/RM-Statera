<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import HiloCarga from '@/components/HiloCarga.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import FiltroFuentes from '@/components/calendario/FiltroFuentes.vue';
import PanelDia from '@/components/calendario/PanelDia.vue';
import BarraFiltros from '@/components/tabla/BarraFiltros.vue';
import { Button } from '@/components/ui/button';
import { useFiltrosServidor } from '@/composables/useFiltrosServidor';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFecha } from '@/lib/celdas';
import { tono } from '@/lib/tonos';
import { Link } from '@inertiajs/vue3';
import { CalendarDaysIcon, ChevronLeftIcon, ChevronRightIcon } from '@lucide/vue';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { curva, duracion } from '@/lib/motion';
import { motion } from 'motion-v';
import { computed, toRef } from 'vue';

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
    };
    vencimientos: Vencimiento[];
    filtros: Filtro[];
    filtrosAplicados: Record<string, string | string[]>;
    /** Lo que el filtro de responsable deja fuera por no tener uno. */
    excluidasPorResponsable: string[];
}>();

/** Cuántos caben en una casilla antes de resumir el resto. */
const POR_DIA = 3;

const cabeceras = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

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
    only: ['vencimientos', 'filtrosAplicados'],
    extras: () => ({ mes: props.rejilla.mes }),
});

const busqueda = computed<Filtro | null>(
    () => props.filtros.find((filtro) => filtro.tipo === 'busqueda') ?? null,
);

/*
 * El filtro de fuente sale de aquí: sube a la fila de chips, que es a la vez
 * filtro y leyenda. Calcularlo excluyéndolo —en vez de duplicar la lista— es lo
 * que impide que el control acabe viviendo en dos sitios.
 */
const filtroFuente = computed<Filtro | null>(
    () => props.filtros.find((filtro) => filtro.clave === 'fuente') ?? null,
);

const sueltos = computed(() =>
    props.filtros.filter((filtro) => filtro.tipo !== 'busqueda' && filtro.clave !== 'fuente'),
);

/*
 * **Y la misma lista va a `todos`.** Sólo se excluía de `sueltos`, así que
 * `chipsDe()` —que recorre `todos`— seguía pintando «Qué: Tarea» como chip
 * descartable al lado de los chips de `FiltroFuentes`: el mismo control dos veces
 * en la misma pantalla, que es justo lo que el comentario de arriba dice evitar.
 */

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

/*
 * Cuatro escalones sólidos, sin alfa.
 *
 * Antes eran `bg-muted/40` y `bg-muted/20` sobre `bg-card`: dos transparencias
 * casi idénticas, y las dos en el mismo atributo, así que decidía el orden en
 * que Tailwind emite las clases y no el código. Es el mismo fallo que CLAUDE.md
 * documenta para las celdas ancladas de la tabla, y `app.css` ya trae la
 * escalera compuesta para no repetirlo.
 */
const fondos: Record<string, string> = {
    normal: 'bg-card',
    finDeSemana: 'bg-superficie',
    fuera: 'bg-muted',
    hoy: 'bg-accent',
};

function fondoDe(dia: Dia): string {
    if (dia.esHoy) {
        return fondos.hoy;
    }

    if (!dia.delMes) {
        return fondos.fuera;
    }

    return dia.finDeSemana ? fondos.finDeSemana : fondos.normal;
}

/*
 * El color dice QUÉ es la cosa, y el rojo dice que se pasó de fecha. Las clases
 * salen de `lib/tonos.ts`, que es donde vive el vocabulario entero.
 */
const claseDe = (vencimiento: Vencimiento): string => tono(vencimiento.estadoTono).badge;

/**
 * Lo que se lee en voz alta y lo que sale al pasar el ratón.
 *
 * § 11: un estado no puede depender sólo del color. En la rejilla el título va
 * truncado, así que este texto es además lo único que da la fecha.
 */
const descripcion = (vencimiento: Vencimiento): string =>
    `${vencimiento.titulo} · ${vencimiento.estadoEtiqueta} · ${vencimiento.fecha}`;

/** Los tonos que hay de verdad este mes, para la leyenda. */
const leyenda = computed(() => {
    const vistos = new Map<string, string>();

    for (const vencimiento of props.vencimientos) {
        vistos.set(vencimiento.estadoTono, vencimiento.estadoEtiqueta);
    }

    return [...vistos].map(([tono, etiqueta]) => ({ tono, etiqueta }));
});

/** Sólo los días con algo, para la agenda de móvil. */
const agenda = computed(() =>
    props.rejilla.dias
        .filter((dia) => del(dia.dia).length > 0)
        .map((dia) => ({ ...dia, vencimientos: del(dia.dia) })),
);

const fechaLarga = (dia: string): string => formatoFecha.format(new Date(`${dia}T00:00:00`));

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
 *
 * Y la dirección no es adorno: la rejilla entera se repinta y sin ella no hay
 * forma de saber si se pulsó adelante o atrás — las seis semanas son siempre
 * seis, así que ni siquiera cambia de alto.
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
</script>

<template>
    <AppLayout titulo="Calendario">
        <!--
            Sin conmutador de vistas: esto dejó de ser una de las tres formas de
            mirar el plan de acción. Enseña vencimientos de siete registros
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

            <h2 class="min-w-48 text-base font-medium">{{ rejilla.etiqueta }}</h2>

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
            Filtro y leyenda a la vez. Ver `FiltroFuentes`: con siete fuentes el
            icono es el único canal que las separa, y esta fila es su clave.
        -->
        <FiltroFuentes
            v-if="filtroFuente"
            :filtro="filtroFuente"
            :seleccionadas="fuentesActivas"
            @aplicar="aplicarFiltro"
        />

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
            <motion.div
                class="grid grid-cols-7 gap-px overflow-hidden rounded-xl border bg-border"
                :initial="entradaRejilla"
                :animate="{ opacity: 1, x: 0 }"
                :transition="{ duration: reducido ? 0 : duracion.normal, ease: curva }"
            >
                <div
                    v-for="(inicial, indice) in cabeceras"
                    :key="inicial"
                    class="bg-card py-2 text-center text-xs font-medium"
                    :class="indice >= 5 ? 'text-muted-foreground/60' : 'text-muted-foreground'"
                >
                    {{ inicial }}
                </div>

                <div
                    v-for="dia in rejilla.dias"
                    :key="dia.dia"
                    class="relative min-h-28 p-2"
                    :class="fondoDe(dia)"
                >
                    <!--
                        La barra de hoy, el mismo gesto que marca el ítem activo
                        del sidebar. El color por sí solo no bastaría: `accent`
                        es un teal muy pálido.
                    -->
                    <span v-if="dia.esHoy" class="absolute inset-x-0 top-0 h-0.5 bg-primary" aria-hidden="true" />

                    <p class="mb-1.5 text-right text-xs">
                        <span
                            class="inline-flex size-5 items-center justify-center rounded-full"
                            :class="[
                                dia.esHoy ? 'bg-primary font-medium text-primary-foreground' : '',
                                dia.delMes ? 'text-foreground' : 'text-muted-foreground/60',
                            ]"
                        >
                            {{ dia.numero }}
                        </span>
                    </p>

                    <ul class="space-y-1">
                        <li
                            v-for="vencimiento in del(dia.dia).slice(0, POR_DIA)"
                            :key="`${vencimiento.fuente}-${vencimiento.id}`"
                        >
                            <Link
                                :href="vencimiento.url"
                                class="flex items-center gap-1 rounded-md px-1.5 py-1 text-xs transition-opacity hover:opacity-80"
                                :class="claseDe(vencimiento)"
                                :title="descripcion(vencimiento)"
                            >
                                <IconoTipo :nombre="vencimiento.icono" />
                                <span class="truncate">{{ vencimiento.titulo }}</span>
                                <!-- El estado en texto, que es lo que § 11 pide
                                     y lo que el color por sí solo no da. -->
                                <span class="sr-only">· {{ vencimiento.estadoEtiqueta }}</span>
                            </Link>
                        </li>
                    </ul>

                    <!--
                        Un tope por día, con su salida. Sin él, un día con doce
                        vencimientos estira la fila entera y el mes deja de caber
                        en la pantalla. La salida es un botón y no un párrafo: lo
                        que esconde el tope tiene que tener puerta, y con el
                        tabulador.
                    -->
                    <PanelDia
                        v-if="del(dia.dia).length > POR_DIA"
                        :dia="dia.dia"
                        :vencimientos="del(dia.dia)"
                        :ocultos="del(dia.dia).length - POR_DIA"
                    />
                </div>
            </motion.div>

            <!--
                § 3: leyenda siempre que haya dos tonos o más. Con rótulo, porque
                ahora hay dos filas de claves y la de arriba dice QUÉ es la cosa:
                sin él, ésta parecería más de lo mismo.
            -->
            <div v-if="leyenda.length > 1" class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5">
                <span class="text-xs font-medium text-muted-foreground">Cómo va</span>
            <ul class="flex flex-wrap gap-x-4 gap-y-1.5">
                <li
                    v-for="tramo in leyenda"
                    :key="tramo.tono"
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <span class="size-2.5 rounded-sm" :class="tono(tramo.tono).badge" />
                    {{ tramo.etiqueta }}
                </li>
            </ul>
            </div>
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
                                :class="claseDe(vencimiento)"
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
            la fila de chips, que se genera del enum: un recuento dentro de un
            texto envejece cada vez que el producto crece.
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
