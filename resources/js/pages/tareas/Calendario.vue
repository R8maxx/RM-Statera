<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import BarraFiltros from '@/components/tabla/BarraFiltros.vue';
import ConmutadorVista from '@/components/tarea/ConmutadorVista.vue';
import { Button } from '@/components/ui/button';
import { useFiltrosServidor } from '@/composables/useFiltrosServidor';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFecha } from '@/lib/celdas';
import { Link } from '@inertiajs/vue3';
import { CalendarDaysIcon, ChevronLeftIcon, ChevronRightIcon } from '@lucide/vue';
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
}>();

/** Cuántos caben en una casilla antes de resumir el resto. */
const POR_DIA = 3;

const cabeceras = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

/*
 * El mes viaja en la URL, así que hay que devolvérselo al servidor en cada
 * recarga: sin esto, filtrar te manda al mes de hoy.
 */
const {
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

const sueltos = computed(() => props.filtros.filter((filtro) => filtro.tipo !== 'busqueda'));

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
 * van escritas enteras: Tailwind analiza el fichero como texto y una compuesta
 * en ejecución no se genera.
 */
const tonos: Record<string, string> = {
    caducada: 'bg-destructive/10 text-destructive',
    no_iniciado: 'bg-estado-no-iniciado-suave text-estado-no-iniciado',
    en_progreso: 'bg-estado-en-progreso-suave text-estado-en-progreso',
    planificado: 'bg-estado-planificado-suave text-estado-planificado',
    implantado: 'bg-estado-implantado-suave text-estado-implantado',
    no_aplica: 'bg-estado-no-aplica-suave text-estado-no-aplica',
};

const claseDe = (vencimiento: Vencimiento): string =>
    tonos[vencimiento.estadoTono] ?? tonos.no_iniciado;

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
</script>

<template>
    <AppLayout titulo="Calendario">
        <CabeceraPagina
            titulo="Calendario"
            descripcion="Qué hay que atender y cuándo: plazos de tareas y caducidades de evidencias en el mismo sitio."
        >
            <template #acciones>
                <ConmutadorVista />
            </template>
        </CabeceraPagina>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <Link :href="`/tareas/calendario?mes=${rejilla.anterior}`">
                <Button variant="outline" size="icon-sm" aria-label="Mes anterior">
                    <ChevronLeftIcon class="size-4" />
                </Button>
            </Link>

            <h2 class="min-w-48 text-base font-medium">{{ rejilla.etiqueta }}</h2>

            <Link :href="`/tareas/calendario?mes=${rejilla.siguiente}`">
                <Button variant="outline" size="icon-sm" aria-label="Mes siguiente">
                    <ChevronRightIcon class="size-4" />
                </Button>
            </Link>

            <Link href="/tareas/calendario" class="ml-1">
                <Button variant="ghost" size="sm">Hoy</Button>
            </Link>

            <BarraFiltros
                v-if="filtros.length > 0"
                class="sm:ml-auto"
                :busqueda="busqueda"
                :sueltos="sueltos"
                :todos="filtros"
                :valores="valores"
                :hay-filtros-activos="hayFiltrosActivos"
                @aplicar="aplicarFiltro"
                @limpiar="limpiarFiltros"
            />
        </div>

        <EstadoVacio
            v-if="vencimientos.length === 0"
            :icono="CalendarDaysIcon"
            titulo="No vence nada este mes"
            :descripcion="
                hayFiltrosActivos
                    ? 'Ningún vencimiento cumple estos filtros. Prueba a quitar alguno o cambia de mes.'
                    : 'Ni plazos de tareas ni caducidades de evidencias. Cambia de mes para ver otros.'
            "
        />

        <!--
            La rejilla desde `md`. Siete columnas a 400 px no se leen: por debajo
            va la agenda, que es la misma información en la forma que cabe.
        -->
        <div v-else class="hidden md:block">
            <div class="grid grid-cols-7 gap-px overflow-hidden rounded-xl border bg-border">
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
                        en la pantalla.
                    -->
                    <p v-if="del(dia.dia).length > POR_DIA" class="mt-1 text-xs text-muted-foreground">
                        y {{ del(dia.dia).length - POR_DIA }} más
                    </p>
                </div>
            </div>

            <!-- § 3: leyenda siempre que haya dos tonos o más. -->
            <ul v-if="leyenda.length > 1" class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5">
                <li
                    v-for="tramo in leyenda"
                    :key="tramo.tono"
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <span class="size-2.5 rounded-sm" :class="tonos[tramo.tono] ?? tonos.no_iniciado" />
                    {{ tramo.etiqueta }}
                </li>
            </ul>
        </div>

        <!-- La agenda: la misma información, en la forma que cabe en un móvil. -->
        <div v-if="vencimientos.length > 0" class="md:hidden">
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

        <p class="mt-4 text-xs text-muted-foreground">
            Se ven los plazos de las tareas abiertas y las caducidades de las evidencias. Cuando lleguen los
            demás vencimientos periódicos —revisión por la dirección, auditoría interna— aparecerán aquí.
        </p>
    </AppLayout>
</template>
