<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import BotonEstado from '@/components/BotonEstado.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import GraficaSerie from '@/components/grafica/GraficaSerie.vue';
import HistoricoTransiciones, { type Transicion } from '@/components/HistoricoTransiciones.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import TendenciaIndicador from '@/components/objetivo/TendenciaIndicador.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import BarraPlazo, { type Plazo } from '@/components/vulnerabilidad/BarraPlazo.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible } from '@/lib/celdas';
import { Link, router, useForm } from '@inertiajs/vue3';
import { EllipsisIcon, InfoIcon, LinkIcon, PencilLineIcon, PlusIcon, ShieldCheckIcon } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Destino {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
    exigeMotivo: boolean;
    permiso: string;
}

interface IndicadorVinculado {
    id: number;
    codigo: string;
    nombre: string;
    periodicidad: string;
    objetivo: string | null;
    ultimoValor: string | null;
    ultimoPeriodo: string | null;
    cumplimiento: string;
    cumplimientoEtiqueta: string;
    cumplimientoTono: string;
    cumplimientoIcono: string;
    fraccion: string | null;
    porcentaje: boolean;
    serie: App.Http.Resources.Metrica.PuntoSerie[];
}

interface Actuacion {
    id: number;
    titulo: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    responsable: string | null;
    prioridad: string;
    plazoEtiqueta: string;
    plazoTono: string;
    fecha: string | null;
    coste: string | null;
}

interface Objetivo {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    recursos: string | null;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    esComprometido: boolean;
    responsable: string | null;
    fecha_objetivo: string | null;
    fechaCierre: string | null;
    aprobadoPor: string | null;
    aprobadoEn: string | null;
    notaAprobacion: string | null;
    plazoEtiqueta: string;
    plazoTono: string;
}

interface Avance {
    enObjetivo: number;
    medidos: number;
    total: number;
    etiqueta: string;
    tono: string;
    contradice: boolean;
}

/**
 * La ficha de un objetivo de seguridad: las cinco preguntas de la cláusula 6.2.
 *
 * **Un solo elemento fuerte** (DESIGN.md § 1), arriba y partido en dos: «¿va
 * bien?», que contestan sus indicadores, y «¿le da tiempo?», que contesta el
 * plazo desde la firma. Son las dos preguntas que hace la revisión por la
 * dirección de cada objetivo, y antes eran tres badges sueltos bajo el título.
 *
 * Debajo, lo que se hará (a) y cómo se evalúa (e) en tabla, y en el lateral
 * quién (c), para cuándo (d) y con qué (b), cada dato con su letra: es como lo
 * busca el auditor.
 */
const props = defineProps<{
    objetivo: Objetivo;
    avance: Avance;
    plazo: Plazo | null;
    indicadores: IndicadorVinculado[];
    actuaciones: Actuacion[];
    coste: { total: string; sinEstimar: number };
    transiciones: Destino[];
    historial: Transicion[];
    prioridades: Opcion[];
    responsables: Opcion[];
    indicadoresDisponibles: Opcion[];
    puedeGestionar: boolean;
    puedeAprobar: boolean;
}>();

const dias = (n: number): string => (n === 1 ? '1 día' : `${n} días`);

/** El último cambio de estado, para el «desde» de la cabecera. Llega del más nuevo al más viejo. */
const ultimoCambio = computed(() => props.historial[0] ?? null);

/*
 * Lo que un auditor va a pedir y falta. Sólo mientras está vivo: a un objetivo
 * cerrado ya no se le completa nada.
 */
const vivo = computed(() => !['alcanzado', 'no_alcanzado', 'retirado'].includes(props.objetivo.estado));

const pendientes = computed(() => {
    if (!vivo.value) {
        return [];
    }

    return [
        props.objetivo.fecha_objetivo === null ? { etiqueta: 'Fecha objetivo', indicador: false } : null,
        props.objetivo.responsable === null ? { etiqueta: 'Responsable', indicador: false } : null,
        props.objetivo.recursos === null ? { etiqueta: 'Recursos', indicador: false } : null,
        props.indicadores.length === 0 ? { etiqueta: 'Indicador', indicador: true } : null,
    ].filter((uno): uno is { etiqueta: string; indicador: boolean } => uno !== null);
});

