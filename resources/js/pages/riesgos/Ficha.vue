<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoOpciones from '@/components/formulario/CampoOpciones.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import MatrizRiesgo from '@/components/riesgo/MatrizRiesgo.vue';
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
import { Separator } from '@/components/ui/separator';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFecha } from '@/lib/celdas';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ShieldPlusIcon, TriangleAlertIcon } from '@lucide/vue';
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

/** Las anteriores a la vigente. La vigente ya se enseña entera arriba. */
const anteriores = computed(() => props.historico.filter((valoracion) => !valoracion.vigente));

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
        <CabeceraPagina :titulo="riesgo.titulo" :descripcion="riesgo.amenaza">
            <template #acciones>
                <Link :href="`/riesgos/${riesgo.id}/editar`">
                    <Button variant="outline">Editar</Button>
                </Link>
            </template>
        </CabeceraPagina>

        <motion.div :variants="variantesEntrada" initial="oculto" animate="visible" class="space-y-6">
            <div class="flex flex-wrap items-center gap-2">
                <span class="cifra text-sm text-muted-foreground">{{ riesgo.codigo }}</span>

                <CeldaBadge
                    v-if="valoracion"
                    :valor="{
                        valor: valoracion.intrinseco,
                        etiqueta: `Intrínseco: ${valoracion.intrinseco_nivel.etiqueta} (${valoracion.intrinseco})`,
                        tono: valoracion.intrinseco_nivel.tono,
                        icono: valoracion.intrinseco_nivel.icono,
                    }"
                />

                <CeldaBadge
                    v-if="valoracion?.residual_nivel"
                    :valor="{
                        valor: valoracion.residual,
                        etiqueta: `Residual: ${valoracion.residual_nivel.etiqueta} (${valoracion.residual})`,
                        tono: valoracion.residual_nivel.tono,
                        icono: valoracion.residual_nivel.icono,
                    }"
                />

                <CeldaBadge
                    v-if="valoracion"
                    :valor="{
                        valor: valoracion.decision,
                        etiqueta: valoracion.decision_etiqueta,
                        tono: valoracion.decision_tono,
                        icono: valoracion.decision_icono,
                    }"
                />

                <CeldaBadge
                    v-if="riesgo.revision_vencida"
                    :valor="{ valor: riesgo.fecha_revision, etiqueta: 'Reevaluación vencida', tono: 'caducada' }"
                />
            </div>

            <!--
                El hallazgo que este módulo existe para enseñar: se declara que el
                riesgo baja y no hay ni una salvaguarda implantada que lo sostenga.
                Va en bloque y no como toast porque tiene que seguir ahí mientras se
                mira la ficha.
            -->
            <Aviso v-if="sinRespaldo" tono="error" titulo="El riesgo residual no tiene nada que lo respalde">
                Se declara que el riesgo baja de <span class="cifra">{{ valoracion?.intrinseco }}</span> a
                <span class="cifra">{{ valoracion?.residual }}</span
                >, y ninguna de las salvaguardas vinculadas está implantada. Es lo primero que un auditor pide
                que se enseñe. La herramienta no cambia la cifra —la decidió una persona y la aprueba el
                propietario del riesgo—, sólo señala que no cuadra.
            </Aviso>

            <Aviso
                v-if="metodologia.esDeFabrica || !metodologia.estaAprobada"
                tono="info"
                titulo="La metodología no está aprobada"
            >
                Este riesgo se está midiendo con
                <template v-if="metodologia.esDeFabrica">la escala de partida de Statera</template>
                <template v-else>«{{ metodologia.nombre }}»</template>, que nadie ha firmado. ISO 27001 pide
                que los criterios de riesgo los establezca la organización.
                <Link href="/riesgos/metodologia" class="font-medium underline underline-offset-4">
                    Definirla y aprobarla
                </Link>
            </Aviso>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div class="space-y-6">
                    <Card>
                        <CardHeader class="flex-row items-start justify-between gap-4 space-y-0">
                            <div>
                                <CardTitle>Valoración</CardTitle>
                                <CardDescription>
                                    Lo que vale el riesgo antes de tratarlo y lo que queda después. El residual lo
                                    declara quien valora y lo aprueba el propietario del riesgo: la herramienta no
                                    lo calcula.
                                </CardDescription>
                            </div>
                            <Button variant="outline" size="sm" @click="valorando = true">
                                {{ valoracion ? 'Valorar de nuevo' : 'Valorar' }}
                            </Button>
                        </CardHeader>

                        <CardContent>
                            <EstadoVacio
                                v-if="!valoracion"
                                :icono="TriangleAlertIcon"
                                titulo="Todavía sin valorar"
                                descripcion="Un riesgo registrado y sin medir no cuenta en ninguna cifra de exposición: no está por encima ni por debajo de ningún umbral, sencillamente no se sabe."
                            />

                            <div v-else class="space-y-5">
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <p class="text-xs text-muted-foreground">Riesgo intrínseco</p>
                                        <p class="mt-1 flex items-baseline gap-2">
                                            <span class="cifra text-3xl font-semibold">{{ valoracion.intrinseco }}</span>
                                            <span class="text-sm text-muted-foreground">
                                                {{ valoracion.intrinseco_nivel.etiqueta }}
                                            </span>
                                        </p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                            Probabilidad {{ valoracion.probabilidad }} × impacto
                                            {{ valoracion.impacto }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs text-muted-foreground">Riesgo residual</p>
                                        <p v-if="valoracion.residual !== null" class="mt-1 flex items-baseline gap-2">
                                            <span class="cifra text-3xl font-semibold">{{ valoracion.residual }}</span>
                                            <span class="text-sm text-muted-foreground">
                                                {{ valoracion.residual_nivel?.etiqueta }}
                                            </span>
                                        </p>
                                        <p v-else class="mt-1 text-sm text-muted-foreground">
                                            Sin declarar todavía
                                        </p>
                                        <p
                                            v-if="valoracion.probabilidad_residual !== null"
                                            class="mt-0.5 text-xs text-muted-foreground"
                                        >
                                            Probabilidad {{ valoracion.probabilidad_residual }} × impacto
                                            {{ valoracion.impacto_residual }}
                                        </p>
                                    </div>
                                </div>

                                <p v-if="valoracion.justificacion_residual" class="text-sm">
                                    {{ valoracion.justificacion_residual }}
                                </p>

                                <Separator />

                                <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_auto]">
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

                                    <div class="w-full sm:w-56">
                                        <MatrizRiesgo
                                            :bandas="metodologia.bandas"
                                            :probabilidad="metodologia.probabilidad"
                                            :impacto="metodologia.impacto"
                                            :marcadas="marcadas"
                                        />
                                    </div>
                                </div>

                                <p class="text-xs text-muted-foreground">
                                    Valorado el {{ fecha(valoracion.valorada_en) }}
                                    <template v-if="valoracion.valorada_por"> por {{ valoracion.valorada_por }}</template
                                    >, con la escala de «{{ metodologia.nombre }}».
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex-row items-start justify-between gap-4 space-y-0">
                            <div>
                                <CardTitle>Salvaguardas</CardTitle>
                                <CardDescription>
                                    Los controles implantados que se apoyan contra este riesgo. Apuntan a
                                    implantaciones y no a requisitos: la diferencia entre «el ENS pide cifrado» y
                                    «lo tenemos puesto en este sistema».
                                </CardDescription>
                            </div>
                            <Button variant="outline" size="sm" @click="vinculando = true">Vincular control</Button>
                        </CardHeader>

                        <CardContent>
                            <EstadoVacio
                                v-if="riesgo.salvaguardas.length === 0"
                                :icono="ShieldPlusIcon"
                                titulo="Ningún control apoyado contra este riesgo"
                                descripcion="Un riesgo que se decide mitigar y no tiene ni una salvaguarda vinculada es una declaración de intenciones, no un tratamiento."
                            />

                            <ul v-else class="divide-y">
                                <li
                                    v-for="salvaguarda in riesgo.salvaguardas"
                                    :key="salvaguarda.id"
                                    class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
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
                        </CardContent>
                    </Card>

                    <Card v-if="anteriores.length > 0">
                        <CardHeader>
                            <CardTitle>Valoraciones anteriores</CardTitle>
                            <CardDescription>
                                Cada una se lee con la escala que tenía en su momento: por eso «qué cambió entre
                                marzo y octubre» tiene respuesta.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ol class="space-y-4">
                                <li v-for="paso in anteriores" :key="paso.id" class="flex gap-3 text-sm">
                                    <CeldaBadge
                                        :valor="{
                                            valor: paso.intrinseco,
                                            etiqueta: `${paso.intrinseco}${paso.residual !== null ? ` → ${paso.residual}` : ''}`,
                                            tono: (paso.residual_nivel ?? paso.intrinseco_nivel).tono,
                                            icono: (paso.residual_nivel ?? paso.intrinseco_nivel).icono,
                                        }"
                                    />
                                    <div class="min-w-0">
                                        <p class="text-muted-foreground">
                                            {{ paso.decision_etiqueta }} · {{ fecha(paso.valorada_en) }}
                                            <template v-if="paso.valorada_por"> · {{ paso.valorada_por }}</template>
                                            <template v-if="paso.aceptada_en">
                                                · aceptada el {{ fecha(paso.aceptada_en) }}
                                            </template>
                                        </p>
                                        <p v-if="paso.justificacion_residual" class="mt-0.5">
                                            {{ paso.justificacion_residual }}
                                        </p>
                                        <p v-if="paso.nota" class="mt-0.5 text-muted-foreground">{{ paso.nota }}</p>
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
                        <CardContent class="space-y-4">
                            <ul class="space-y-2 text-sm">
                                <li v-for="activo in riesgo.activos" :key="activo.id">
                                    <Link
                                        :href="`/activos/${activo.id}`"
                                        class="hover:underline hover:underline-offset-4"
                                    >
                                        <span class="cifra text-xs text-muted-foreground">{{ activo.codigo }}</span>
                                        {{ activo.nombre }}
                                    </Link>
                                </li>
                            </ul>

                            <template v-if="sugerencia.impacto !== null">
                                <Separator />

                                <div>
                                    <p class="text-xs text-muted-foreground">Impacto que sugieren los activos</p>
                                    <p class="cifra mt-1 text-2xl font-semibold">{{ sugerencia.impacto }}</p>

                                    <!--
                                        Sin los motivos, un 5 sobre treinta activos parece un
                                        error de la herramienta. Esto dice quién pone el techo.
                                    -->
                                    <ul
                                        v-if="sugerencia.motivos.length > 0"
                                        class="mt-2 space-y-1 text-xs text-muted-foreground"
                                    >
                                        <li v-for="motivo in sugerencia.motivos" :key="motivo.activo">
                                            {{ motivo.activo }} — {{ motivo.dimensiones.join(', ') }}
                                        </li>
                                    </ul>

                                    <p class="mt-2 text-xs text-muted-foreground">
                                        Es una propuesta: el impacto lo decide quien valora.
                                    </p>
                                </div>
                            </template>
                        </CardContent>
                    </Card>

                    <Card class="h-fit">
                        <CardHeader>
                            <CardTitle>Cobertura</CardTitle>
                            <CardDescription>
                                En qué estado están los controles que sostienen el residual. No se almacena: se
                                calcula y se enseña al lado de lo declarado.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <p v-if="cobertura.total === 0" class="text-sm text-muted-foreground">
                                Ninguna salvaguarda vinculada.
                            </p>

                            <template v-else>
                                <BarraSegmentada :segmentos="tramosCobertura" leyenda />

                                <p class="text-sm">
                                    <span class="cifra">{{ cobertura.implantadas }}</span> de
                                    <span class="cifra">{{ cobertura.total }}</span> implantadas
                                </p>

                                <!-- La media, siempre con su denominador. -->
                                <p v-if="cobertura.madurezMedia !== null" class="text-sm text-muted-foreground">
                                    Madurez media <span class="cifra">{{ cobertura.madurezMedia }}</span> sobre
                                    <span class="cifra">{{ cobertura.madurezEvaluadas }}</span> evaluadas de
                                    <span class="cifra">{{ cobertura.total }}</span>
                                </p>
                                <p v-else class="text-sm text-muted-foreground">
                                    Ninguna salvaguarda tiene la madurez evaluada.
                                </p>
                            </template>
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
                            <dl class="grid gap-3 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-muted-foreground">Propietario</dt>
                                    <dd>{{ riesgo.propietario ?? 'Sin asignar' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-muted-foreground">Próxima reevaluación</dt>
                                    <dd class="cifra">{{ fecha(riesgo.fecha_revision) }}</dd>
                                </div>
                            </dl>

                            <template v-if="valoracion?.aceptada_en">
                                <Separator />
                                <p class="text-sm">
                                    Aceptado por <strong>{{ valoracion.aceptada_por ?? '—' }}</strong> el
                                    <span class="cifra">{{ fecha(valoracion.aceptada_en) }}</span>
                                </p>
                                <p v-if="valoracion.nota_aceptacion" class="text-sm text-muted-foreground">
                                    {{ valoracion.nota_aceptacion }}
                                </p>
                            </template>

                            <template v-else>
                                <Separator />
                                <p v-if="!valoracion" class="text-sm text-muted-foreground">
                                    No se puede aceptar un riesgo que nadie ha medido.
                                </p>
                                <p v-else-if="valoracion.residual === null" class="text-sm text-muted-foreground">
                                    Falta declarar el riesgo residual: lo que se acepta es lo que queda después de
                                    tratar, no lo que había al empezar.
                                </p>
                                <!--
                                    La variante `acento` que DESIGN.md reserva a los flujos de
                                    revisión y auditoría, y su segundo uso tras «Emitir versión».
                                    Va aquí, en la columna lateral, donde no compite con ningún
                                    primario — que es la condición que pone §9.
                                -->
                                <Button v-else variant="acento" class="w-full" @click="aceptando = true">
                                    Aceptar el riesgo
                                </Button>
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

                    <div class="grid gap-5 sm:grid-cols-2">
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
                    </div>

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
