<script setup lang="ts">
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import Aviso from '@/components/Aviso.vue';
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoOpciones from '@/components/formulario/CampoOpciones.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import MatrizRiesgo from '@/components/riesgo/MatrizRiesgo.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFecha } from '@/lib/celdas';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ArrowRightIcon, CheckIcon, CircleIcon, InfoIcon, ShieldPlusIcon, TriangleAlertIcon, XIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, ref } from 'vue';

/**
 * La ficha de un riesgo.
 *
 * **Las dos cifras se enseñan juntas y ninguna sustituye a la otra**: el intrínseco
 * es lo que vale el riesgo sin hacer nada y el residual es lo que queda después de
 * tratarlo. Y al lado, sin sobrescribir nada, lo que la herramienta deduce — el
 * impacto que sugieren los activos y el respaldo real de las salvaguardas. Es el
 * mismo trato que la ficha de un activo da a la valoración propia y a la efectiva:
 * el auditor pregunta qué valoró la organización, no qué dedujo la herramienta.
 *
 * El diálogo de valorar vive aquí y no en una pantalla aparte, a diferencia de la
 * valoración de un sistema: lo que hace falta para decidir bien —la sugerencia de
 * impacto, las salvaguardas y en qué estado están— está en esta misma pantalla, y
 * sacarlo a otra obligaría a recordarlo.
 */

interface Nivel {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
}

interface Escalon {
    valor: number;
    etiqueta: string;
    descripcion: string | null;
}

interface Banda {
    desde: number;
    hasta: number;
    etiqueta: string;
    tono: string;
    icono: string;
}

interface ActivoVinculado {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
}

interface Salvaguarda {
    id: number;
    requisito: string | null;
    titulo: string | null;
    estado: string;
    estado_etiqueta: string;
    estado_tono: string;
    estado_icono: string;
    madurez: string | null;
    nota: string | null;
}

interface Valoracion {
    id: number;
    vigente: boolean;
    probabilidad: number;
    impacto: number;
    impacto_por_dimension: Record<string, number>;
    intrinseco: number;
    intrinseco_nivel: Nivel;
    probabilidad_residual: number | null;
    impacto_residual: number | null;
    residual: number | null;
    residual_nivel: Nivel | null;
    justificacion_residual: string | null;
    decision: string;
    decision_etiqueta: string;
    decision_tono: string;
    decision_icono: string;
    valorada_en: string;
    valorada_por: string | null;
    aceptada_en: string | null;
    aceptada_por: string | null;
    nota: string | null;
    nota_aceptacion: string | null;
    editable: boolean;
}

interface Decision {
    valor: string;
    etiqueta: string;
    descripcion: string;
    tono: string;
    icono: string;
}

const props = defineProps<{
    riesgo: {
        id: number;
        codigo: string;
        titulo: string;
        amenaza: string;
        vulnerabilidad: string | null;
        propietario: string | null;
        fecha_revision: string | null;
        revision_vencida: boolean;
        notas: string | null;
        activos: ActivoVinculado[];
        salvaguardas: Salvaguarda[];
    };
    valoracion: Valoracion | null;
    historico: Valoracion[];
    sugerencia: { impacto: number | null; motivos: { activo: string; dimensiones: string[] }[] };
    cobertura: {
        total: number;
        implantadas: number;
        enProgreso: number;
        sinEmpezar: number;
        madurezMedia: number | null;
        madurezEvaluadas: number;
    };
    sinRespaldo: boolean;
    metodologia: {
        nombre: string;
        esDeFabrica: boolean;
        estaAprobada: boolean;
        probabilidad: Escalon[];
        impacto: Escalon[];
        umbralAceptacion: number;
        umbralCritico: number;
        bandas: Record<string, Banda>;
    };
    decisiones: Decision[];
    candidatos: (Opcion & { marco: string | null; estado: string })[];
}>();

const { variantesEntrada } = useMovimientoReducido();

const fecha = (valor: string | null): string => (valor ? formatoFecha.format(new Date(valor)) : '—');

/* ---------------------------------------------------------------- Valorar -- */

const valorando = ref(false);

const escalones = (escala: Escalon[]): Opcion[] =>
    escala.map((escalon) => ({ valor: String(escalon.valor), etiqueta: `${escalon.valor} · ${escalon.etiqueta}` }));

const probabilidades = computed(() => escalones(props.metodologia.probabilidad));
const impactos = computed(() => escalones(props.metodologia.impacto));

/*
 * «Todavía no lo he decidido» tiene que poder elegirse: un riesgo recién medido no
 * tiene residual hasta que alguien decide qué se va a hacer con él. El centinela lo
 * traduce a nulo el `FormRequest`, que es donde ya se traduce el de los demás
 * desplegables opcionales.
 */
const probabilidadesResiduales = computed(() => conOpcionVacia(probabilidades.value, 'Sin declarar'));
const impactosResiduales = computed(() => conOpcionVacia(impactos.value, 'Sin declarar'));