/* --- Cómo va --- */

/*
 * Con un solo indicador, su cifra **es** la lectura del objetivo y va grande con
 * su serie. Con varios no hay una cifra que los resuma sin inventar una
 * ponderación, así que lo grande es el recuento de `Avance` y la tendencia baja
 * a cada fila.
 */
const principal = computed(() => (props.indicadores.length === 1 ? props.indicadores[0] : null));

/*
 * La cifra grande cuenta, como en la ficha del indicador, porque resume: es la
 * lectura de la pantalla y no una celda. Sólo el porcentaje, cuyo formato es
 * trivial; el resto se queda escrito tal como lo manda `UnidadIndicador`.
 */
const cifraPrincipal = computed(() => {
    const ultimo = principal.value?.serie.at(-1);

    return principal.value?.porcentaje && ultimo ? ultimo.valor : null;
});

/* --- Para cuándo --- */

const resumenPlazo = computed(() => {
    const plazo = props.plazo;

    if (plazo === null) {
        return '';
    }

    if (plazo.corre) {
        return plazo.fuera > 0
            ? `El plazo era el ${fechaLegible(plazo.limite)} y sigue sin cerrarse.`
            : `${Math.round((plazo.transcurridos / Math.max(plazo.dias, 1)) * 100)} % del plazo consumido, de ${dias(plazo.dias)} desde la firma.`;
    }

    return plazo.fuera > 0
        ? `Se cerró ${dias(plazo.fuera)} después de la fecha objetivo.`
        : `Se cerró dentro de plazo, a los ${dias(plazo.transcurridos)} de la firma.`;
});

/* --- El ciclo --- */

/*
 * Tres transiciones piden algo escrito, así que el primer clic en ellas no
 * envía: abre el bloque de la nota. Mismo gesto que en una no conformidad y por
 * lo mismo — pedir la nota siempre convierte en trámite el único sitio donde se
 * dice qué pasó.
 */
const destino = ref<Destino | null>(null);
const nota = ref('');
const enviando = ref(false);

const disponibles = computed(() =>
    props.transiciones.filter((paso) =>
        paso.permiso === 'objetivos.aprobar' ? props.puedeAprobar : props.puedeGestionar,
    ),
);

/*
 * Los que cierran o firman van arriba y a lo ancho; volver al borrador y
 * retirar, debajo y sin tinte. Retirar no es destructivo —el histórico lo
 * conserva todo—, pero tampoco es lo que se viene a hacer aquí.
 */
const secundarios = ['propuesto', 'retirado'];
const principales = computed(() => disponibles.value.filter((paso) => !secundarios.includes(paso.valor)));
const menores = computed(() => disponibles.value.filter((paso) => secundarios.includes(paso.valor)));

/*
 * Aprobar sin fecha lo rechaza el dominio; se dice antes de pulsar, y no
 * después con un error. Reabrir un cerrado siempre tiene fecha: el `CHECK` la
 * exigió al firmarlo.
 */
const faltaPlazo = computed(() => props.objetivo.fecha_objetivo === null);
const bloqueado = (paso: Destino): boolean => paso.valor === 'aprobado' && faltaPlazo.value;

const etiquetaNota = computed(() => {
    if (destino.value === null) {
        return 'Nota';
    }

    return destino.value.valor === 'no_alcanzado'
        ? 'Por qué no se alcanzó'
        : destino.value.valor === 'retirado'
          ? 'Por qué se retira'
          : 'Qué cambia';
});

function mover(paso: Destino): void {
    if (paso.exigeMotivo && destino.value?.valor !== paso.valor) {
        destino.value = paso;
        nota.value = '';

        return;
    }

    enviando.value = true;

    router.post(
        `/objetivos/${props.objetivo.id}/estado`,
        { estado: paso.valor, nota: nota.value },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
                destino.value = null;
            },
        },
    );
}

/* --- Cómo se evalúa --- */

const vinculando = ref(false);
const indicador = useForm({ indicador_id: '' });

function abrirVinculo(): void {
    indicador.reset();
    indicador.clearErrors();
    vinculando.value = true;
}

