<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import BotonEstado from '@/components/BotonEstado.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import HistoricoTransiciones, { type Transicion } from '@/components/HistoricoTransiciones.vue';
import IconoTipo from '@/components/IconoTipo.vue';
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
import BarraPlazo, { type Plazo } from '@/components/vulnerabilidad/BarraPlazo.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible, formatoFechaHora } from '@/lib/celdas';
import type { Opcion } from '@/lib/formularios';
import { tono } from '@/lib/tonos';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { InfoIcon, PlusIcon } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * La ficha de una vulnerabilidad (invariante 8, A.8.8, `op.exp.4`).
 *
 * La columna lateral va en el orden de `DESIGN.md`: «Estado» arriba y «Ficha»
 * después. **Lo que manda es el plazo**: el auditor no pregunta si está
 * cerrada, pregunta cuánto tardó, así que la primera tarjeta es la barra de
 * `CicloRemediacion` con los días en grande y el camino recorrido debajo. La
 * severidad es ordinal y se pinta como escala de pasos, no como badge (§ 9). **Los destinos que piden nota la piden antes de enviar**: aceptar
 * (motivo), cerrar (cómo se verificó), reabrir o devolver a remediación
 * (por qué). `CambiarEstadoVulnerabilidad` lo exige igual; aquí sólo se evita
 * el viaje de ida y vuelta.
 */
interface Destino {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
    exigeNota: boolean;
}

interface PasoCamino {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
    fecha: string | null;
    paso: 'dado' | 'saltado' | 'actual' | 'pendiente';
}

interface Vulnerabilidad {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    cve: string | null;
    cvss: string | null;
    vector: string | null;
    /** `null` si no hay vector o no es de la v3: entonces se enseña la cadena tal cual. */
    vectorDesglose: { metrica: string; valor: string }[] | null;
    severidad: string;
    severidadEtiqueta: string;
    severidadTono: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    origen: string;
    fechaDeteccion: string;
    fechaLimite: string | null;
    fueraDePlazo: boolean;
    responsable: string | null;
    proveedor: { id: number; codigo: string; nombre: string } | null;
    riesgo: { id: number; codigo: string; titulo: string } | null;
    incidente: { id: number; codigo: string; titulo: string } | null;
    remediacion: string | null;
    motivoAceptacion: string | null;
    aceptadaPor: string | null;
    aceptadaEn: string | null;
    verificacion: string | null;
    verificadaPor: string | null;
    cerradaEn: string | null;
}

const props = defineProps<{
    vulnerabilidad: Vulnerabilidad;
    severidades: Opcion[];
    plazo: Plazo | null;
    camino: PasoCamino[];
    activos: { id: number; codigo: string; nombre: string; tipo: string; tipoTono: string; tipoIcono: string }[];
    transiciones: Destino[];
    historial: Transicion[];
    tareas: { id: number; titulo: string; estado: string; tono: string; icono: string }[];
    responsables: Opcion[];
    prioridades: Opcion[];
    puedeGestionar: boolean;
    puedeAbrirTarea: boolean;
}>();

const pagina = usePage();
const errorEstado = computed(() => (pagina.props.errors as Record<string, string | undefined>).nota);

/* ---------------------------------------------------------------- Estado */

const destino = ref<Destino | null>(null);
const nota = ref('');
const enviando = ref(false);

const rotuloNota = computed(() => {
    switch (destino.value?.valor) {
        case 'cerrada':
            return 'Cómo se comprobó que ya no está';
        case 'aceptada':
            return 'Por qué no se corrige';
        case 'falso_positivo':
            return 'Por qué no era una vulnerabilidad';
        default:
            return `Por qué pasa a «${destino.value?.etiqueta ?? ''}»`;
    }
});