const valorar = useForm({
    probabilidad: String(props.valoracion?.probabilidad ?? ''),
    impacto: String(props.valoracion?.impacto ?? props.sugerencia.impacto ?? ''),
    probabilidad_residual: props.valoracion?.probabilidad_residual
        ? String(props.valoracion.probabilidad_residual)
        : SIN_VALOR,
    impacto_residual: props.valoracion?.impacto_residual ? String(props.valoracion.impacto_residual) : SIN_VALOR,
    justificacion_residual: props.valoracion?.justificacion_residual ?? '',
    decision: props.valoracion?.decision ?? 'mitigar',
    nota: '',
});

const opcionesDecision = computed<Opcion[]>(() =>
    props.decisiones.map((decision) => ({ valor: decision.valor, etiqueta: decision.etiqueta })),
);

const descripcionDecision = computed(
    () => props.decisiones.find((decision) => decision.valor === valorar.decision)?.descripcion,
);

function enviarValoracion(): void {
    valorar.post(`/riesgos/${props.riesgo.id}/valoracion`, {
        preserveScroll: true,
        onSuccess: () => {
            valorando.value = false;
            valorar.nota = '';
        },
    });
}

/** El producto que saldría con lo que hay elegido ahora mismo, para no esperar a guardar. */
const previsualizacion = computed(() => {
    const p = Number(valorar.probabilidad);
    const i = Number(valorar.impacto);

    return p > 0 && i > 0 ? p * i : null;
});

const bandaPrevista = computed(() => {
    const riesgo = previsualizacion.value;

    if (riesgo === null) {
        return null;
    }

    return (
        Object.values(props.metodologia.bandas).find(
            (banda) => riesgo >= banda.desde && riesgo <= banda.hasta,
        ) ?? null
    );
});

/* ----------------------------------------------------------- Salvaguardas -- */

const vinculando = ref(false);

const vincular = useForm({ implantacion_id: undefined as string | undefined, nota: '' });

function enviarVinculo(): void {
    vincular.post(`/riesgos/${props.riesgo.id}/salvaguardas`, {
        preserveScroll: true,
        onSuccess: () => {
            vincular.reset();
            vinculando.value = false;
        },
    });
}

function desvincular(implantacionId: number): void {
    router.delete(`/riesgos/${props.riesgo.id}/salvaguardas/${implantacionId}`, { preserveScroll: true });
}

/* --------------------------------------------------------------- Aceptar -- */

const aceptando = ref(false);

const aceptar = useForm({ nota: '' });

function enviarAceptacion(): void {
    aceptar.post(`/riesgos/${props.riesgo.id}/aceptacion`, {
        preserveScroll: true,
        onSuccess: () => {
            aceptar.reset();
            aceptando.value = false;
        },
    });
}

/* --------------------------------------------------------------- Derivados -- */

/**
 * Las dos celdas que se señalan en la matriz. El residual sólo si se ha declarado:
 * marcar el intrínseco dos veces diría que no se ha tratado nada, que es distinto de
 * no haberlo decidido todavía.
 */
const marcadas = computed(() => {
    if (props.valoracion === null) {
        return [];
    }

    const celdas = [
        {
            probabilidad: props.valoracion.probabilidad,
            impacto: props.valoracion.impacto,
            etiqueta: 'Intrínseco',
        },
    ];

    if (props.valoracion.probabilidad_residual !== null && props.valoracion.impacto_residual !== null) {
        celdas.push({
            probabilidad: props.valoracion.probabilidad_residual,
            impacto: props.valoracion.impacto_residual,
            etiqueta: 'Residual',
        });
    }

    return celdas;
});

const tramosCobertura = computed<Segmento[]>(() => [
    { clave: 'implantado', etiqueta: 'Implantadas', valor: props.cobertura.implantadas },
    { clave: 'en_progreso', etiqueta: 'En progreso', valor: props.cobertura.enProgreso },
    { clave: 'no_iniciado', etiqueta: 'Sin empezar', valor: props.cobertura.sinEmpezar },
]);

/** Cuánto baja el riesgo entre el intrínseco y el residual declarado. */
const reduccion = computed(() =>
    props.valoracion?.residual != null ? props.valoracion.intrinseco - props.valoracion.residual : null,
);

const decisionVigente = computed(() =>
    props.decisiones.find((decision) => decision.valor === props.valoracion?.decision),
);

/**
 * Los días que faltan para la reevaluación, en positivo, o los que lleva vencida.
 * Se cuenta por días de calendario: a las once de la noche «mañana» sigue siendo 1.
 */
const diasHastaRevision = computed(() => {
    if (props.riesgo.fecha_revision === null) {
        return null;
    }

    const hoy = new Date();
    const inicioDeHoy = Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());
    // Llega como `2026-12-23`, sin hora: se parte a mano para que la zona no mueva el día.
    const [anio, mes, dia] = props.riesgo.fecha_revision.split('-').map(Number);
    const inicioDeRevision = Date.UTC(anio, mes - 1, dia);

    return Math.round((inicioDeRevision - inicioDeHoy) / 86_400_000);
});