function vincularIndicador(): void {
    indicador.post(`/objetivos/${props.objetivo.id}/indicadores`, {
        preserveScroll: true,
        onSuccess: () => {
            vinculando.value = false;
            indicador.reset();
        },
    });
}

function desvincularIndicador(id: number): void {
    router.delete(`/objetivos/${props.objetivo.id}/indicadores/${id}`, { preserveScroll: true });
}

/*
 * Se ofrecen los que no están ya puestos: repetir uno no rompe nada —la
 * vinculación es idempotente— pero ofrecerlo hace pensar que falta.
 */
const sinVincular = computed(() => {
    const puestos = new Set(props.indicadores.map((item) => String(item.id)));

    return props.indicadoresDisponibles.filter((opcion) => !puestos.has(opcion.valor));
});

const puedeVincular = computed(() => props.puedeGestionar && sinVincular.value.length > 0);

/* --- Qué se hará --- */

const abierto = ref(false);

const actuacion = useForm({
    titulo: '',
    descripcion: '',
    prioridad: 'media',
    responsable_id: '',
    fecha_limite: '',
    coste_estimado: '',
});

function abrirActuacion(): void {
    actuacion.reset();
    actuacion.clearErrors();
    abierto.value = true;
}

function crearActuacion(): void {
    actuacion.post(`/objetivos/${props.objetivo.id}/actuaciones`, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            actuacion.reset();
        },
    });
}

function desvincularActuacion(id: number): void {
    router.delete(`/objetivos/${props.objetivo.id}/actuaciones/${id}`, { preserveScroll: true });
}

/*
 * La firma que acaba de ponerse traza su check, una vez. No al abrir una ficha
 * ya aprobada: ahí no ha pasado nada que contar.
 */
const recienFirmado = ref(false);

watch(
    () => props.objetivo.aprobadoEn,
    (nueva, anterior) => {
        recienFirmado.value = anterior === null && nueva !== null;
    },
);

const abiertas = computed(
    () => props.actuaciones.filter((item) => item.estado !== 'hecha' && item.estado !== 'descartada').length,
);
</script>