function mover(paso: Destino): void {
    if (paso.exigeNota && destino.value?.valor !== paso.valor) {
        destino.value = paso;
        nota.value = '';

        return;
    }

    router.post(
        `/vulnerabilidades/${props.vulnerabilidad.id}/estado`,
        { estado: paso.valor, nota: nota.value || null },
        {
            preserveScroll: true,
            onStart: () => (enviando.value = true),
            onFinish: () => (enviando.value = false),
            onSuccess: () => {
                destino.value = null;
                nota.value = '';
            },
        },
    );
}

/* ---------------------------------------------------------------- Tareas */

const abriendoTarea = ref(false);
const tarea = useForm({ titulo: '', descripcion: '', prioridad: 'alta', responsable_id: '', fecha_limite: '' });

function abrirTarea(): void {
    tarea.post(`/vulnerabilidades/${props.vulnerabilidad.id}/tareas`, {
        preserveScroll: true,
        onSuccess: () => {
            abriendoTarea.value = false;
            tarea.reset();
        },
    });
}

const cuando = (fecha: string | null): string => (fecha ? formatoFechaHora.format(new Date(fecha)) : '—');

/* ------------------------------------------------------------------ Ficha */

/** Lo que un auditor va a pedir y falta: neutro y sólo cuando falta (§ 9, «Fichas»). */
const pendientes = computed<string[]>(() =>
    [
        props.vulnerabilidad.responsable === null ? 'Responsable sin asignar' : null,
        props.vulnerabilidad.descripcion === null ? 'Descripción' : null,
        props.activos.length === 0 ? 'Activos afectados' : null,
    ].filter((uno): uno is string => uno !== null),
);

const estadoActual = computed(() => tono(props.vulnerabilidad.estadoTono));
const desde = computed(() => props.historial.at(-1)?.fecha ?? null);
const terminal = computed(() => ['cerrada', 'aceptada', 'falso_positivo'].includes(props.vulnerabilidad.estado));

const pasoSeveridad = computed(() => props.severidades.findIndex((una) => una.valor === props.vulnerabilidad.severidad));

const dias = (n: number): string => (n === 1 ? '1 día' : `${n} días`);

const resumenPlazo = computed(() => {
    const plazo = props.plazo;

    if (plazo === null) {
        return '';
    }

    if (plazo.fuera > 0) {
        return plazo.corre ? `abierta · ${dias(plazo.fuera)} más allá del plazo de ${plazo.dias}` : `de ${plazo.dias} · ${dias(plazo.fuera)} fuera de plazo`;
    }

    if (plazo.corre) {
        return `de ${plazo.dias} · quedan ${dias(plazo.dias - plazo.transcurridos)}`;
    }

    return plazo.transcurridos === 0 ? `de ${plazo.dias} · resuelta el mismo día en que se detectó` : `de ${plazo.dias} · dentro de plazo`;
});

const rotuloPaso = (paso: PasoCamino): string => {
    if (paso.fecha !== null) {
        return formatoFechaHora.format(new Date(paso.fecha));
    }

    return paso.paso === 'saltado' ? 'no pasó por aquí' : 'pendiente';
};
</script>

