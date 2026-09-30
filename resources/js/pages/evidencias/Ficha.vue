<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CampoSeleccionMultiple, { type OpcionTipada } from '@/components/formulario/CampoSeleccionMultiple.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
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
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { distanciaLegible, fechaLegible } from '@/lib/celdas';
import { tono } from '@/lib/tonos';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    CheckIcon,
    CopyIcon,
    DownloadIcon,
    ExternalLinkIcon,
    FileIcon,
    InfoIcon,
    PaperclipIcon,
    PencilLineIcon,
    PlusIcon,
    RepeatIcon,
} from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, ref } from 'vue';

interface Vinculo {
    implantacionId: number;
    codigo: string;
    titulo: string;
    marco: string | null;
    sistema: string;
    estado: string;
    estadoEtiqueta: string;
    estadoIcono: string;
    nota: string | null;
    editable: boolean;
}

interface Vigencia {
    estado: 'vigente' | 'por_caducar' | 'caducada' | 'sin_caducidad' | 'renovada';
    etiqueta: string;
    tono: string;
    icono: string;
    desde: string;
    hasta: string | null;
    diasTotales: number | null;
    diasTranscurridos: number | null;
    diasRestantes: number | null;
}

interface Referencia {
    id: number;
    titulo: string;
    fecha_obtencion: string;
}

interface MarcoCubierto {
    codigo: string;
    nombre: string;
    requisitos: number;
}

const props = defineProps<{
    evidencia: {
        id: number;
        titulo: string;
        tipo: string;
        tipoEtiqueta: string;
        descripcion: string | null;
        esFichero: boolean;
        nombre_fichero: string | null;
        mime: string | null;
        tamano: number | null;
        hash_sha256: string | null;
        url_externa: string | null;
        fecha_obtencion: string;
        fecha_caducidad: string | null;
        periodicidad_renovacion: string | null;
        periodicidadEtiqueta: string | null;
        haCaducado: boolean;
        responsable: string | null;
    };
    vigencia: Vigencia;
    renovadaPor: Referencia | null;
    renuevaA: Referencia | null;
    vinculos: Vinculo[];
    marcos: MarcoCubierto[];
    puede: { gestionar: boolean; vincular: boolean };
    /** Llega sólo cuando se pide: prop opcional de Inertia. */
    implantacionesDisponibles?: OpcionTipada[];
}>();

const { variantesEntrada } = useMovimientoReducido();

const periodicidad = computed(() => props.evidencia.periodicidadEtiqueta);

const tamano = computed(() => {
    if (props.evidencia.tamano === null) {
        return null;
    }

    const mb = props.evidencia.tamano / 1024 / 1024;

    return mb >= 1
        ? `${mb.toFixed(1).replace('.', ',')} MB`
        : `${Math.max(Math.round(props.evidencia.tamano / 1024), 1)} KB`;
});

const formato = computed(() => props.evidencia.mime?.split('/').pop()?.toUpperCase() ?? null);

/*
 * --- La tarjeta principal ---
 *
 * A la izquierda, en cuántos marcos cuenta: es la cifra que justifica el
 * producto, y estaba escondida en la descripción de una tarjeta. A la derecha,
 * hasta cuándo prueba, con el periodo dibujado: una fecha sola hay que leerla y
 * compararla con hoy.
 */
const marcosConPrueba = computed(() => props.marcos.filter((marco) => marco.requisitos > 0).length);
const maximo = computed(() => Math.max(...props.marcos.map((marco) => marco.requisitos), 1));

const avance = computed(() => {
    const { diasTotales, diasTranscurridos } = props.vigencia;

    return diasTotales && diasTranscurridos !== null ? Math.round((diasTranscurridos / diasTotales) * 100) : null;
});

const tituloVigencia = computed(() => {
    const { estado, hasta } = props.vigencia;

    if (estado === 'renovada') {
        return 'Sustituida por su renovación';
    }

    if (hasta === null) {
        return 'Nadie ha dicho hasta cuándo prueba';
    }

    return estado === 'caducada' ? `Caducó el ${fechaLegible(hasta)}` : `Caduca el ${fechaLegible(hasta)}`;
});