<template>
    <AppLayout :titulo="objetivo.codigo">
        <CabeceraPagina :titulo="objetivo.titulo" :codigo="objetivo.codigo" :descripcion="objetivo.descripcion">
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <CeldaBadge
                    anunciar
                    :valor="{
                        valor: objetivo.estado,
                        etiqueta: objetivo.estadoEtiqueta,
                        tono: objetivo.estadoTono,
                        icono: objetivo.estadoIcono,
                    }"
                />
                <span v-if="ultimoCambio" class="text-[13px] text-muted-foreground">
                    desde el {{ fechaLegible(ultimoCambio.fecha) }}
                    <template v-if="ultimoCambio.usuario">· {{ ultimoCambio.usuario }}</template>
                </span>
            </div>

            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/objetivos/${objetivo.id}/editar`">
                        <PencilLineIcon aria-hidden="true" />
                        Editar
                    </Link>
                </Button>
            </template>
        </CabeceraPagina>

        <!--
            La contradicción se señala y no se corrige: un objetivo dado por
            alcanzado con indicadores por debajo de su objetivo se pone delante,
            y la herramienta no toca el dato. Mismo papel que el residual sin
            respaldo de un riesgo.
        -->
        <Aviso v-if="avance.contradice">
            Este objetivo figura como alcanzado y
            {{ avance.medidos - avance.enObjetivo }} de sus
            {{ avance.medidos }} indicadores medidos no llegan a su objetivo.
        </Aviso>

        <section
            v-if="pendientes.length > 0"
            aria-labelledby="pendientes"
            class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border bg-card px-5 py-3.5"
        >
            <InfoIcon class="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
            <h2 id="pendientes" class="text-sm font-semibold">
                {{ pendientes.length }} {{ pendientes.length === 1 ? 'dato sin completar' : 'datos sin completar' }}
            </h2>
            <ul class="flex flex-1 flex-wrap gap-2">
                <li v-for="pendiente in pendientes" :key="pendiente.etiqueta">
                    <button
                        v-if="pendiente.indicador && puedeVincular"
                        type="button"
                        class="inline-flex h-7 items-center rounded-full bg-muted px-2.5 text-[13px] font-medium text-secondary-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                        @click="abrirVinculo"
                    >
                        {{ pendiente.etiqueta }}
                    </button>
                    <Link
                        v-else-if="!pendiente.indicador && puedeGestionar"
                        :href="`/objetivos/${objetivo.id}/editar`"
                        class="inline-flex h-7 items-center rounded-full bg-muted px-2.5 text-[13px] font-medium text-secondary-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                    >
                        {{ pendiente.etiqueta }}
                    </Link>
                    <span
                        v-else
                        class="inline-flex h-7 items-center rounded-full bg-muted px-2.5 text-[13px] font-medium text-secondary-foreground"
                    >
                        {{ pendiente.etiqueta }}
                    </span>
                </li>
            </ul>
        </section>

        <!-- El elemento fuerte: ¿va bien, y le da tiempo? -->
        <section aria-label="Cómo va" class="grid rounded-xl border bg-card lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
            <div class="flex flex-col gap-6 p-6 md:flex-row md:gap-10 lg:px-8 lg:py-7">
                <!-- Sin indicador, lo que falta es la 6.2 e) entera. -->
                <div v-if="indicadores.length === 0" class="flex max-w-xl flex-col gap-2.5">
                    <span class="text-[13px] font-medium text-muted-foreground">Cómo va</span>
                    <span class="text-2xl font-semibold tracking-[-0.02em]">Sin indicador</span>
                    <p class="text-sm text-pretty text-muted-foreground">
                        La cláusula 6.2 exige que el objetivo sea medible. Sin ninguna cifra detrás, se
                        cumple de palabra.
                    </p>
                    <Button v-if="puedeVincular" class="mt-2 self-start" @click="abrirVinculo">
                        <LinkIcon aria-hidden="true" />
                        Vincular indicador
                    </Button>
                </div>

                <template v-else-if="principal">
                    <div class="flex shrink-0 flex-col gap-2 md:w-60">
                        <span class="text-[13px] font-medium text-muted-foreground">
                            Cómo va · según <span class="cifra">{{ principal.codigo }}</span>
                        </span>
                        <template v-if="principal.ultimoValor">
                            <span class="cifra text-5xl leading-tight font-bold tracking-tight">
                                <Cifra v-if="cifraPrincipal !== null" :valor="cifraPrincipal" sufijo=" %" />
                                <template v-else>{{ principal.ultimoValor }}</template>
                            </span>
                            <span v-if="principal.fraccion" class="text-sm text-secondary-foreground tabular-nums">
                                {{ principal.fraccion }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ principal.ultimoPeriodo }}
                                <template v-if="principal.objetivo"> · objetivo {{ principal.objetivo }}</template>
                            </span>
                        </template>
                        <template v-else>
                            <span class="text-2xl font-semibold tracking-[-0.02em]">Sin medir</span>
                            <span class="text-sm text-muted-foreground">
                                Tiene indicador y ninguna medición: lo que falta es la 9.1.
                            </span>
                        </template>
                        <CeldaBadge
                            class="mt-1 self-start"
                            :valor="{
                                valor: principal.cumplimiento,
                                etiqueta: principal.cumplimientoEtiqueta,
                                tono: principal.cumplimientoTono,
                                icono: principal.cumplimientoIcono,
                            }"
                        />
                    </div>
                    <div v-if="principal.serie.length > 0" class="min-w-0 flex-1">
                        <GraficaSerie
                            :puntos="principal.serie"
                            :nombre="principal.nombre"
                            :techo="principal.porcentaje ? 100 : null"
                            :alto="176"
                        />
                    </div>
                </template>

                <!-- Con varios, el recuento de `Avance`: sobre los medidos, no sobre el total. -->
                <div v-else class="flex flex-col gap-2">
                    <span class="text-[13px] font-medium text-muted-foreground">Cómo va</span>
                    <p class="flex items-baseline gap-2">
                        <span class="cifra text-5xl leading-tight font-bold tracking-tight"><Cifra :valor="avance.enObjetivo" /></span>
                        <span class="text-sm text-secondary-foreground">
                            de {{ avance.medidos }} indicadores medidos en objetivo
                        </span>
                    </p>
                    <span v-if="avance.total > avance.medidos" class="text-xs text-muted-foreground">
                        {{ avance.total - avance.medidos }} de {{ avance.total }} sin medir todavía
                    </span>
                    <CeldaBadge
                        class="mt-1 self-start"
                        :valor="{ valor: 'avance', etiqueta: avance.etiqueta, tono: avance.tono, icono: null }"
                    />
                </div>
            </div>

            <div class="flex flex-col gap-2.5 border-t p-6 lg:border-t-0 lg:border-l lg:px-8 lg:py-7">
                <span class="text-[13px] font-medium text-muted-foreground">Para cuándo</span>

                <template v-if="plazo">
                    <p class="flex items-baseline gap-2">
                        <template v-if="plazo.corre && plazo.fuera > 0">
                            <span class="cifra text-5xl leading-tight font-bold tracking-tight text-destructive"><Cifra :valor="plazo.fuera" /></span>
                            <span class="text-base font-medium text-destructive">{{ plazo.fuera === 1 ? 'día' : 'días' }} fuera de plazo</span>
                        </template>
                        <template v-else-if="plazo.corre">
                            <span class="cifra text-5xl leading-tight font-bold tracking-tight"><Cifra :valor="plazo.dias - plazo.transcurridos" /></span>
                            <span class="text-base font-medium text-muted-foreground">
                                {{ plazo.dias - plazo.transcurridos === 1 ? 'día' : 'días' }}
                            </span>
                        </template>
                        <template v-else>
                            <span class="text-2xl font-semibold tracking-[-0.02em]">{{ plazo.finRotulo }}</span>
                            <span class="text-sm text-muted-foreground">el {{ fechaLegible(plazo.fin) }}</span>
                        </template>
                    </p>
                    <span class="text-sm text-secondary-foreground">
                        {{ plazo.corre && plazo.fuera === 0 ? 'hasta el' : 'fecha objetivo:' }}
                        {{ fechaLegible(plazo.limite) }}
                    </span>
                    <BarraPlazo class="mt-4" :plazo="plazo" inicio="Aprobado" />
                    <p class="mt-2 text-[13px] text-muted-foreground">{{ resumenPlazo }}</p>
                </template>

                <!-- Con fecha y sin firma: el plazo existe y todavía no corre. -->
                <template v-else-if="objetivo.fecha_objetivo">
                    <span class="text-2xl font-semibold tracking-[-0.02em]">{{ fechaLegible(objetivo.fecha_objetivo) }}</span>
                    <p class="text-sm text-pretty text-muted-foreground">
                        Empieza a correr cuando dirección lo apruebe. Un objetivo propuesto no compromete a nadie.
                    </p>
                </template>

                <template v-else>
                    <span class="text-2xl font-semibold tracking-[-0.02em]">Sin plazo</span>
                    <p class="text-sm text-pretty text-muted-foreground">
                        Un borrador se apunta como se pueda. Para aprobarlo hay que decir para cuándo.
                    </p>
                </template>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader class="flex flex-row items-start justify-between gap-4">
                        <div class="space-y-1.5">
                            <CardTitle class="flex items-center gap-2">
                                Cómo se evalúan los resultados
                                <span class="cifra rounded-full bg-muted px-1.5 text-xs font-normal text-muted-foreground">6.2 e</span>
                            </CardTitle>
                            <CardDescription>
                                Los indicadores que juzgan este objetivo. Se vinculan; el método y la
                                periodicidad viven en su ficha.
                            </CardDescription>
                        </div>
                        <Button v-if="puedeVincular && indicadores.length > 0" variant="outline" size="sm" @click="abrirVinculo">
                            <LinkIcon aria-hidden="true" />
                            Vincular indicador
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <EstadoVacio
                            v-if="indicadores.length === 0"
                            titulo="Sin indicador"
                            descripcion="Se vincula desde el bloque de arriba. Un indicador tiene periodicidad, responsable y método propios."
                        />

                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Indicador</TableHead>
                                    <TableHead>Tendencia</TableHead>
                                    <TableHead class="text-right">Último</TableHead>
                                    <TableHead>Cumplimiento</TableHead>
                                    <TableHead v-if="puedeGestionar" class="w-10"><span class="sr-only">Acciones</span></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in indicadores" :key="item.id">
                                    <TableCell class="whitespace-normal">
                                        <Link :href="`/indicadores/${item.id}`" class="flex flex-col underline-offset-4 hover:underline">
                                            <span class="cifra text-xs text-muted-foreground">{{ item.codigo }}</span>
                                            <span class="font-medium">{{ item.nombre }}</span>
                                        </Link>
                                        <span class="text-xs text-muted-foreground">{{ item.periodicidad }}</span>
                                    </TableCell>
                                    <TableCell>
                                        <TendenciaIndicador v-if="item.serie.length > 0" :serie="item.serie" />
                                        <span v-else class="text-xs text-muted-foreground">Sin medir</span>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <template v-if="item.ultimoValor">
                                            <span class="cifra block font-semibold">{{ item.ultimoValor }}</span>
                                            <span class="block text-xs text-muted-foreground">
                                                <template v-if="item.objetivo">de <span class="cifra">{{ item.objetivo }}</span> · </template>{{ item.ultimoPeriodo }}
                                            </span>
                                        </template>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </TableCell>
                                    <TableCell>
                                        <CeldaBadge
                                            :valor="{
                                                valor: item.cumplimiento,
                                                etiqueta: item.cumplimientoEtiqueta,
                                                tono: item.cumplimientoTono,
                                                icono: item.cumplimientoIcono,
                                            }"
                                        />
                                    </TableCell>
                                    <TableCell v-if="puedeGestionar" class="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon-sm" :aria-label="`Acciones de ${item.codigo}`">
                                                    <EllipsisIcon aria-hidden="true" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-48">
                                                <DropdownMenuItem @select="desvincularIndicador(item.id)">
                                                    Desvincular el indicador
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-start justify-between gap-4">
                        <div class="space-y-1.5">
                            <CardTitle class="flex items-center gap-2">
                                Qué se va a hacer
                                <span class="cifra rounded-full bg-muted px-1.5 text-xs font-normal text-muted-foreground">6.2 a</span>
                            </CardTitle>
                            <CardDescription v-if="actuaciones.length > 0">
                                <span class="cifra font-semibold text-foreground">{{ abiertas }}</span>
                                de <span class="cifra">{{ actuaciones.length }}</span> abiertas ·
                                <span class="cifra">{{ coste.total }}</span> estimados
                                <template v-if="coste.sinEstimar > 0">({{ coste.sinEstimar }} sin estimar)</template>
                                · fuera del presupuesto del plan de adecuación
                            </CardDescription>
                            <CardDescription v-else>
                                Son tareas del plan de acción, con responsable, plazo y coste.
                            </CardDescription>
                        </div>
                        <Button v-if="puedeGestionar" variant="outline" size="sm" @click="abrirActuacion">
                            <PlusIcon aria-hidden="true" />
                            Abrir actuación
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <EstadoVacio
                            v-if="actuaciones.length === 0"
                            titulo="Sin actuaciones"
                            descripcion="Nadie está empujando este objetivo todavía."
                        />

                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Actuación</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead>Plazo</TableHead>
                                    <TableHead class="text-right">Coste</TableHead>
                                    <TableHead v-if="puedeGestionar" class="w-10"><span class="sr-only">Acciones</span></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in actuaciones" :key="item.id">
                                    <TableCell class="whitespace-normal">
                                        <Link :href="`/tareas/${item.id}`" class="font-medium underline-offset-4 hover:underline">
                                            {{ item.titulo }}
                                        </Link>
                                        <span class="block text-xs text-muted-foreground">
                                            {{ item.responsable ?? 'Sin responsable' }} · prioridad {{ item.prioridad.toLowerCase() }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <CeldaBadge
                                            :valor="{
                                                valor: item.estado,
                                                etiqueta: item.estadoEtiqueta,
                                                tono: item.estadoTono,
                                                icono: item.estadoIcono,
                                            }"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <span v-if="item.fecha" class="cifra block text-sm">{{ fechaLegible(item.fecha) }}</span>
                                        <CeldaBadge
                                            :valor="{
                                                valor: 'plazo',
                                                etiqueta: item.plazoEtiqueta,
                                                tono: item.plazoTono,
                                                icono: null,
                                            }"
                                        />
                                    </TableCell>
                                    <TableCell class="cifra text-right">
                                        <template v-if="item.coste">{{ item.coste }}</template>
                                        <span v-else class="text-muted-foreground">Sin estimar</span>
                                    </TableCell>
                                    <TableCell v-if="puedeGestionar" class="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon-sm" :aria-label="`Acciones de «${item.titulo}»`">
                                                    <EllipsisIcon aria-hidden="true" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-52">
                                                <DropdownMenuItem @select="desvincularActuacion(item.id)">
                                                    Desvincular la actuación
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Histórico</CardTitle>
                        <CardDescription>
                            Desde cuándo está en cada estado, quién lo movió y por qué. De aquí sale el
                            «por qué no se alcanzó» de la revisión por la dirección.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <HistoricoTransiciones :transiciones="historial" />
                    </CardContent>
                </Card>
            </div>

            <div class="h-fit space-y-6">
                <Card v-if="disponibles.length > 0">
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-if="principales.length > 0" class="flex flex-col gap-2">
                            <template v-for="paso in principales" :key="paso.valor">
                                <BotonEstado
                                    class="h-9 w-full justify-start"
                                    :destino="{ ...paso, pista: paso.exigeMotivo ? 'pide motivo' : null }"
                                    :deshabilitado="enviando || bloqueado(paso)"
                                    :aria-describedby="bloqueado(paso) ? 'falta-plazo' : undefined"
                                    @click="mover(paso)"
                                />
                                <p v-if="bloqueado(paso)" id="falta-plazo" class="text-xs text-muted-foreground">
                                    Falta la fecha objetivo.
                                    <Link
                                        v-if="puedeGestionar"
                                        :href="`/objetivos/${objetivo.id}/editar`"
                                        class="text-primary underline-offset-4 hover:underline"
                                    >
                                        Ponerla
                                    </Link>
                                </p>
                            </template>
                        </div>

                        <div v-if="menores.length > 0" class="flex flex-wrap gap-1" :class="{ 'border-t pt-3': principales.length > 0 }">
                            <Button
                                v-for="paso in menores"
                                :key="paso.valor"
                                variant="ghost"
                                size="sm"
                                class="text-muted-foreground"
                                :disabled="enviando"
                                @click="mover(paso)"
                            >
                                <IconoTipo :nombre="paso.icono" clase="size-4" />
                                {{ paso.valor === 'propuesto' ? 'Volver a propuesto' : paso.etiqueta }}
                            </Button>
                        </div>

                        <div v-if="destino" class="space-y-2 border-t pt-3">
                            <CampoTextarea
                                nombre="nota"
                                :etiqueta="etiquetaNota"
                                :filas="4"
                                :valor-inicial="nota"
                                requerido
                                @input="nota = ($event.target as HTMLTextAreaElement).value"
                            />
                            <div class="flex gap-2">
                                <Button :disabled="enviando || nota.trim() === ''" @click="mover(destino)">
                                    {{ destino.etiqueta }}
                                </Button>
                                <Button variant="ghost" @click="destino = null">Cancelar</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="divide-y text-sm">
                            <div class="grid grid-cols-[3rem_minmax(0,1fr)] gap-3 pb-3">
                                <span class="cifra text-xs leading-5 text-muted-foreground">6.2 c</span>
                                <div>
                                    <dt class="text-[13px] text-muted-foreground">Quién responde</dt>
                                    <dd :class="{ 'text-muted-foreground': !objetivo.responsable }">
                                        {{ objetivo.responsable ?? 'Sin asignar' }}
                                    </dd>
                                </div>
                            </div>
                            <div class="grid grid-cols-[3rem_minmax(0,1fr)] gap-3 py-3">
                                <span class="cifra text-xs leading-5 text-muted-foreground">6.2 d</span>
                                <div>
                                    <dt class="text-[13px] text-muted-foreground">Para cuándo</dt>
                                    <dd :class="{ 'text-muted-foreground': !objetivo.fecha_objetivo }">
                                        {{ objetivo.fecha_objetivo ? fechaLegible(objetivo.fecha_objetivo) : 'Sin fijar' }}
                                    </dd>
                                </div>
                            </div>
                            <div class="grid grid-cols-[3rem_minmax(0,1fr)] gap-3 py-3">
                                <span class="cifra text-xs leading-5 text-muted-foreground">6.2 b</span>
                                <div>
                                    <dt class="text-[13px] text-muted-foreground">Con qué recursos</dt>
                                    <dd class="text-pretty" :class="{ 'text-muted-foreground': !objetivo.recursos }">
                                        {{ objetivo.recursos ?? 'Sin declarar' }}
                                    </dd>
                                </div>
                            </div>
                            <div v-if="objetivo.fechaCierre" class="grid grid-cols-[3rem_minmax(0,1fr)] gap-3 pt-3">
                                <span />
                                <div>
                                    <dt class="text-[13px] text-muted-foreground">Cerrado</dt>
                                    <dd>{{ objetivo.fechaCierre }}</dd>
                                </div>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <!--
                    La firma tiene tarjeta propia y no una línea más en la ficha:
                    es lo que separa un borrador de un compromiso, y su hueco
                    vacío tiene que verse.
                -->
                <Card>
                    <CardHeader>
                        <CardTitle>Aprobación</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <template v-if="objetivo.aprobadoEn">
                            <div class="flex items-start gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent text-primary">
                                    <ShieldCheckIcon class="size-4.5" :class="{ 'icono-dibuja': recienFirmado }" aria-hidden="true" />
                                </span>
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ objetivo.aprobadoPor ?? 'Firmante desconocido' }}</span>
                                    <span class="cifra text-xs text-muted-foreground">{{ objetivo.aprobadoEn }}</span>
                                </div>
                            </div>
                            <p v-if="objetivo.notaAprobacion" class="border-l-2 pl-3 text-secondary-foreground">
                                {{ objetivo.notaAprobacion }}
                            </p>
                        </template>
                        <p v-else class="rounded-lg border border-dashed p-4 text-[13px] text-muted-foreground">
                            Sin firmar. Comprometerse a una cifra y a un plazo es de dirección.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="vinculando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Vincular indicador</DialogTitle>
                    <DialogDescription>
                        Se vincula, no se crea: un indicador tiene periodicidad, responsable y método propios,
                        y puede evaluar varios objetivos a la vez.
                    </DialogDescription>
                </DialogHeader>

                <CampoSelect
                    nombre="indicador_id"
                    etiqueta="Indicador"
                    :opciones="sinVincular"
                    :error="indicador.errors.indicador_id"
                    requerido
                    @update:model-value="(valor?: string) => (indicador.indicador_id = valor ?? '')"
                />

                <DialogFooter>
                    <Button variant="outline" @click="vinculando = false">Cancelar</Button>
                    <Button :disabled="indicador.processing || indicador.indicador_id === ''" @click="vincularIndicador">
                        Vincular
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Abrir actuación</DialogTitle>
                    <DialogDescription>
                        Nace como tarea del plan de acción, con el origen ya puesto. Es el
                        «qué se hará» que pide la cláusula 6.2.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoTexto
                        nombre="titulo"
                        etiqueta="Título"
                        :error="actuacion.errors.titulo"
                        requerido
                        @input="actuacion.titulo = ($event.target as HTMLInputElement).value"
                    />
                    <CampoSelect
                        nombre="prioridad"
                        etiqueta="Prioridad"
                        :opciones="prioridades"
                        :valor-inicial="actuacion.prioridad"
                        :error="actuacion.errors.prioridad"
                        requerido
                        @update:model-value="(valor?: string) => (actuacion.prioridad = valor ?? 'media')"
                    />
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :error="actuacion.errors.responsable_id"
                        @update:model-value="(valor?: string) => (actuacion.responsable_id = valor ?? '')"
                    />
                    <CampoTexto
                        nombre="fecha_limite"
                        etiqueta="Fecha límite"
                        tipo="date"
                        :error="actuacion.errors.fecha_limite"
                        @input="actuacion.fecha_limite = ($event.target as HTMLInputElement).value"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abierto = false">Cancelar</Button>
                    <Button :disabled="actuacion.processing" @click="crearActuacion">Abrir</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