<template>
    <AppLayout :titulo="vulnerabilidad.codigo">
        <CabeceraPagina
            :titulo="vulnerabilidad.titulo"
            :codigo="vulnerabilidad.codigo"
            :descripcion="`${vulnerabilidad.origen} · detectada el ${fechaLegible(vulnerabilidad.fechaDeteccion)}`"
        >
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/vulnerabilidades/${vulnerabilidad.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <!-- El único rojo: el plazo que se comprometió y pasó sin arreglo. -->
        <Aviso
            v-if="vulnerabilidad.fueraDePlazo"
            tono="error"
            :titulo="plazo ? `Fuera de plazo desde hace ${dias(plazo.fuera)}` : 'Fuera de plazo'"
        >
            Había que remediarla antes del {{ fechaLegible(vulnerabilidad.fechaLimite) }} y sigue sin arreglo. Si no se va a
            corregir, que supervisión la acepte con su motivo.
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
                <li v-for="pendiente in pendientes" :key="pendiente">
                    <Link
                        v-if="puedeGestionar"
                        :href="`/vulnerabilidades/${vulnerabilidad.id}/editar`"
                        class="inline-flex h-7 items-center rounded-full bg-muted px-2.5 text-[13px] font-medium text-secondary-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                    >
                        {{ pendiente }}
                    </Link>
                    <span
                        v-else
                        class="inline-flex h-7 items-center rounded-full bg-muted px-2.5 text-[13px] font-medium text-secondary-foreground"
                    >
                        {{ pendiente }}
                    </span>
                </li>
            </ul>
        </section>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Plazo</CardTitle>
                        <CardDescription v-if="plazo">
                            {{ dias(plazo.dias) }} por ser de severidad {{ vulnerabilidad.severidadEtiqueta.toLowerCase() }}, según la
                            política de la organización.
                        </CardDescription>
                        <CardDescription v-else>Una informativa no tiene plazo de remediación.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <template v-if="plazo">
                            <p class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <span class="cifra text-4xl font-bold tracking-tight">{{ dias(plazo.transcurridos) }}</span>
                                <span class="text-sm text-muted-foreground" :class="{ 'font-medium text-destructive': plazo.fuera > 0 }">
                                    {{ resumenPlazo }}
                                </span>
                            </p>
                            <BarraPlazo :plazo="plazo" />
                        </template>

                        <ol
                            aria-label="Camino recorrido"
                            class="grid gap-3 sm:grid-cols-4"
                            :class="{ 'border-t pt-4': plazo }"
                        >
                            <li
                                v-for="paso in camino"
                                :key="paso.valor"
                                class="space-y-1"
                                :class="{ 'opacity-60': paso.paso === 'pendiente' || paso.paso === 'saltado' }"
                                :aria-current="paso.paso === 'actual' ? 'step' : undefined"
                            >
                                <span
                                    class="flex items-center gap-1.5 text-[13px]"
                                    :class="[
                                        paso.paso === 'pendiente' || paso.paso === 'saltado' ? 'text-muted-foreground' : tono(paso.tono).texto,
                                        paso.paso === 'actual' ? 'font-semibold' : 'font-medium',
                                    ]"
                                >
                                    <IconoTipo
                                        :nombre="paso.paso === 'pendiente' || paso.paso === 'saltado' ? 'Circle' : paso.icono"
                                        clase="size-4"
                                    />
                                    {{ paso.etiqueta }}
                                </span>
                                <span class="block text-xs text-muted-foreground" :class="{ cifra: paso.fecha }">{{ rotuloPaso(paso) }}</span>
                            </li>
                        </ol>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Qué es</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-5 text-sm">
                        <!-- Lo que cierra o acepta la vulnerabilidad va primero y citado, con su firma. -->
                        <figure v-if="vulnerabilidad.verificacion" class="space-y-1.5">
                            <figcaption class="text-[13px] font-medium text-muted-foreground">Cómo se verificó el cierre</figcaption>
                            <blockquote class="border-l-2 border-estado-implantado pl-3.5 text-[15px] leading-6 whitespace-pre-line">
                                {{ vulnerabilidad.verificacion }}
                            </blockquote>
                            <p class="pl-4 text-xs text-muted-foreground">
                                {{ vulnerabilidad.verificadaPor }} · <span class="cifra">{{ cuando(vulnerabilidad.cerradaEn) }}</span>
                            </p>
                        </figure>
                        <figure v-if="vulnerabilidad.motivoAceptacion" class="space-y-1.5">
                            <figcaption class="text-[13px] font-medium text-muted-foreground">Por qué se acepta</figcaption>
                            <blockquote class="border-l-2 border-estado-planificado pl-3.5 text-[15px] leading-6 whitespace-pre-line">
                                {{ vulnerabilidad.motivoAceptacion }}
                            </blockquote>
                            <p class="pl-4 text-xs text-muted-foreground">
                                {{ vulnerabilidad.aceptadaPor }} · <span class="cifra">{{ cuando(vulnerabilidad.aceptadaEn) }}</span>
                            </p>
                        </figure>

                        <p v-if="vulnerabilidad.descripcion" class="max-w-prose text-[15px] leading-6 text-pretty whitespace-pre-line">
                            {{ vulnerabilidad.descripcion }}
                        </p>

                        <dl
                            class="grid gap-x-6 gap-y-3.5 sm:grid-cols-[11rem_minmax(0,1fr)]"
                            :class="{ 'border-t pt-4': vulnerabilidad.verificacion || vulnerabilidad.motivoAceptacion || vulnerabilidad.descripcion }"
                        >
                            <dt class="text-muted-foreground">Cómo se arregla</dt>
                            <dd v-if="vulnerabilidad.remediacion" class="max-w-prose whitespace-pre-line text-secondary-foreground">
                                {{ vulnerabilidad.remediacion }}
                            </dd>
                            <dd v-else class="text-muted-foreground">Sin escribir.</dd>

                            <dt class="text-muted-foreground">Vector CVSS</dt>
                            <dd v-if="vulnerabilidad.vector" class="cifra text-xs break-all text-muted-foreground sm:pt-0.5">
                                {{ vulnerabilidad.vector }}
                            </dd>
                            <dd v-else class="text-muted-foreground">
                                {{ vulnerabilidad.cvss ? `Sin vector: la puntuación ${vulnerabilidad.cvss} se declaró a mano.` : 'Sin puntuación CVSS.' }}
                            </dd>
                        </dl>

                        <dl v-if="vulnerabilidad.vectorDesglose" class="grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="Vector CVSS desglosado">
                            <div v-for="metrica in vulnerabilidad.vectorDesglose" :key="metrica.metrica" class="rounded-md bg-superficie px-3 py-2.5">
                                <dt class="text-xs text-muted-foreground">{{ metrica.metrica }}</dt>
                                <dd class="mt-0.5 font-medium">{{ metrica.valor }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Dónde está</CardTitle>
                        <CardDescription>Los activos afectados. Deciden también qué ve un auditor externo.</CardDescription>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <p v-if="activos.length === 0" class="text-muted-foreground">
                            Sin activos: no se sabe dónde está, y un auditor externo no la ve.
                        </p>
                        <ul v-else class="divide-y rounded-md border">
                            <li v-for="activo in activos" :key="activo.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
                                <CeldaBadge :valor="{ valor: activo.tipoTono, etiqueta: activo.tipo, tono: activo.tipoTono, icono: activo.tipoIcono }" />
                                <Link :href="`/activos/${activo.id}`" class="font-medium hover:underline hover:underline-offset-4">
                                    <span class="cifra text-[13px] text-muted-foreground">{{ activo.codigo }}</span> · {{ activo.nombre }}
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Histórico</CardTitle>
                        <CardDescription>Cada cambio de estado, con quién y cuándo.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <HistoricoTransiciones :transiciones="historial" />
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center gap-3 rounded-md px-4 py-3.5" :class="estadoActual.badge">
                            <IconoTipo :nombre="vulnerabilidad.estadoIcono" clase="size-6 shrink-0" />
                            <div class="flex flex-col">
                                <span class="text-base font-semibold">{{ vulnerabilidad.estadoEtiqueta }}</span>
                                <span v-if="desde" class="text-xs text-secondary-foreground">Desde el {{ fechaLegible(desde) }}</span>
                            </div>
                        </div>

                        <Aviso v-if="errorEstado" tono="error">{{ errorEstado }}</Aviso>

                        <!-- Sin esta línea, a quien sólo lee le queda una tarjeta con el estado y nada debajo. -->
                        <p v-if="!puedeGestionar" class="text-[13px] leading-[18px] text-muted-foreground">
                            El estado lo mueve quien gestiona las vulnerabilidades, y cada cambio queda en el histórico, con quién y
                            cuándo.
                        </p>
                        <p v-else-if="terminal" class="text-[13px] leading-[18px] text-muted-foreground">
                            Si vuelve a aparecer, se reabre con motivo, y lo que la cerró o la aceptó pierde su firma.
                        </p>

                        <div v-if="puedeGestionar && transiciones.length > 0" class="space-y-2">
                            <p class="text-[13px] font-medium text-muted-foreground">Pasar a</p>
                            <div class="flex flex-wrap gap-2">
                                <BotonEstado
                                    v-for="paso in transiciones"
                                    :key="paso.valor"
                                    :destino="paso"
                                    :deshabilitado="enviando"
                                    @click="mover(paso)"
                                />
                            </div>
                        </div>

                        <div v-if="destino" class="space-y-2 border-t pt-3">
                            <CampoTextarea
                                v-model="nota"
                                nombre="nota"
                                :etiqueta="rotuloNota"
                                :filas="3"
                                :ayuda="destino.valor === 'aceptada' ? 'Aceptar es de supervisión y queda firmado con tu nombre.' : undefined"
                                requerido
                            />
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" @click="destino = null">Cancelar</Button>
                                <Button size="sm" :disabled="enviando || nota.trim() === ''" @click="mover(destino)">
                                    Pasar a «{{ destino.etiqueta }}»
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <div class="space-y-2">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="text-[13px] text-muted-foreground">Severidad</span>
                                <span>
                                    <strong class="font-semibold">{{ vulnerabilidad.severidadEtiqueta }}</strong>
                                    <template v-if="vulnerabilidad.cvss">
                                        <span class="text-muted-foreground"> · CVSS </span>
                                        <span class="cifra font-medium">{{ vulnerabilidad.cvss }}</span>
                                    </template>
                                </span>
                            </div>
                            <!-- Ordinal: la longitud contesta si es más que la otra antes que el texto (§ 9). -->
                            <div
                                class="grid grid-cols-5 gap-0.5"
                                role="img"
                                :aria-label="`Severidad ${vulnerabilidad.severidadEtiqueta.toLowerCase()}, escalón ${pasoSeveridad + 1} de ${severidades.length}`"
                            >
                                <span
                                    v-for="(una, indice) in severidades"
                                    :key="una.valor"
                                    class="h-2 rounded-[2px] first:rounded-l-full last:rounded-r-full"
                                    :class="indice <= pasoSeveridad ? 'bg-primary' : 'bg-muted'"
                                />
                            </div>
                            <div class="grid grid-cols-5 gap-0.5 text-xs text-muted-foreground" aria-hidden="true">
                                <span
                                    v-for="(una, indice) in severidades"
                                    :key="una.valor"
                                    class="truncate"
                                    :class="{ 'font-medium text-foreground': indice === pasoSeveridad }"
                                    :title="una.etiqueta"
                                >
                                    {{ una.etiqueta }}
                                </span>
                            </div>
                        </div>

                        <dl class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-3 border-t pt-4">
                            <dt class="text-muted-foreground">Responsable</dt>
                            <dd v-if="vulnerabilidad.responsable">{{ vulnerabilidad.responsable }}</dd>
                            <dd v-else>
                                <span class="inline-flex h-5 items-center rounded-full bg-muted px-2 text-xs font-medium text-secondary-foreground">
                                    Sin asignar
                                </span>
                            </dd>
                            <dt class="text-muted-foreground">CVE</dt>
                            <dd :class="vulnerabilidad.cve ? 'cifra text-[13px]' : 'text-muted-foreground'">{{ vulnerabilidad.cve ?? 'No tiene' }}</dd>
                            <dt class="text-muted-foreground">Origen</dt>
                            <dd>{{ vulnerabilidad.origen }}</dd>
                            <dt class="text-muted-foreground">Detectada</dt>
                            <dd class="cifra text-[13px]">{{ fechaLegible(vulnerabilidad.fechaDeteccion) }}</dd>
                            <dt class="text-muted-foreground">Límite</dt>
                            <dd v-if="vulnerabilidad.fechaLimite" class="cifra text-[13px]" :class="{ 'text-destructive': vulnerabilidad.fueraDePlazo }">
                                {{ fechaLegible(vulnerabilidad.fechaLimite) }}
                            </dd>
                            <dd v-else class="text-muted-foreground">Sin plazo</dd>
                        </dl>

                        <dl class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-3 border-t pt-4">
                            <dt class="text-muted-foreground">Depende de</dt>
                            <dd v-if="vulnerabilidad.proveedor">
                                <Link :href="`/proveedores/${vulnerabilidad.proveedor.id}`" class="text-primary hover:underline hover:underline-offset-4">
                                    {{ vulnerabilidad.proveedor.nombre }}
                                </Link>
                            </dd>
                            <dd v-else class="text-muted-foreground">Ningún proveedor</dd>
                            <dt class="text-muted-foreground">Riesgo</dt>
                            <dd v-if="vulnerabilidad.riesgo">
                                <Link
                                    :href="`/riesgos/${vulnerabilidad.riesgo.id}`"
                                    class="cifra text-[13px] text-primary hover:underline hover:underline-offset-4"
                                    :title="vulnerabilidad.riesgo.titulo"
                                >
                                    {{ vulnerabilidad.riesgo.codigo }}
                                </Link>
                            </dd>
                            <dd v-else class="text-muted-foreground">Sin vincular</dd>
                            <dt class="text-muted-foreground">Incidente</dt>
                            <dd v-if="vulnerabilidad.incidente">
                                <Link
                                    :href="`/incidentes/${vulnerabilidad.incidente.id}`"
                                    class="cifra text-[13px] text-primary hover:underline hover:underline-offset-4"
                                    :title="vulnerabilidad.incidente.titulo"
                                >
                                    {{ vulnerabilidad.incidente.codigo }}
                                </Link>
                            </dd>
                            <dd v-else class="text-muted-foreground">Sin vincular</dd>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Tareas</CardTitle>
                        <CardDescription>Sin fecha propia, vencen con el plazo de remediación.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <p v-if="tareas.length === 0" class="text-muted-foreground">Ninguna abierta desde aquí.</p>
                        <ul v-else class="space-y-2.5">
                            <li v-for="una in tareas" :key="una.id" class="flex flex-col items-start gap-1">
                                <Link :href="`/tareas/${una.id}`" class="font-medium hover:underline hover:underline-offset-4">{{ una.titulo }}</Link>
                                <CeldaBadge :valor="{ valor: una.estado, etiqueta: una.estado, tono: una.tono, icono: una.icono }" />
                            </li>
                        </ul>
                        <Button v-if="puedeAbrirTarea" variant="outline" size="sm" @click="abriendoTarea = true">
                            <PlusIcon aria-hidden="true" />
                            Abrir una tarea
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="abriendoTarea">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Abrir una tarea</DialogTitle>
                    <DialogDescription>
                        Queda en el plan de acción con origen «Vulnerabilidad» y enlazada aquí. Sin fecha, vence con el plazo de
                        remediación.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4">
                    <CampoTexto v-model="tarea.titulo" nombre="titulo" etiqueta="Qué hay que hacer" :error="tarea.errors.titulo" requerido />
                    <CampoTextarea v-model="tarea.descripcion" nombre="descripcion" etiqueta="Detalle" :filas="2" :error="tarea.errors.descripcion" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <CampoSelect
                            v-model="tarea.prioridad"
                            nombre="prioridad"
                            etiqueta="Prioridad"
                            :opciones="prioridades"
                            :error="tarea.errors.prioridad"
                            requerido
                        />
                        <CampoTexto
                            v-model="tarea.fecha_limite"
                            nombre="fecha_limite"
                            etiqueta="Fecha límite"
                            tipo="date"
                            :error="tarea.errors.fecha_limite"
                        />
                    </div>
                    <CampoSelect
                        v-model="tarea.responsable_id"
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :error="tarea.errors.responsable_id"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="abriendoTarea = false">Cancelar</Button>
                    <Button :disabled="tarea.processing" @click="abrirTarea">Abrir</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