const renovable = computed(() => props.puede.gestionar && props.vigencia.estado !== 'renovada');

/* --- Lo que falta: lo que un auditor va a preguntar, y sólo cuando falta --- */
const pendientes = computed(() => {
    if (props.vigencia.estado === 'renovada') {
        return [];
    }

    return [
        ...(props.evidencia.responsable === null ? ['Responsable'] : []),
        ...(props.vigencia.estado === 'sin_caducidad' ? ['Caducidad o renovación'] : []),
    ];
});

/* --- Qué prueba, agrupado por marco --- */
const grupos = computed(() => {
    const porMarco = new Map<string, Vinculo[]>();

    for (const vinculo of props.vinculos) {
        const clave = vinculo.marco ?? 'Sin marco';
        porMarco.set(clave, [...(porMarco.get(clave) ?? []), vinculo]);
    }

    return [...porMarco.entries()].map(([codigo, vinculos]) => ({
        codigo,
        nombre: props.marcos.find((marco) => marco.codigo === codigo)?.nombre ?? codigo,
        vinculos,
        sistemas: [...new Set(vinculos.map((vinculo) => vinculo.sistema))],
    }));
});

const sinIniciar = computed(() => props.vinculos.filter((vinculo) => vinculo.estado === 'no_iniciado').length);

/* --- Vincular requisitos, de varios en varios --- */
const abierto = ref(false);
const cargando = ref(false);
const vincular = useForm({ implantaciones: [] as string[], nota: '' });

function abrir(): void {
    abierto.value = true;
    cargando.value = true;

    router.reload({
        only: ['implantacionesDisponibles'],
        onFinish: () => (cargando.value = false),
    });
}

function confirmar(): void {
    vincular.post(`/evidencias/${props.evidencia.id}/requisitos`, {
        preserveScroll: true,
        onSuccess: () => {
            vincular.reset();
            abierto.value = false;
        },
    });
}

function quitar(vinculo: Vinculo): void {
    router.delete(`/implantaciones/${vinculo.implantacionId}/evidencias/${props.evidencia.id}`, {
        preserveScroll: true,
    });
}

/*
 * --- La huella ---
 *
 * Entera, no un prefijo: es lo que se contrasta con el fichero que se le
 * entrega al auditor, y para eso hay que poder copiarla completa. Se pinta en
 * bloques de dieciséis porque sesenta y cuatro caracteres seguidos no se
 * comparan a ojo.
 */
const bloquesHuella = computed(() => props.evidencia.hash_sha256?.match(/.{1,16}/g) ?? []);

const enlace = computed(() => {
    if (!props.evidencia.url_externa) {
        return null;
    }

    try {
        const url = new URL(props.evidencia.url_externa);

        return { dominio: url.host, ruta: `${url.pathname}${url.search}` };
    } catch {
        return { dominio: props.evidencia.url_externa, ruta: '' };
    }
});

const copiado = ref<'huella' | 'enlace' | null>(null);

async function copiar(que: 'huella' | 'enlace', texto: string | null): Promise<void> {
    if (!texto) {
        return;
    }

    await navigator.clipboard.writeText(texto);
    copiado.value = que;
    setTimeout(() => (copiado.value = null), 2000);
}
</script>

