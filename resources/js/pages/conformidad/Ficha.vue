<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckIcon } from '@lucide/vue';
import { computed, ref } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Transicion {
    id: number;
    anterior: string | null;
    nuevo: string;
    tono: string;
    icono: string;
    usuario: string | null;
    fecha: string;
    nota: string | null;
}

interface Conformidad {
    id: number;
    estado: string;
    estadoEtiqueta: string;
    tono: string;
    icono: string;
    categoria: string;
    vigenteHasta: string | null;
    caducada: boolean;
    via: string;
    auditoria: { id: number; codigo: string; fechaCierre: string | null };
    version: {
        id: number;
        numero: number | null;
        documentoId: number;
        firmadaPor: string | null;
        firmadaEn: string | null;
        huella: string | null;
    } | null;
    fechaDeclaracion: string | null;
    fechaDeclaracionIso: string | null;
    distintivo: {
        url: string;
        publicadoEn: string | null;
        evidencia: { id: number; titulo: string } | null;
    } | null;
    iniciadaEn: string;
    puedeRetirarse: boolean;
    historial: Transicion[];
}

/**
 * La conformidad con el ENS de un sistema: § 4.17.
 *
 * **Los tres pasos de categoría básica, a la vista y en orden**: la
 * autoevaluación cerrada, la Declaración firmada y el distintivo publicado. Cada
 * paso dice qué falta con las mismas frases con las que el dominio se negaría
 * (`RequisitosDeDeclaracion`), para que nadie descubra el bloqueo al pulsar.
 *
 * Una renovación se prepara con la anterior todavía en vigor, así que la
 * declaración vigente va en su propia tarjeta y los pasos siguen a la que está
 * en preparación.
 */
const props = defineProps<{
    sistema: {
        id: number;
        codigo: string;
        nombre: string;
        marco: string;
        categoria: string | null;
        categoriaTono: string | null;
    };
    comprobacion: {
        bloqueos: string[];
        sePuedeIniciar: boolean;
        autoevaluacion: { id: number; codigo: string; fechaCierre: string | null } | null;
    };
    enPreparacion: Conformidad | null;
    vigente: Conformidad | null;
    anteriores: Conformidad[];
    documento: { id: number; codigo: string; titulo: string } | null;
    versionesFirmadas: Opcion[];
    evidencias: Opcion[];
    puedeGestionar: boolean;
    puedeGenerar: boolean;
}>();

const pagina = usePage();
const errores = computed(() => (pagina.props.errors ?? {}) as Record<string, string>);

/* --- Paso 2: iniciar y declarar --- */

const enviando = ref(false);

function iniciar(): void {
    enviando.value = true;
    router.post(`/conformidad/sistemas/${props.sistema.id}`, {}, {
        preserveScroll: true,
        onFinish: () => (enviando.value = false),
    });
}

function prepararDocumento(): void {
    enviando.value = true;
    router.post(`/conformidad/sistemas/${props.sistema.id}/documento`, {}, {
        onFinish: () => (enviando.value = false),
    });
}

const declaracion = useForm({ documento_version_id: '' });

function declarar(): void {
    if (!props.enPreparacion) {
        return;
    }

    declaracion.post(`/conformidad/${props.enPreparacion.id}/declarar`, { preserveScroll: true });
}

/* --- Paso 3: el distintivo --- */

/** La declarada que espera su distintivo: la vigente, si todavía no lo tiene. */
const sinDistintivo = computed(() => (props.vigente?.estado === 'declarada' ? props.vigente : null));

const distintivo = useForm({
    distintivo_url: '',
    distintivo_publicado_en: new Date().toISOString().slice(0, 10),
    distintivo_evidencia_id: '',
});

function registrarDistintivo(): void {
    if (!sinDistintivo.value) {
        return;
    }

    distintivo
        .transform((datos) => ({
            ...datos,
            distintivo_evidencia_id: datos.distintivo_evidencia_id === '' ? null : datos.distintivo_evidencia_id,
        }))
        .post(`/conformidad/${sinDistintivo.value.id}/distintivo`, { preserveScroll: true });
}

/* --- Retirar --- */

const aRetirar = ref<Conformidad | null>(null);
const retirada = useForm({ motivo: '' });

function abrirRetirada(conformidad: Conformidad): void {
    retirada.reset();
    retirada.clearErrors();
    aRetirar.value = conformidad;
}

function retirar(): void {
    if (!aRetirar.value) {
        return;
    }

    retirada.post(`/conformidad/${aRetirar.value.id}/retirar`, {
        preserveScroll: true,
        onSuccess: () => (aRetirar.value = null),
    });
}