const plazoRevision = computed(() => {
    const dias = diasHastaRevision.value;

    if (dias === null) {
        return null;
    }

    if (dias === 0) {
        return 'hoy';
    }

    const unidad = Math.abs(dias) === 1 ? 'día' : 'días';

    return dias > 0 ? `en ${dias} ${unidad}` : `vencida hace ${-dias} ${unidad}`;
});

type EstadoPaso = 'hecho' | 'falla' | 'pendiente';

/**
 * Lo que falta hasta la firma, en el orden en que se hace. El respaldo cuenta
 * como hecho cuando el residual no baja: nada que sostener, nada que falte.
 */
const pasos = computed<{ etiqueta: string; estado: EstadoPaso }[]>(() => {
    const valoracion = props.valoracion;
    const declarado = valoracion?.residual != null;

    let respaldo: EstadoPaso = 'pendiente';

    if (props.sinRespaldo) {
        respaldo = 'falla';
    } else if (props.cobertura.implantadas > 0 || (declarado && (reduccion.value ?? 0) <= 0)) {
        respaldo = 'hecho';
    }

    return [
        { etiqueta: 'Valorado', estado: valoracion ? 'hecho' : 'pendiente' },
        { etiqueta: 'Residual declarado', estado: declarado ? 'hecho' : 'pendiente' },
        { etiqueta: 'Respaldado por controles', estado: respaldo },
        { etiqueta: 'Aceptado por el propietario', estado: valoracion?.aceptada_en ? 'hecho' : 'pendiente' },
    ];
});

const estilosPaso: Record<EstadoPaso, { clase: string; icono: typeof CheckIcon }> = {
    hecho: { clase: 'bg-estado-implantado-suave text-estado-implantado', icono: CheckIcon },
    falla: { clase: 'bg-destructive/10 text-destructive', icono: XIcon },
    pendiente: { clase: 'bg-muted text-muted-foreground', icono: CircleIcon },
};

/** El techo de la escala de impacto, para que las dos barras se lean contra lo mismo. */
const impactoMaximo = computed(() => Math.max(...props.metodologia.impacto.map((escalon) => escalon.valor), 1));

const anchoImpacto = (valor: number): string => `${Math.min(100, (valor / impactoMaximo.value) * 100)}%`;

const dimensiones: Record<string, string> = {
    C: 'Confidencialidad',
    I: 'Integridad',
    D: 'Disponibilidad',
    A: 'Autenticidad',
    T: 'Trazabilidad',
};
</script>