<template>
    <AppLayout :titulo="evidencia.titulo">
        <CabeceraPagina :titulo="evidencia.titulo" :descripcion="evidencia.descripcion">
            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                <span class="inline-flex h-5 items-center rounded-full bg-muted px-2 text-xs font-medium text-secondary-foreground">
                    {{ evidencia.tipoEtiqueta }}
                </span>
                <CeldaBadge
                    :valor="{ valor: vigencia.estado, etiqueta: vigencia.etiqueta, tono: vigencia.tono, icono: vigencia.icono }"
                />
                <span>Obtenida el {{ fechaLegible(evidencia.fecha_obtencion) }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ evidencia.esFichero ? `Fichero ${formato ?? ''}`.trim() : 'Enlace externo' }}</span>
            </div>

            <template #acciones>
                <Button v-if="puede.gestionar" as-child variant="outline">
                    <Link :href="`/evidencias/${evidencia.id}/editar`">
                        <PencilLineIcon />
                        Editar
                    </Link>
                </Button>
                <Button v-if="evidencia.esFichero" as-child>
                    <a :href="`/evidencias/${evidencia.id}/descargar`">
                        <DownloadIcon />
                        Descargar
                    </a>
                </Button>
                <Button v-else-if="evidencia.url_externa" as-child>
                    <a :href="evidencia.url_externa" target="_blank" rel="noopener">
                        Abrir el enlace
                        <ExternalLinkIcon />
                    </a>
                </Button>
            </template>
        </CabeceraPagina>

        <motion.div :variants="variantesEntrada" initial="oculto" animate="visible" class="space-y-6">
            <Aviso v-if="renovadaPor" titulo="Renovada">
                La sustituye
                <Link :href="`/evidencias/${renovadaPor.id}`" class="font-medium underline underline-offset-4">
                    «{{ renovadaPor.titulo }}»</Link>,
                obtenida el {{ fechaLegible(renovadaPor.fecha_obtencion) }}. Ésta se conserva porque probó lo que
                probó durante su periodo, pero ya no avisa al caducar.
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
                            v-if="puede.gestionar"
                            :href="`/evidencias/${evidencia.id}/editar`"
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
                <p class="text-xs text-muted-foreground">El auditor pregunta quién la mantiene y hasta cuándo vale.</p>
            </section>

            <!--
                Lo que el auditor mira, antes que el detalle. `gap-px` sobre el
                fondo del borde dibuja el divisor en las dos disposiciones: al
                partirse en móvil, `divide-x` lo perdía.
            -->
            <section
                aria-label="Resumen"
                class="grid gap-px overflow-hidden rounded-xl bg-border ring-1 ring-foreground/10 md:grid-cols-2"
            >
                <div class="flex flex-col gap-4 bg-card p-6">
                    <p class="text-[13px] font-medium text-muted-foreground">Cuenta en</p>
                    <p class="flex flex-wrap items-baseline gap-x-3">
                        <Cifra class="text-4xl leading-none font-bold tracking-[-0.02em]" :valor="vinculos.length" />
                        <span class="text-base font-semibold">
                            {{ vinculos.length === 1 ? 'requisito' : 'requisitos' }} de
                            <span class="cifra">{{ marcosConPrueba }}</span>
                            {{ marcosConPrueba === 1 ? 'marco' : 'marcos' }}
                        </span>
                    </p>

                    <ul v-if="marcos.length > 0" class="space-y-2">
                        <li v-for="marco in marcos" :key="marco.codigo" class="flex items-center gap-3">
                            <span
                                class="cifra inline-flex h-5 w-20 shrink-0 items-center justify-center truncate rounded-full px-2 text-xs"
                                :class="marco.requisitos > 0 ? 'bg-muted text-secondary-foreground' : 'border border-dashed text-muted-foreground'"
                                :title="marco.nombre"
                            >
                                {{ marco.codigo }}
                            </span>
                            <template v-if="marco.requisitos > 0">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                                    <div
                                        class="h-full rounded-full bg-primary"
                                        :style="{ width: `${(marco.requisitos / maximo) * 100}%` }"
                                    />
                                </div>
                                <span class="sr-only">{{ marco.nombre }}:</span>
                                <span class="cifra w-6 shrink-0 text-right text-[13px]">{{ marco.requisitos }}</span>
                            </template>
                            <span v-else class="flex-1 text-[13px] text-muted-foreground">
                                No prueba nada de {{ marco.nombre }} todavía.
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="flex flex-col gap-4 bg-card p-6">
                    <p class="text-[13px] font-medium text-muted-foreground">Vigencia</p>
                    <div>
                        <p class="text-base font-semibold text-balance">{{ tituloVigencia }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            <template v-if="vigencia.estado === 'renovada'">
                                Cuenta como prueba histórica de lo que cubría hasta entonces.
                            </template>
                            <template v-else-if="vigencia.hasta">
                                <span :class="vigencia.estado !== 'vigente' && tono(vigencia.tono).texto">
                                    {{ distanciaLegible(vigencia.hasta) }}
                                </span>
                                <template v-if="periodicidad"> · renovación: {{ periodicidad }}</template>
                            </template>
                            <template v-else>
                                Sin fecha de caducidad ni periodicidad. No vale para siempre: es que no consta.
                            </template>
                        </p>
                    </div>

                    <div v-if="avance !== null && vigencia.estado !== 'renovada'" class="space-y-1.5">
                        <div
                            role="img"
                            :aria-label="`Han pasado ${vigencia.diasTranscurridos} de ${vigencia.diasTotales} días de vigencia`"
                            class="h-2 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full"
                                :class="vigencia.estado === 'vigente' ? 'bg-primary' : tono(vigencia.tono).relleno"
                                :style="{ width: `${avance}%` }"
                            />
                        </div>
                        <div class="flex justify-between gap-3 text-xs text-muted-foreground">
                            <span>{{ fechaLegible(vigencia.desde) }} · obtenida</span>
                            <span class="hidden sm:inline">
                                Hoy · <span class="cifra">{{ vigencia.diasTranscurridos }}</span> de
                                <span class="cifra">{{ vigencia.diasTotales }}</span> días
                            </span>
                            <span>{{ fechaLegible(vigencia.hasta) }}</span>
                        </div>
                    </div>

                    <div v-if="renovable" class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1">
                        <Button as-child variant="outline" size="sm">
                            <Link :href="`/evidencias/${evidencia.id}/renovar`">
                                <RepeatIcon />
                                Registrar la renovación
                            </Link>
                        </Button>
                        <span class="text-xs text-muted-foreground">
                            Una evidencia nueva que prueba lo mismo que ésta.
                        </span>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start">
                <Card class="gap-0 pb-2">
                    <CardHeader class="pb-4">
                        <CardTitle>Qué prueba</CardTitle>
                        <CardDescription>Los requisitos que esta evidencia demuestra, agrupados por marco.</CardDescription>
                        <CardAction v-if="puede.vincular && vinculos.length > 0">
                            <Button variant="outline" @click="abrir">
                                <PlusIcon />
                                Vincular requisitos
                            </Button>
                        </CardAction>
                    </CardHeader>

                    <CardContent v-if="vinculos.length === 0">
                        <EstadoVacio
                            :icono="PaperclipIcon"
                            titulo="Todavía no prueba nada"
                            descripcion="Una evidencia sin vincular no cuenta en ningún marco."
                        />
                        <div v-if="puede.vincular" class="flex justify-center pb-4">
                            <Button variant="outline" @click="abrir">
                                <PlusIcon />
                                Vincular requisitos
                            </Button>
                        </div>
                    </CardContent>

                    <template v-else>
                        <section v-for="grupo in grupos" :key="grupo.codigo" :aria-label="grupo.nombre">
                            <header class="flex flex-wrap items-center gap-2 border-y bg-superficie px-6 py-2.5">
                                <span class="cifra inline-flex h-5 items-center rounded-full bg-card px-2 text-xs ring-1 ring-border">
                                    {{ grupo.codigo }}
                                </span>
                                <h3 class="text-[13px] font-medium">{{ grupo.nombre }}</h3>
                                <span class="cifra ml-auto text-xs text-muted-foreground">
                                    {{ grupo.vinculos.length }} · {{ grupo.sistemas.join(', ') }}
                                </span>
                            </header>

                            <ul class="divide-y">
                                <li
                                    v-for="vinculo in grupo.vinculos"
                                    :key="vinculo.implantacionId"
                                    class="group/fila grid grid-cols-[6rem_minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 px-6 py-3.5"
                                >
                                    <Link
                                        :href="`/implantaciones/${vinculo.implantacionId}`"
                                        class="cifra text-sm font-medium text-primary underline-offset-4 hover:underline"
                                    >
                                        {{ vinculo.codigo }}
                                    </Link>
                                    <span class="min-w-0 text-sm font-medium">
                                        {{ vinculo.titulo }}
                                        <span v-if="grupo.sistemas.length > 1" class="cifra ml-1 text-xs font-normal text-muted-foreground">
                                            {{ vinculo.sistema }}
                                        </span>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <CeldaBadge
                                            :valor="{
                                                valor: vinculo.estado,
                                                etiqueta: vinculo.estadoEtiqueta,
                                                tono: vinculo.estado,
                                                icono: vinculo.estadoIcono,
                                            }"
                                        />
                                        <Button
                                            v-if="vinculo.editable"
                                            variant="ghost"
                                            size="sm"
                                            class="text-muted-foreground"
                                            :aria-label="`Quitar el vínculo con ${vinculo.codigo}`"
                                            @click="quitar(vinculo)"
                                        >
                                            Quitar
                                        </Button>
                                    </span>
                                    <p
                                        v-if="vinculo.nota"
                                        class="col-span-2 col-start-2 border-l-2 pl-2.5 text-[13px] text-muted-foreground"
                                    >
                                        {{ vinculo.nota }}
                                    </p>
                                </li>
                            </ul>
                        </section>

                        <p
                            v-if="sinIniciar > 0"
                            class="mx-6 mt-2 mb-4 flex items-start gap-2.5 rounded-lg bg-superficie px-3.5 py-3 text-[13px] text-secondary-foreground"
                        >
                            <InfoIcon class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <span>
                                <template v-if="sinIniciar === vinculos.length">
                                    {{ sinIniciar === 1 ? 'El requisito sigue' : `Los ${sinIniciar} requisitos siguen` }}
                                </template>
                                <template v-else>
                                    <span class="cifra">{{ sinIniciar }}</span> de <span class="cifra">{{ vinculos.length }}</span>
                                    siguen
                                </template>
                                en <strong class="font-semibold">No iniciado</strong> aunque ya tienen evidencia. El estado
                                se cambia en la ficha de cada implantación.
                            </span>
                        </p>
                    </template>
                </Card>

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Ficha</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                                <dt class="text-muted-foreground">Tipo</dt>
                                <dd class="text-right">{{ evidencia.tipoEtiqueta }}</dd>

                                <dt class="text-muted-foreground">Obtenida</dt>
                                <dd class="text-right">{{ fechaLegible(evidencia.fecha_obtencion) }}</dd>

                                <dt class="text-muted-foreground">Renovación</dt>
                                <dd class="text-right" :class="!periodicidad && 'text-muted-foreground'">
                                    {{ periodicidad ?? 'No se renueva' }}
                                </dd>

                                <dt class="text-muted-foreground">Caducidad</dt>
                                <dd class="text-right" :class="!evidencia.fecha_caducidad && 'text-muted-foreground'">
                                    {{ evidencia.fecha_caducidad ? fechaLegible(evidencia.fecha_caducidad) : 'Sin fecha' }}
                                </dd>

                                <dt class="text-muted-foreground">Responsable</dt>
                                <dd class="text-right">
                                    <template v-if="evidencia.responsable">{{ evidencia.responsable }}</template>
                                    <Link
                                        v-else-if="puede.gestionar"
                                        :href="`/evidencias/${evidencia.id}/editar`"
                                        class="text-primary underline-offset-4 hover:underline"
                                    >
                                        Asignar
                                    </Link>
                                    <span v-else class="text-muted-foreground">Sin asignar</span>
                                </dd>

                                <template v-if="renuevaA">
                                    <dt class="text-muted-foreground">Renueva a</dt>
                                    <dd class="text-right">
                                        <Link
                                            :href="`/evidencias/${renuevaA.id}`"
                                            class="text-primary underline-offset-4 hover:underline"
                                        >
                                            La del {{ fechaLegible(renuevaA.fecha_obtencion) }}
                                        </Link>
                                    </dd>
                                </template>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card v-if="evidencia.esFichero">
                        <CardHeader>
                            <CardTitle>Integridad</CardTitle>
                            <CardDescription>
                                SHA-256 calculado al recibir el fichero. Si coincide, el fichero es el que se obtuvo
                                aquel día y no otro.
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="space-y-3.5">
                            <div class="flex items-center gap-2.5">
                                <span class="grid size-9 shrink-0 place-items-center rounded-md bg-muted">
                                    <FileIcon class="size-4.5 text-secondary-foreground" aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium break-all">{{ evidencia.nombre_fichero }}</p>
                                    <p class="cifra text-xs text-muted-foreground">
                                        {{ [formato, tamano].filter(Boolean).join(' · ') }}
                                    </p>
                                </div>
                            </div>

                            <template v-if="evidencia.hash_sha256">
                                <p
                                    class="cifra grid grid-cols-2 gap-x-3 rounded-lg bg-superficie p-3 text-xs leading-[18px] text-secondary-foreground"
                                    :aria-label="`Huella SHA-256: ${evidencia.hash_sha256}`"
                                >
                                    <span v-for="(bloque, indice) in bloquesHuella" :key="indice">{{ bloque }}</span>
                                </p>
                                <Button variant="outline" size="sm" @click="copiar('huella', evidencia.hash_sha256)">
                                    <CheckIcon v-if="copiado === 'huella'" />
                                    <CopyIcon v-else />
                                    {{ copiado === 'huella' ? 'Copiada' : 'Copiar huella' }}
                                </Button>
                            </template>
                        </CardContent>
                    </Card>

                    <Card v-else-if="enlace">
                        <CardHeader>
                            <CardTitle>Origen</CardTitle>
                            <CardDescription>
                                Enlace externo. No hay fichero guardado ni huella que contrastar: si lo que hay detrás
                                cambia, la evidencia deja de probar.
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="space-y-3.5">
                            <p class="rounded-lg bg-superficie p-3">
                                <span class="cifra block text-[13px] font-medium break-all">{{ enlace.dominio }}</span>
                                <span v-if="enlace.ruta" class="cifra block text-xs break-all text-muted-foreground">
                                    {{ enlace.ruta }}
                                </span>
                            </p>
                            <Button variant="outline" size="sm" @click="copiar('enlace', evidencia.url_externa)">
                                <CheckIcon v-if="copiado === 'enlace'" />
                                <CopyIcon v-else />
                                {{ copiado === 'enlace' ? 'Copiado' : 'Copiar enlace' }}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </motion.div>

        <Dialog v-model:open="abierto">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Vincular requisitos</DialogTitle>
                    <DialogDescription>
                        De cualquier marco y de varios a la vez: la misma prueba cuenta en todos donde aplique.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <p v-if="cargando" class="text-sm text-muted-foreground">Cargando los requisitos…</p>

                    <template v-else>
                        <CampoSeleccionMultiple
                            v-model="vincular.implantaciones"
                            nombre="implantaciones"
                            etiqueta="Requisitos"
                            :opciones="implantacionesDisponibles ?? []"
                            :error="vincular.errors.implantaciones"
                            vacio="No queda ningún requisito por vincular que puedas tocar."
                            requerido
                        />

                        <CampoTextarea
                            v-model="vincular.nota"
                            nombre="nota"
                            etiqueta="Nota"
                            :filas="2"
                            :error="vincular.errors.nota"
                            ayuda="Qué parte de cada requisito prueba. Se guarda en cada vínculo y se edita desde la ficha del requisito."
                        />
                    </template>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abierto = false">Cancelar</Button>
                    <Button :disabled="vincular.processing || vincular.implantaciones.length === 0" @click="confirmar">
                        {{ vincular.processing ? 'Vinculando…' : 'Vincular' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