/* --- Los tres pasos --- */

/** En qué punto está la declaración que se está siguiendo. */
const actual = computed(() => props.enPreparacion ?? props.vigente);

const pasos = computed(() => [
    {
        numero: 1,
        titulo: 'Autoevaluación cerrada',
        hecho: actual.value !== null || props.comprobacion.autoevaluacion !== null,
    },
    {
        numero: 2,
        titulo: 'Declaración firmada',
        hecho: actual.value?.estado === 'declarada' || actual.value?.estado === 'publicada',
    },
    {
        numero: 3,
        titulo: 'Distintivo publicado',
        hecho: actual.value?.estado === 'publicada',
    },
]);

/** Todas las de este sistema, de la más reciente a la más antigua, con su histórico. */
const todas = computed(() =>
    [props.enPreparacion, props.vigente, ...props.anteriores].filter((c): c is Conformidad => c !== null),
);

const abiertoRetirar = computed({
    get: () => aRetirar.value !== null,
    set: (valor: boolean) => {
        if (!valor) {
            aRetirar.value = null;
        }
    },
});
</script>

<template>
    <AppLayout :titulo="`Conformidad · ${sistema.codigo}`">
        <CabeceraPagina :titulo="sistema.nombre" :codigo="sistema.codigo" :descripcion="sistema.marco">
            <template #acciones>
                <Button as-child variant="outline">
                    <Link :href="`/sistemas/${sistema.id}`">Ver el sistema</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                v-if="sistema.categoria"
                :valor="{
                    valor: sistema.categoriaTono ?? '',
                    etiqueta: `Categoría ${sistema.categoria}`,
                    tono: sistema.categoriaTono ?? 'no_iniciado',
                    icono: null,
                }"
            />
            <CeldaBadge
                v-if="vigente"
                :valor="{ valor: vigente.estado, etiqueta: vigente.estadoEtiqueta, tono: vigente.tono, icono: vigente.icono }"
            />
        </div>

        <Aviso v-if="errores.conformidad" tono="error" titulo="No se ha podido iniciar">
            {{ errores.conformidad }}
        </Aviso>

        <Aviso v-if="vigente?.caducada" tono="error" titulo="La declaración ha caducado">
            Venció el {{ vigente.vigenteHasta }}. El sistema ya no puede enseñar el distintivo: inicia la
            renovación con una autoevaluación nueva.
        </Aviso>

        <ol class="grid gap-2 sm:grid-cols-3" aria-label="Pasos de la conformidad en categoría básica">
            <li
                v-for="paso in pasos"
                :key="paso.numero"
                class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm"
                :class="paso.hecho ? 'border-estado-implantado/40 bg-estado-implantado-suave' : 'border-border'"
            >
                <span
                    class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                    :class="paso.hecho ? 'bg-estado-implantado text-background' : 'bg-muted text-muted-foreground'"
                    aria-hidden="true"
                >
                    <CheckIcon v-if="paso.hecho" class="size-3.5" />
                    <template v-else>{{ paso.numero }}</template>
                </span>
                <span :class="paso.hecho ? 'font-medium' : 'text-muted-foreground'">
                    {{ paso.titulo }}
                    <span class="sr-only">{{ paso.hecho ? '(hecho)' : '(pendiente)' }}</span>
                </span>
            </li>
        </ol>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <!-- Iniciar: no hay nada en preparación -->
                <Card v-if="!enPreparacion">
                    <CardHeader>
                        <CardTitle>{{ vigente ? 'Renovar la declaración' : 'Iniciar la declaración' }}</CardTitle>
                        <CardDescription>
                            La declaración se apoya en la última autoevaluación cerrada del sistema y
                            congela su categoría de hoy. Revalorar el sistema después no cambia lo declarado.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <p v-if="comprobacion.autoevaluacion">
                            Autoevaluación:
                            <Link
                                :href="`/auditorias/${comprobacion.autoevaluacion.id}`"
                                class="cifra underline-offset-4 hover:underline"
                            >
                                {{ comprobacion.autoevaluacion.codigo }}
                            </Link>
                            <span class="text-muted-foreground">
                                · cerrada el {{ comprobacion.autoevaluacion.fechaCierre }}
                            </span>
                        </p>

                        <Aviso v-if="comprobacion.bloqueos.length > 0" tono="info" titulo="Todavía no se puede declarar">
                            <ul class="list-disc space-y-1 pl-4">
                                <li v-for="bloqueo in comprobacion.bloqueos" :key="bloqueo">{{ bloqueo }}</li>
                            </ul>
                        </Aviso>

                        <Button
                            v-if="puedeGestionar"
                            :disabled="!comprobacion.sePuedeIniciar || enviando"
                            @click="iniciar"
                        >
                            {{ vigente ? 'Iniciar la renovación' : 'Iniciar la declaración' }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Declarar: hay una en preparación -->
                <Card v-else>
                    <CardHeader>
                        <CardTitle>Declaración de Conformidad</CardTitle>
                        <CardDescription>
                            En preparación desde el {{ enPreparacion.iniciadaEn }}, sobre la autoevaluación
                            {{ enPreparacion.auditoria.codigo }} y en categoría {{ enPreparacion.categoria }}.
                            La firma se da en el documento, con el permiso de aprobar documentos.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <ol class="list-decimal space-y-1 pl-4 text-muted-foreground">
                            <li>Genera la Declaración y revisa el borrador.</li>
                            <li>Mándala a revisión y apruébala: aprobar es lo que la firma y la emite.</li>
                            <li>Vuelve aquí y elige la versión firmada.</li>
                        </ol>

                        <div class="flex flex-wrap gap-2">
                            <Button v-if="documento" as-child variant="outline">
                                <Link :href="`/documentos/${documento.id}`">Abrir {{ documento.codigo }}</Link>
                            </Button>
                            <Button
                                v-else-if="puedeGestionar && puedeGenerar"
                                variant="outline"
                                :disabled="enviando"
                                @click="prepararDocumento"
                            >
                                Preparar la Declaración
                            </Button>
                        </div>

                        <template v-if="puedeGestionar">
                            <p v-if="versionesFirmadas.length === 0" class="text-muted-foreground">
                                Todavía no hay ninguna versión firmada después de iniciar esta declaración.
                            </p>
                            <div v-else class="space-y-3">
                                <CampoSelect
                                    nombre="documento_version_id"
                                    etiqueta="Versión firmada"
                                    :opciones="versionesFirmadas"
                                    :error="declaracion.errors.documento_version_id"
                                    requerido
                                    @update:model-value="(valor?: string) => (declaracion.documento_version_id = valor ?? '')"
                                />
                                <Button
                                    :disabled="declaracion.processing || declaracion.documento_version_id === ''"
                                    @click="declarar"
                                >
                                    Registrar la declaración
                                </Button>
                            </div>
                        </template>

                        <Button
                            v-if="puedeGestionar"
                            variant="ghost"
                            size="sm"
                            @click="abrirRetirada(enPreparacion)"
                        >
                            Abandonar esta declaración
                        </Button>
                    </CardContent>
                </Card>

                <!-- El distintivo -->
                <Card v-if="sinDistintivo && puedeGestionar">
                    <CardHeader>
                        <CardTitle>Publicar el distintivo</CardTitle>
                        <CardDescription>
                            El distintivo lo publica la organización en su web junto a la Declaración
                            (CCN-STIC 809). Aquí se registra dónde y desde cuándo, con una captura como
                            evidencia si la hay.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <CampoTexto
                            nombre="distintivo_url"
                            etiqueta="Dirección de la página"
                            tipo="url"
                            placeholder="https://"
                            :error="distintivo.errors.distintivo_url"
                            requerido
                            @input="distintivo.distintivo_url = ($event.target as HTMLInputElement).value"
                        />
                        <CampoTexto
                            nombre="distintivo_publicado_en"
                            etiqueta="Publicado el"
                            tipo="date"
                            :valor-inicial="distintivo.distintivo_publicado_en"
                            :error="distintivo.errors.distintivo_publicado_en"
                            requerido
                            @input="distintivo.distintivo_publicado_en = ($event.target as HTMLInputElement).value"
                        />
                        <CampoSelect
                            nombre="distintivo_evidencia_id"
                            etiqueta="Evidencia"
                            ayuda="Opcional. La captura de la página con el distintivo puesto."
                            :opciones="evidencias"
                            :error="distintivo.errors.distintivo_evidencia_id"
                            @update:model-value="(valor?: string) => (distintivo.distintivo_evidencia_id = valor ?? '')"
                        />
                        <Button :disabled="distintivo.processing" @click="registrarDistintivo">
                            Registrar el distintivo
                        </Button>
                    </CardContent>
                </Card>

                <!-- Históricos -->
                <Card v-for="conformidad in todas" :key="conformidad.id">
                    <CardHeader>
                        <CardTitle class="flex flex-wrap items-center gap-2">
                            <span>Histórico</span>
                            <CeldaBadge
                                :valor="{
                                    valor: conformidad.estado,
                                    etiqueta: conformidad.estadoEtiqueta,
                                    tono: conformidad.tono,
                                    icono: conformidad.icono,
                                }"
                            />
                        </CardTitle>
                        <CardDescription>
                            Iniciada el {{ conformidad.iniciadaEn }} sobre {{ conformidad.auditoria.codigo }}.
                            Desde cuándo está en cada estado, y por qué.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y divide-border">
                            <li v-for="paso in conformidad.historial" :key="paso.id" class="space-y-1 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <CeldaBadge
                                        :valor="{ valor: paso.nuevo, etiqueta: paso.nuevo, tono: paso.tono, icono: paso.icono }"
                                    />
                                    <span class="text-xs text-muted-foreground">
                                        {{ paso.fecha }}
                                        <template v-if="paso.usuario"> · {{ paso.usuario }}</template>
                                    </span>
                                </div>
                                <p v-if="paso.nota" class="text-sm">{{ paso.nota }}</p>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <div class="h-fit space-y-6">
                <Card v-if="vigente">
                    <CardHeader>
                        <CardTitle>En vigor</CardTitle>
                        <CardDescription>{{ vigente.via }} · categoría {{ vigente.categoria }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Declarada el</dt>
                            <dd>{{ vigente.fechaDeclaracion }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ vigente.caducada ? 'Caducó el' : 'Vigente hasta' }}</dt>
                            <dd :class="vigente.caducada ? 'text-destructive' : ''">{{ vigente.vigenteHasta }}</dd>
                        </div>
                        <div v-if="vigente.version">
                            <dt class="text-muted-foreground">Documento</dt>
                            <dd>
                                <Link
                                    :href="`/documentos/${vigente.version.documentoId}`"
                                    class="underline-offset-4 hover:underline"
                                >
                                    v{{ vigente.version.numero }}
                                </Link>
                                <span class="text-muted-foreground">
                                    · firmada por {{ vigente.version.firmadaPor ?? '—' }}
                                </span>
                            </dd>
                            <dd v-if="vigente.version.huella" class="cifra mt-1 break-all text-xs text-muted-foreground">
                                SHA-256 {{ vigente.version.huella }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Autoevaluación</dt>
                            <dd>
                                <Link
                                    :href="`/auditorias/${vigente.auditoria.id}`"
                                    class="cifra underline-offset-4 hover:underline"
                                >
                                    {{ vigente.auditoria.codigo }}
                                </Link>
                            </dd>
                        </div>
                        <div v-if="vigente.distintivo">
                            <dt class="text-muted-foreground">Distintivo</dt>
                            <dd class="break-all">
                                <a
                                    :href="vigente.distintivo.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="underline-offset-4 hover:underline"
                                >
                                    {{ vigente.distintivo.url }}
                                </a>
                            </dd>
                            <dd class="text-muted-foreground">Desde el {{ vigente.distintivo.publicadoEn }}</dd>
                            <dd v-if="vigente.distintivo.evidencia">
                                <Link
                                    :href="`/evidencias/${vigente.distintivo.evidencia.id}`"
                                    class="underline-offset-4 hover:underline"
                                >
                                    {{ vigente.distintivo.evidencia.titulo }}
                                </Link>
                            </dd>
                        </div>

                        <Button
                            v-if="puedeGestionar && vigente.puedeRetirarse"
                            variant="ghost"
                            size="sm"
                            @click="abrirRetirada(vigente)"
                        >
                            Retirar la declaración
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="abiertoRetirar">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{ aRetirar?.estado === 'en_preparacion' ? 'Abandonar la declaración' : 'Retirar la declaración' }}
                    </DialogTitle>
                    <DialogDescription>
                        No se borra nada: la declaración queda retirada con su motivo en el histórico, y el
                        PDF firmado sigue siendo el que se entregó.
                    </DialogDescription>
                </DialogHeader>

                <CampoTextarea
                    nombre="motivo"
                    etiqueta="Por qué"
                    :filas="4"
                    :error="retirada.errors.motivo"
                    requerido
                    @input="retirada.motivo = ($event.target as HTMLTextAreaElement).value"
                />

                <DialogFooter>
                    <Button variant="outline" @click="aRetirar = null">Cancelar</Button>
                    <Button
                        variant="destructive"
                        :disabled="retirada.processing || retirada.motivo.trim() === ''"
                        @click="retirar"
                    >
                        Retirar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