<template>
    <AppLayout :titulo="`${riesgo.codigo} · ${riesgo.titulo}`">
        <CabeceraPagina :titulo="riesgo.titulo" :codigo="riesgo.codigo" :descripcion="`Amenaza: ${riesgo.amenaza}`">
            <template #acciones>
                <Button as-child variant="outline">
                    <Link :href="`/riesgos/${riesgo.id}/editar`">Editar</Link>
                </Button>
                <Button variant="outline" @click="valorando = true">
                    {{ valoracion ? 'Valorar de nuevo' : 'Valorar' }}
                </Button>
            </template>
        </CabeceraPagina>

        <motion.div :variants="variantesEntrada" initial="oculto" animate="visible" class="space-y-6">
            <!--
                El resumen: de dónde a dónde baja, qué se hace con él y quién
                responde. Sustituye a la fila de badges, que decía lo mismo sin
                orden de lectura.
            -->
            <Card class="gap-0 py-0">
                <div
                    v-if="valoracion"
                    class="grid md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] xl:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]"
                >
                    <div class="flex flex-col gap-1.5 p-6 pb-5">
                        <span class="text-[13px] font-medium text-muted-foreground">Intrínseco</span>
                        <div class="flex items-baseline gap-2.5">
                            <span class="cifra text-4xl font-semibold">{{ valoracion.intrinseco }}</span>
                            <CeldaBadge
                                :valor="{
                                    valor: valoracion.intrinseco,
                                    etiqueta: valoracion.intrinseco_nivel.etiqueta,
                                    tono: valoracion.intrinseco_nivel.tono,
                                    icono: valoracion.intrinseco_nivel.icono,
                                }"
                            />
                        </div>
                        <span class="cifra text-xs text-muted-foreground">
                            P {{ valoracion.probabilidad }} × I {{ valoracion.impacto }}
                        </span>
                    </div>

                    <div
                        class="hidden flex-col items-center justify-center gap-1 px-2 text-muted-foreground md:flex"
                        aria-hidden="true"
                    >
                        <ArrowRightIcon class="size-5" />
                        <span v-if="reduccion !== null && reduccion !== 0" class="cifra text-xs">
                            {{ reduccion > 0 ? '−' : '+' }}{{ Math.abs(reduccion) }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-1.5 border-t p-6 pb-5 md:border-t-0">
                        <span class="text-[13px] font-medium text-muted-foreground">Residual declarado</span>
                        <template v-if="valoracion.residual !== null && valoracion.residual_nivel">
                            <div class="flex items-baseline gap-2.5">
                                <span class="cifra text-4xl font-semibold">{{ valoracion.residual }}</span>
                                <CeldaBadge
                                    :valor="{
                                        valor: valoracion.residual,
                                        etiqueta: valoracion.residual_nivel.etiqueta,
                                        tono: valoracion.residual_nivel.tono,
                                        icono: valoracion.residual_nivel.icono,
                                    }"
                                />
                            </div>
                            <span class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                <span class="cifra">
                                    P {{ valoracion.probabilidad_residual }} × I {{ valoracion.impacto_residual }}
                                </span>
                                <span v-if="sinRespaldo" class="inline-flex items-center gap-1 text-destructive">
                                    <XIcon class="size-3" aria-hidden="true" />
                                    sin respaldo
                                </span>
                            </span>
                        </template>
                        <p v-else class="mt-2 text-sm text-muted-foreground">Sin declarar todavía</p>
                    </div>

                    <div class="flex flex-col gap-1.5 border-t p-6 md:col-span-3 xl:col-span-1 xl:border-t-0 xl:border-l">
                        <span class="text-[13px] font-medium text-muted-foreground">Tratamiento</span>
                        <span class="flex items-center gap-2 font-semibold">
                            <IconoTipo :nombre="valoracion.decision_icono" class="size-4 text-muted-foreground" />
                            {{ valoracion.decision_etiqueta }}
                        </span>
                        <span v-if="decisionVigente" class="text-[13px] text-pretty text-muted-foreground">
                            {{ decisionVigente.descripcion }}
                        </span>
                    </div>

                    <dl class="grid gap-2.5 border-t p-6 md:col-span-3 xl:col-span-1 xl:border-t-0 xl:border-l">
                        <div>
                            <dt class="text-[13px] font-medium text-muted-foreground">Propietario</dt>
                            <dd class="text-sm font-medium">{{ riesgo.propietario ?? 'Sin asignar' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[13px] font-medium text-muted-foreground">Próxima reevaluación</dt>
                            <dd class="text-sm">
                                <span class="cifra">{{ fecha(riesgo.fecha_revision) }}</span>
                                <span
                                    v-if="plazoRevision"
                                    :class="riesgo.revision_vencida ? 'text-destructive' : 'text-muted-foreground'"
                                >
                                    · {{ plazoRevision }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <EstadoVacio
                    v-else
                    :icono="TriangleAlertIcon"
                    titulo="Todavía sin valorar"
                    descripcion="Un riesgo registrado y sin medir no cuenta en ninguna cifra de exposición: no está por encima ni por debajo de ningún umbral, sencillamente no se sabe."
                />

                <!-- Lo que falta hasta la firma, en el orden en que se hace. -->
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t px-6 py-3.5 text-[13px]">
                    <span class="mr-1 font-medium text-muted-foreground">Hasta la firma</span>
                    <ol class="contents">
                        <li v-for="(paso, indice) in pasos" :key="paso.etiqueta" class="flex items-center gap-3">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium"
                                :class="estilosPaso[paso.estado].clase"
                            >
                                <component :is="estilosPaso[paso.estado].icono" class="size-3.5" aria-hidden="true" />
                                {{ paso.etiqueta }}
                                <span class="sr-only">
                                    · {{ paso.estado === 'hecho' ? 'hecho' : paso.estado === 'falla' ? 'no se cumple' : 'pendiente' }}
                                </span>
                            </span>
                            <span v-if="indice < pasos.length - 1" class="h-px w-6 bg-border" aria-hidden="true" />
                        </li>
                    </ol>
                </div>
            </Card>

            <!--
                El hallazgo que este módulo existe para enseñar: se declara que el
                riesgo baja y no hay ni una salvaguarda implantada que lo sostenga.
                Va en bloque y no como toast porque tiene que seguir ahí mientras se
                mira la ficha, y con su salida al lado.
            -->
            <Aviso v-if="sinRespaldo" tono="error" titulo="El residual no tiene nada que lo respalde">
                Se declara que baja de <span class="cifra">{{ valoracion?.intrinseco }}</span> a
                <span class="cifra">{{ valoracion?.residual }}</span> y ninguna salvaguarda vinculada está
                implantada. Es lo primero que pide un auditor.

                <template #accion>
                    <Button variant="outline" size="sm" @click="vinculando = true">Vincular control</Button>
                </template>
            </Aviso>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div class="space-y-6">
                    <Card v-if="valoracion">
                        <CardHeader>
                            <CardTitle>Valoración</CardTitle>
                            <CardDescription>
                                Vigente desde el <span class="cifra">{{ fecha(valoracion.valorada_en) }}</span>
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="space-y-5">
                            <div class="grid gap-8 sm:grid-cols-[minmax(0,1fr)_auto]">
                                <div>
                                    <p class="text-xs text-muted-foreground">Impacto por dimensión</p>
                                    <dl class="mt-2 grid gap-1.5 text-sm">
                                        <div
                                            v-for="(valor, codigo) in valoracion.impacto_por_dimension"
                                            :key="codigo"
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <dt class="text-muted-foreground">
                                                {{ dimensiones[codigo] ?? codigo }}
                                            </dt>
                                            <dd class="cifra">{{ valor }}</dd>
                                        </div>
                                    </dl>
                                    <p
                                        v-if="Object.keys(valoracion.impacto_por_dimension).length === 0"
                                        class="mt-2 text-sm text-muted-foreground"
                                    >
                                        Sin desglose: el riesgo no tenía activos al valorarlo.
                                    </p>
                                </div>

                                <div class="w-full sm:w-72">
                                    <MatrizRiesgo
                                        :bandas="metodologia.bandas"
                                        :probabilidad="metodologia.probabilidad"
                                        :impacto="metodologia.impacto"
                                        :marcadas="marcadas"
                                    />
                                </div>
                            </div>

                            <!-- La justificación es lo que el auditor lee al lado de la firma. -->
                            <blockquote
                                v-if="valoracion.justificacion_residual"
                                class="space-y-1.5 rounded-lg border bg-superficie px-4 py-3.5"
                            >
                                <p class="text-xs font-medium text-muted-foreground">Por qué baja</p>
                                <p class="text-pretty">{{ valoracion.justificacion_residual }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <template v-if="valoracion.valorada_por">{{ valoracion.valorada_por }} · </template>
                                    <span class="cifra">{{ fecha(valoracion.valorada_en) }}</span>
                                </p>
                            </blockquote>

                            <p v-if="valoracion.nota" class="text-sm text-muted-foreground">{{ valoracion.nota }}</p>

                            <!--
                                La metodología sin firmar baja aquí, a una línea: es un
                                matiz de cómo se mide, no un hallazgo sobre este riesgo.
                            -->
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 border-t pt-4 text-xs text-muted-foreground">
                                <InfoIcon class="size-3.5 shrink-0" aria-hidden="true" />
                                <span v-if="metodologia.esDeFabrica || !metodologia.estaAprobada">
                                    <template v-if="metodologia.esDeFabrica">Escala de partida de Statera</template>
                                    <template v-else>«{{ metodologia.nombre }}»</template>, sin aprobar: ISO 27001 pide
                                    que los criterios los fije la organización.
                                </span>
                                <span v-else>Medido con «{{ metodologia.nombre }}».</span>
                                <Link
                                    v-if="metodologia.esDeFabrica || !metodologia.estaAprobada"
                                    href="/riesgos/metodologia"
                                    class="font-medium text-primary underline-offset-4 hover:underline sm:ml-auto"
                                >
                                    Definirla y aprobarla
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Salvaguardas</CardTitle>
                            <CardDescription>Controles implantados en un sistema, no requisitos del catálogo.</CardDescription>
                            <CardAction class="flex items-center gap-3">
                                <span v-if="cobertura.total > 0" class="cifra text-[13px] text-muted-foreground">
                                    {{ cobertura.implantadas }} de {{ cobertura.total }} implantadas
                                </span>
                                <Button
                                    v-if="riesgo.salvaguardas.length > 0"
                                    variant="outline"
                                    size="sm"
                                    @click="vinculando = true"
                                >
                                    Vincular control
                                </Button>
                            </CardAction>
                        </CardHeader>

                        <CardContent>
                            <div v-if="riesgo.salvaguardas.length === 0" class="rounded-xl border border-dashed">
                                <EstadoVacio
                                    :icono="ShieldPlusIcon"
                                    titulo="Ningún control apoyado contra este riesgo"
                                    descripcion="Sin una salvaguarda vinculada, el residual es una declaración de intenciones, no un tratamiento."
                                />
                                <div class="-mt-2 flex justify-center pb-6">
                                    <Button @click="vinculando = true">Vincular control</Button>
                                </div>
                            </div>

                            <div v-else class="space-y-4">
                                <!-- La cobertura se calcula, no se almacena: va al lado de lo declarado. -->
                                <div class="space-y-2">
                                    <BarraSegmentada :segmentos="tramosCobertura" leyenda />
                                    <p v-if="cobertura.madurezMedia !== null" class="text-xs text-muted-foreground">
                                        Madurez media <span class="cifra">{{ cobertura.madurezMedia }}</span> sobre
                                        <span class="cifra">{{ cobertura.madurezEvaluadas }}</span> evaluadas de
                                        <span class="cifra">{{ cobertura.total }}</span>
                                    </p>
                                    <p v-else class="text-xs text-muted-foreground">
                                        Ninguna salvaguarda tiene la madurez evaluada.
                                    </p>
                                </div>

                                <ul class="divide-y border-t">
                                    <li
                                        v-for="salvaguarda in riesgo.salvaguardas"
                                        :key="salvaguarda.id"
                                        class="flex items-start justify-between gap-3 py-3 last:pb-0"
                                    >
                                        <div class="min-w-0">
                                            <p class="flex flex-wrap items-center gap-2 text-sm">
                                                <span class="cifra">{{ salvaguarda.requisito ?? '—' }}</span>
                                                <CeldaBadge
                                                    :valor="{
                                                        valor: salvaguarda.estado,
                                                        etiqueta: salvaguarda.estado_etiqueta,
                                                        tono: salvaguarda.estado_tono,
                                                        icono: salvaguarda.estado_icono,
                                                    }"
                                                />
                                                <span v-if="salvaguarda.madurez" class="text-xs text-muted-foreground">
                                                    {{ salvaguarda.madurez }}
                                                </span>
                                            </p>
                                            <p class="mt-0.5 truncate text-sm text-muted-foreground">
                                                {{ salvaguarda.titulo ?? '' }}
                                            </p>
                                            <p v-if="salvaguarda.nota" class="mt-1 text-sm">{{ salvaguarda.nota }}</p>
                                        </div>

                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="shrink-0"
                                            @click="desvincular(salvaguarda.id)"
                                        >
                                            Quitar
                                        </Button>
                                    </li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="historico.length > 0">
                        <CardHeader>
                            <CardTitle>Historial</CardTitle>
                            <CardDescription>
                                Cada valoración se conserva con la escala que tenía en su momento.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ol class="relative space-y-5">
                                <!-- El hilo de la línea de tiempo, detrás de los puntos. -->
                                <span
                                    v-if="historico.length > 1"
                                    class="absolute top-2 bottom-2 left-[calc(7rem+19px)] w-px bg-border max-sm:hidden"
                                    aria-hidden="true"
                                />
                                <li
                                    v-for="paso in historico"
                                    :key="paso.id"
                                    class="relative grid gap-x-3 gap-y-1 text-sm sm:grid-cols-[7rem_16px_minmax(0,1fr)]"
                                >
                                    <span class="cifra pt-0.5 text-xs text-muted-foreground">
                                        {{ fecha(paso.valorada_en) }}
                                    </span>
                                    <span class="flex justify-center pt-1.5 max-sm:hidden" aria-hidden="true">
                                        <span
                                            class="size-2.5 rounded-full ring-4 ring-card"
                                            :class="paso.vigente ? 'bg-primary' : 'bg-muted-foreground/50'"
                                        />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-medium">
                                            {{ paso.vigente ? 'Valoración vigente' : 'Valoración anterior' }}
                                            <span class="cifra font-normal text-muted-foreground">
                                                · {{ paso.intrinseco }}<template v-if="paso.residual !== null">
                                                    → {{ paso.residual }}</template
                                                >
                                            </span>
                                        </p>
                                        <p class="text-[13px] text-muted-foreground">
                                            {{ paso.decision_etiqueta }}
                                            <template v-if="paso.valorada_por"> · {{ paso.valorada_por }}</template>
                                            <template v-if="paso.aceptada_en">
                                                · aceptada el {{ fecha(paso.aceptada_en) }}
                                            </template>
                                        </p>
                                        <p v-if="!paso.vigente && paso.justificacion_residual" class="mt-0.5">
                                            {{ paso.justificacion_residual }}
                                        </p>
                                        <p v-if="!paso.vigente && paso.nota" class="mt-0.5 text-muted-foreground">
                                            {{ paso.nota }}
                                        </p>
                                    </div>
                                </li>
                            </ol>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card class="h-fit">
                        <CardHeader>
                            <CardTitle>Sobre qué pesa</CardTitle>
                            <CardDescription>
                                El impacto se deduce de lo que valen estos activos, incluida la valoración que
                                heredan por el grafo de dependencias.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <ul class="space-y-2">
                                <li v-for="activo in riesgo.activos" :key="activo.id">
                                    <Link
                                        :href="`/activos/${activo.id}`"
                                        class="flex min-w-0 flex-col rounded-lg border bg-superficie px-3 py-2.5 transition-colors hover:bg-accent"
                                    >
                                        <span class="truncate text-sm font-medium">{{ activo.nombre }}</span>
                                        <span class="cifra text-xs text-muted-foreground">{{ activo.codigo }}</span>
                                    </Link>
                                </li>
                            </ul>
                            <p v-if="riesgo.activos.length === 0" class="text-sm text-muted-foreground">
                                Ningún activo vinculado.
                            </p>

                            <!--
                                Lo sugerido y lo declarado, uno encima del otro y contra la
                                misma escala. Lo derivado se enseña al lado y no sobrescribe.
                            -->
                            <div v-if="sugerencia.impacto !== null" class="space-y-2.5">
                                <p class="text-xs text-muted-foreground">Impacto</p>
                                <dl class="grid grid-cols-[5rem_minmax(0,1fr)_1.5rem] items-center gap-x-2.5 gap-y-2 text-[13px]">
                                    <dt class="text-muted-foreground">Sugerido</dt>
                                    <dd class="h-2 rounded-full bg-muted" aria-hidden="true">
                                        <span
                                            class="block h-full rounded-full bg-muted-foreground"
                                            :style="{ width: anchoImpacto(sugerencia.impacto) }"
                                        />
                                    </dd>
                                    <dd class="cifra text-right">{{ sugerencia.impacto }}</dd>

                                    <template v-if="valoracion">
                                        <dt class="text-muted-foreground">Declarado</dt>
                                        <dd class="h-2 rounded-full bg-muted" aria-hidden="true">
                                            <span
                                                class="block h-full rounded-full bg-primary"
                                                :style="{ width: anchoImpacto(valoracion.impacto) }"
                                            />
                                        </dd>
                                        <dd class="cifra text-right">{{ valoracion.impacto }}</dd>
                                    </template>
                                </dl>

                                <!--
                                    Sin los motivos, un 5 sobre treinta activos parece un
                                    error de la herramienta. Esto dice quién pone el techo.
                                -->
                                <ul v-if="sugerencia.motivos.length > 0" class="space-y-0.5 text-xs text-muted-foreground">
                                    <li v-for="motivo in sugerencia.motivos" :key="motivo.activo">
                                        Lo pone {{ motivo.activo }}:
                                        <span class="text-foreground">{{ motivo.dimensiones.join(', ').toLowerCase() }}</span>
                                    </li>
                                </ul>

                                <p
                                    v-if="valoracion && valoracion.impacto < sugerencia.impacto"
                                    class="text-xs text-pretty text-muted-foreground"
                                >
                                    Se declaró por debajo de lo que sugieren los activos: conviene que la justificación lo
                                    explique.
                                </p>
                                <p v-else-if="!valoracion" class="text-xs text-muted-foreground">
                                    Es una propuesta: el impacto lo decide quien valora.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="h-fit">
                        <CardHeader>
                            <CardTitle>Aceptación</CardTitle>
                            <CardDescription>
                                ISO 27001 exige que el propietario del riesgo apruebe lo que queda después de
                                tratarlo. Firmar vuelve la valoración inmutable.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <template v-if="valoracion?.aceptada_en">
                                <p class="flex items-start gap-2 text-sm">
                                    <CheckIcon class="mt-0.5 size-4 shrink-0 text-estado-implantado" aria-hidden="true" />
                                    <span>
                                        Aceptado por <strong>{{ valoracion.aceptada_por ?? '—' }}</strong> el
                                        <span class="cifra">{{ fecha(valoracion.aceptada_en) }}</span>
                                    </span>
                                </p>
                                <p v-if="valoracion.nota_aceptacion" class="text-sm text-muted-foreground">
                                    {{ valoracion.nota_aceptacion }}
                                </p>
                            </template>

                            <template v-else>
                                <p v-if="!valoracion" class="text-sm text-muted-foreground">
                                    No se puede aceptar un riesgo que nadie ha medido.
                                </p>
                                <p v-else-if="valoracion.residual === null" class="text-sm text-muted-foreground">
                                    Falta declarar el riesgo residual: lo que se acepta es lo que queda después de
                                    tratar, no lo que había al empezar.
                                </p>
                                <template v-else>
                                    <p
                                        v-if="sinRespaldo"
                                        class="flex items-start gap-2 rounded-lg bg-estado-en-progreso-suave px-3 py-2.5 text-[13px] text-foreground"
                                    >
                                        <TriangleAlertIcon
                                            class="mt-0.5 size-4 shrink-0 text-estado-en-progreso"
                                            aria-hidden="true"
                                        />
                                        <span>
                                            Firmarías un residual de <span class="cifra">{{ valoracion.residual }}</span>
                                            sin ningún control implantado detrás.
                                        </span>
                                    </p>
                                    <!--
                                        La variante `acento` que DESIGN.md reserva a los flujos de
                                        revisión y auditoría, y su segundo uso tras «Emitir versión».
                                        Va aquí, en la columna lateral, donde no compite con ningún
                                        primario — que es la condición que pone §9.
                                    -->
                                    <Button variant="acento" class="w-full" @click="aceptando = true">
                                        Aceptar el riesgo
                                    </Button>
                                </template>
                            </template>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </motion.div>

        <!-- Valorar -------------------------------------------------------- -->
        <Dialog v-model:open="valorando">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Valorar el riesgo</DialogTitle>
                    <DialogDescription>
                        Se guarda una valoración nueva y la anterior pasa al histórico con la escala que tenía. No
                        se sobrescribe nada.
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-[60vh] space-y-5 overflow-y-auto px-1">
                    <CampoOpciones
                        v-model="valorar.probabilidad"
                        nombre="probabilidad"
                        etiqueta="Probabilidad"
                        :opciones="probabilidades"
                        :error="valorar.errors.probabilidad"
                        requerido
                    />

                    <CampoOpciones
                        v-model="valorar.impacto"
                        nombre="impacto"
                        etiqueta="Impacto"
                        :opciones="impactos"
                        :error="valorar.errors.impacto"
                        :ayuda="
                            sugerencia.impacto !== null
                                ? `Los activos de este riesgo sugieren ${sugerencia.impacto}. Es una propuesta: decides tú.`
                                : undefined
                        "
                        requerido
                    />

                    <p v-if="bandaPrevista" class="text-sm">
                        Riesgo intrínseco: <span class="cifra font-semibold">{{ previsualizacion }}</span> ·
                        {{ bandaPrevista.etiqueta }}
                    </p>

                    <Separator />

                    <FilaCampos>
                        <CampoSelect
                            v-model="valorar.probabilidad_residual"
                            nombre="probabilidad_residual"
                            etiqueta="Probabilidad residual"
                            :opciones="probabilidadesResiduales"
                            :error="valorar.errors.probabilidad_residual"
                        />
                        <CampoSelect
                            v-model="valorar.impacto_residual"
                            nombre="impacto_residual"
                            etiqueta="Impacto residual"
                            :opciones="impactosResiduales"
                            :error="valorar.errors.impacto_residual"
                        />
                    </FilaCampos>

                    <CampoTextarea
                        v-model="valorar.justificacion_residual"
                        nombre="justificacion_residual"
                        etiqueta="Por qué baja"
                        :error="valorar.errors.justificacion_residual"
                        :filas="3"
                        ayuda="Qué hace que el riesgo quede en ese nivel. Es lo que el auditor lee al lado de la firma."
                    />

                    <!--
                        Las cuatro etiquetas van cortas porque `CampoOpciones` las
                        pinta en fila; la descripción de la elegida va debajo, en la
                        ayuda, y cambia al cambiar de opción. Meterla en la etiqueta
                        dejaba cuatro píldoras que no caben ni en escritorio.
                    -->
                    <CampoOpciones
                        v-model="valorar.decision"
                        nombre="decision"
                        etiqueta="Qué se hace con él"
                        :opciones="opcionesDecision"
                        :error="valorar.errors.decision"
                        :ayuda="descripcionDecision"
                        requerido
                    />

                    <CampoTextarea
                        v-model="valorar.nota"
                        nombre="nota"
                        etiqueta="Nota"
                        :error="valorar.errors.nota"
                        :filas="2"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="valorando = false">Cancelar</Button>
                    <Button :disabled="valorar.processing" @click="enviarValoracion">
                        {{ valorar.processing ? 'Guardando…' : 'Guardar valoración' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Vincular salvaguarda -------------------------------------------- -->
        <Dialog v-model:open="vinculando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Vincular un control</DialogTitle>
                    <DialogDescription>
                        Sólo los requisitos que le aplican a la organización: uno excluido por el motor de
                        categorización no protege de nada.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoSelect
                        v-model="vincular.implantacion_id"
                        nombre="implantacion_id"
                        etiqueta="Control"
                        :opciones="candidatos"
                        :error="vincular.errors.implantacion_id"
                        placeholder="Elige un requisito"
                        requerido
                    />

                    <CampoTextarea
                        v-model="vincular.nota"
                        nombre="nota"
                        etiqueta="Por qué cubre este riesgo"
                        :error="vincular.errors.nota"
                        :filas="3"
                        ayuda="Opcional, pero es lo que se lee cuando alguien pregunta de dónde sale el residual."
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="vinculando = false">Cancelar</Button>
                    <Button :disabled="vincular.processing || !vincular.implantacion_id" @click="enviarVinculo">
                        {{ vincular.processing ? 'Vinculando…' : 'Vincular' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Aceptar --------------------------------------------------------- -->
        <Dialog v-model:open="aceptando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Aceptar el riesgo</DialogTitle>
                    <DialogDescription>
                        Declaras que la organización conoce esta exposición y decide convivir con ella. Queda
                        firmado con tu nombre y la fecha de hoy, y la valoración pasa a ser inmutable: para
                        cambiar de opinión hay que volver a valorar, lo que deja constancia de las dos decisiones.
                    </DialogDescription>
                </DialogHeader>

                <CampoTextarea
                    v-model="aceptar.nota"
                    nombre="nota"
                    etiqueta="Nota de la aceptación"
                    :error="aceptar.errors.nota"
                    :filas="3"
                    ayuda="Dónde se decidió. «Aceptado en el comité de seguridad del 3 de marzo»."
                />

                <DialogFooter>
                    <Button variant="outline" @click="aceptando = false">Cancelar</Button>
                    <Button variant="acento" :disabled="aceptar.processing" @click="enviarAceptacion">
                        {{ aceptar.processing ? 'Firmando…' : 'Aceptar el riesgo' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
