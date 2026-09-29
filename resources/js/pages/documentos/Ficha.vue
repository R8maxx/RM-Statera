<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import ServiciosDelPlan from '@/components/continuidad/ServiciosDelPlan.vue';
import BloqueAcuse from '@/components/documento/BloqueAcuse.vue';
import BloqueAprobacion from '@/components/documento/BloqueAprobacion.vue';
import CicloDocumento from '@/components/documento/CicloDocumento.vue';
import CopiarHuella from '@/components/documento/CopiarHuella.vue';
import EsqueletoDocumento from '@/components/documento/EsqueletoDocumento.vue';
import HistorialVersiones, { type Version } from '@/components/documento/HistorialVersiones.vue';
import HuellaRevelada from '@/components/documento/HuellaRevelada.vue';
import MiniaturaPortada from '@/components/documento/MiniaturaPortada.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { distanciaLegible, fechaLegible } from '@/lib/celdas';
import type { Opcion } from '@/lib/formularios';
import { motion } from 'motion-v';
import { Link, router, useForm, usePoll } from '@inertiajs/vue3';
import { DownloadIcon, EyeIcon, FileTextIcon, InfoIcon, PencilIcon, RefreshCwIcon, TypeIcon } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

/**
 * Los dos estados de una versión, que no son lo mismo.
 *
 * `generacion*` es el ciclo de vida del TRABAJO que produce el PDF y es lo que
 * mira el poll; `estado*` es el del DOCUMENTO —borrador, en revisión, aprobado—.
 * Que el PDF se haya generado bien no significa que nadie lo haya firmado.
 */
interface VersionEnCurso extends Version {
    generacion: string;
    generacionEtiqueta: string;
    generacionTono: string;
    enCurso: boolean;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    aprobadaPor: string | null;
    aprobadaEn: string | null;
    notaAprobacion: string | null;
    motivoRechazo: string | null;
    proximaRevision: string | null;
    descargable: boolean;
    emisible: boolean;
    error: string | null;
    recuento: string | null;
}

const props = defineProps<{
    documento: {
        id: number;
        codigo: string;
        titulo: string;
        tipo: string;
        tipoEtiqueta: string;
        clasificacion: string;
        clasificacionEtiqueta: string;
        sistema: string | null;
        sistemaCodigo: string | null;
        marco: string | null;
        responsable: string | null;
        notas: string | null;
        periodicidad_revision_meses: number | null;
        exige_acuse: boolean;
        redactado: boolean;
    };
    versionEnCurso: VersionEnCurso | null;
    versionVigente: VersionEnCurso | null;
    versiones: Version[];
    acuse: {
        total: number;
        acusados: number;
        pendientes: string[];
        lectores: { nombre: string; fecha: string }[];
        yaAcusado: boolean;
    } | null;
    /**
     * Sólo un plan de continuidad los manda (§ 4.11). Nulo -no una lista
     * vacía- es lo que dice «este documento no vincula servicios», que es
     * distinto de «todavía no cubre ninguno».
     */
    serviciosDelPlan: { id: number; codigo: string; nombre: string }[] | null;
    serviciosDisponibles: Opcion[] | null;
    puedeAprobar: boolean;
    puedeRedactar: boolean;
    cuerpoMasNuevoQueElBorrador: boolean;
}>();

const generar = useForm({});

const enCurso = computed(() => props.versionEnCurso?.enCurso ?? false);

/*
 * Mientras el trabajo está vivo hay que preguntarle al servidor, porque el
 * worker corre en otro proceso: no puede mandar un flash ni hay broadcasting en
 * el stack. `defer` tampoco sirve —resuelve en UNA petición de seguimiento y no
 * reintenta—, así que es un poll corto y acotado.
 *
 * `keepAlive: false` lo para con la pestaña en segundo plano, la misma lógica
 * que `Cifra` y la balanza del acceso.
 */
const { start, stop } = usePoll(
    3000,
    { only: ['versionEnCurso', 'versionVigente', 'versiones'] },
    { keepAlive: false, autoStart: false },
);

/**
 * Y se rinde a los dos minutos.
 *
 * Un poll infinito contra una cola atascada es un bucle caliente contra el
 * servidor que nadie mira: pasado ese rato se dice que está tardando y se deja
 * un botón para comprobar a mano.
 */
const LIMITE_MS = 120_000;
const rendido = ref(false);
let desde: number | null = null;
let temporizador: number | null = null;

function pararTodo(): void {
    stop();
    if (temporizador !== null) {
        window.clearTimeout(temporizador);
        temporizador = null;
    }
}

watch(
    enCurso,
    (vivo, anterior) => {
        if (vivo) {
            rendido.value = false;
            desde = Date.now();
            start();
            temporizador = window.setTimeout(() => {
                rendido.value = true;
                stop();
            }, LIMITE_MS);

            return;
        }

        pararTodo();

        // El servidor anuncia lo que hizo —«generación encolada»— y el cliente
        // anuncia lo que vio. El aviso de fin sólo puede salir de aquí.
        if (anterior === true && desde !== null) {
            desde = null;

            /*
             * Si la generación venía de una firma, el borrador ya no está: se
             * numeró y pasó a `versiones`. Ese caso no es «el borrador está
             * listo», es «el documento está entregado», y decirlo mal deja a
             * alguien buscando un borrador que no existe.
             */
            if (props.versionEnCurso === null) {
                toast.success('Documento aprobado y entregado.');
            } else if (props.versionEnCurso.generacion === 'generada') {
                toast.success('El borrador está listo.');
            } else if (props.versionEnCurso.generacion === 'fallida') {
                toast.error('La generación falló.');
            }
        }
    },
    { immediate: true },
);

onBeforeUnmount(pararTodo);

function comprobar(): void {
    rendido.value = false;
    router.reload({ only: ['versionEnCurso', 'versionVigente', 'versiones'] });
}

/**
 * La etiqueta que llevará el borrador al firmarse. La numeración no deja huecos
 * —un rechazo no gasta número—, así que es la última emitida más uno.
 */
const siguiente = computed(() => `v${Math.max(0, ...props.versiones.map((v) => v.numero ?? 0)) + 1}`);

/**
 * Lo que un auditor va a pedir y la ficha no tiene (DESIGN.md §9, «Lo que
 * falta, delante y sólo cuando falta»). Hoy es un dato; la forma es la del
 * activo para que el siguiente entre sin tocar la plantilla.
 */
const pendientes = computed(() => (props.documento.responsable === null ? ['Responsable'] : []));

/*
 * La huella de la vigente se escribe sola **sólo si la vigente cambia con la
 * página abierta**, que es firmar y ver cómo se emite. Quien llega a la ficha
 * escribiendo la URL no acaba de entregar nada y la ve puesta.
 */
const vigenteAlLlegar = props.versionVigente?.id ?? null;
const revelarHuella = computed(
    () => props.versionVigente !== null && props.versionVigente.id !== vigenteAlLlegar,
);

const { variantesEntrada, variantesEscalonado } = useMovimientoReducido();

/* Las tres tarjetas llegan en el orden en que se leen, no de golpe. */
const escalonado = variantesEscalonado(0.05);

const kb = (bytes: number | null | undefined): string =>
    bytes === null || bytes === undefined ? '—' : `${Math.round(bytes / 1024)} kB`;
</script>

<template>
    <AppLayout :titulo="documento.codigo">
        <CabeceraPagina :titulo="documento.titulo" :codigo="documento.codigo" :descripcion="documento.tipoEtiqueta">
            <template #acciones>
                <!--
                    Dos cosas distintas que antes se llamaban casi igual
                    —«Editar documento» y «Editar»—: el texto que sale en el
                    PDF y los datos de la ficha.
                -->
                <Button as-child variant="outline">
                    <Link :href="`/documentos/${documento.id}/cuerpo`">
                        <TypeIcon class="size-4" />
                        Editar el texto
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="`/documentos/${documento.id}/editar`">
                        <PencilIcon class="size-4" />
                        Editar la ficha
                    </Link>
                </Button>
            </template>
        </CabeceraPagina>

        <motion.div class="flex flex-col gap-6" :variants="escalonado" initial="oculto" animate="visible">
            <motion.section
                v-if="pendientes.length > 0"
                :variants="variantesEntrada"
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
                            :href="`/documentos/${documento.id}/editar`"
                            class="inline-flex h-7 items-center rounded-full bg-muted px-2.5 text-[13px] font-medium text-secondary-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            {{ pendiente }}
                        </Link>
                    </li>
                </ul>
            </motion.section>

            <motion.div :variants="variantesEntrada">
                <CicloDocumento
                    :version="versionEnCurso"
                    :siguiente="siguiente"
                    :periodicidad-meses="documento.periodicidad_revision_meses"
                />
            </motion.div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22.5rem] lg:items-start">
                <motion.div :variants="variantesEntrada">
                    <!--
                        Sin borrador, la tarjeta es un estado vacío con la única
                        acción que hay: generarlo. Es el primario de la vista;
                        en cuanto hay borrador, regenerar baja a sutil y manda la
                        acción del bloque de aprobación (DESIGN.md §9, un solo
                        botón de color lleno).
                    -->
                    <Card v-if="!versionEnCurso">
                        <CardContent>
                            <EstadoVacio
                                :icono="FileTextIcon"
                                :titulo="versionVigente ? 'No hay ningún borrador pendiente' : 'Todavía no hay borrador'"
                                :descripcion="
                                    versionVigente
                                        ? `La ${versionVigente.etiqueta} está en vigor y no cambia hasta que se firme la siguiente. Genera un borrador cuando toque revisarla.`
                                        : 'Se genera con lo que haya registrado en ese momento y se puede regenerar cuantas veces haga falta: no se entrega hasta que se firma.'
                                "
                            >
                                <Button
                                    :disabled="generar.processing"
                                    @click="generar.post(`/documentos/${documento.id}/generar`, { preserveScroll: true })"
                                >
                                    <RefreshCwIcon class="size-4" />
                                    Generar el borrador
                                </Button>
                            </EstadoVacio>
                        </CardContent>
                    </Card>

                    <Card v-else>
                        <CardContent class="flex flex-col gap-6">
                            <!--
                                Mientras el worker trabaja se enseña la forma de
                                lo que va a salir, no una rueda. Es la única
                                espera del producto que dura decenas de segundos.
                            -->
                            <div class="flex flex-col gap-6 sm:flex-row">
                                <EsqueletoDocumento v-if="enCurso" class="w-full sm:w-44 sm:shrink-0" :filas="3" />
                                <MiniaturaPortada
                                    v-else
                                    :firmada="versionEnCurso.aprobadaPor !== null"
                                    :en-revision="versionEnCurso.estado === 'en_revision'"
                                />

                                <div class="flex min-w-0 flex-1 flex-col gap-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <h2 class="text-base font-semibold tracking-[-0.01em]">
                                            Borrador de la <span class="cifra">{{ siguiente }}</span>
                                        </h2>
                                        <!--
                                            Mientras se genera manda el estado
                                            del trabajo; con el PDF listo, el del
                                            documento, que es lo que le importa a
                                            quien lo firma.
                                        -->
                                        <CeldaBadge
                                            v-if="enCurso || versionEnCurso.generacion === 'fallida'"
                                            :valor="{
                                                valor: versionEnCurso.generacion,
                                                etiqueta: versionEnCurso.generacionEtiqueta,
                                                tono: versionEnCurso.generacionTono,
                                            }"
                                        />
                                        <CeldaBadge
                                            v-else
                                            :valor="{
                                                valor: versionEnCurso.estado,
                                                etiqueta: versionEnCurso.estadoEtiqueta,
                                                tono: versionEnCurso.estadoTono,
                                                icono: versionEnCurso.estadoIcono,
                                            }"
                                        />
                                    </div>

                                    <dl
                                        v-if="versionEnCurso.quien || versionEnCurso.descargable || versionEnCurso.motivo"
                                        class="grid grid-cols-[8rem_minmax(0,1fr)] gap-x-4 gap-y-2.5 text-sm"
                                    >
                                        <template v-if="versionEnCurso.quien">
                                            <dt class="text-[13px] font-medium text-muted-foreground">Generado por</dt>
                                            <dd>{{ versionEnCurso.quien }}</dd>
                                        </template>
                                        <template v-if="versionEnCurso.recuento">
                                            <dt class="text-[13px] font-medium text-muted-foreground">Contenido</dt>
                                            <dd>{{ versionEnCurso.recuento }}</dd>
                                        </template>
                                        <template v-if="versionEnCurso.descargable">
                                            <dt class="text-[13px] font-medium text-muted-foreground">Fichero</dt>
                                            <dd>PDF/A-3b · <span class="cifra">{{ kb(versionEnCurso.tamano) }}</span></dd>
                                        </template>
                                        <template v-if="versionEnCurso.motivo">
                                            <dt class="text-[13px] font-medium text-muted-foreground">Motivo</dt>
                                            <dd class="border-l-2 border-border pl-3 text-secondary-foreground">
                                                {{ versionEnCurso.motivo }}
                                            </dd>
                                        </template>
                                    </dl>

                                    <p v-if="versionEnCurso.error" class="text-sm text-destructive">
                                        {{ versionEnCurso.error }}
                                    </p>

                                    <p v-if="rendido" class="text-sm text-muted-foreground">
                                        Está tardando más de lo normal. Puede que la cola no esté procesando trabajos.
                                    </p>

                                    <!--
                                        El PDF en disco es anterior a la última
                                        edición del documento. Sin decirlo,
                                        alguien edita, descarga, no ve su texto y
                                        concluye que el módulo no funciona.
                                    -->
                                    <p v-if="cuerpoMasNuevoQueElBorrador" class="text-sm text-estado-en-progreso">
                                        El borrador es anterior a la última edición del documento. Regenéralo para verla.
                                    </p>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <template v-if="versionEnCurso.descargable">
                                            <Button as-child variant="outline">
                                                <a
                                                    :href="`/documentos/${documento.id}/versiones/${versionEnCurso.id}/ver`"
                                                    target="_blank"
                                                    rel="noopener"
                                                >
                                                    <EyeIcon class="size-4" />
                                                    Ver el PDF
                                                </a>
                                            </Button>
                                            <Button as-child variant="ghost">
                                                <a :href="`/documentos/${documento.id}/versiones/${versionEnCurso.id}/descargar`">
                                                    <DownloadIcon class="size-4" />
                                                    Descargar
                                                </a>
                                            </Button>
                                            <Button as-child variant="ghost">
                                                <a :href="`/documentos/${documento.id}/versiones/${versionEnCurso.id}/word`">
                                                    <FileTextIcon class="size-4" />
                                                    Word
                                                </a>
                                            </Button>
                                        </template>

                                        <Button v-if="rendido" variant="outline" @click="comprobar">Comprobar</Button>

                                        <Button
                                            variant="ghost"
                                            class="ms-auto text-muted-foreground"
                                            :disabled="enCurso || generar.processing"
                                            @click="generar.post(`/documentos/${documento.id}/generar`, { preserveScroll: true })"
                                        >
                                            <RefreshCwIcon class="size-4" :class="{ 'animate-spin': enCurso }" />
                                            Regenerar
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <!--
                                La aprobación va dentro de la tarjeta del
                                borrador: es la acción que manda de esta pantalla,
                                y lo que se firma es el borrador que hay encima.
                            -->
                            <BloqueAprobacion
                                :documento-id="documento.id"
                                :version="versionEnCurso"
                                :puede-aprobar="puedeAprobar"
                                :entregas="versiones.length"
                                :siguiente="siguiente"
                            />
                        </CardContent>
                    </Card>
                </motion.div>

                <motion.aside :variants="variantesEntrada" class="flex flex-col gap-6">
                    <!--
                        El bloque de acuse NO se pinta si el documento no lo
                        exige, y el servidor tampoco lo manda: conectar dos cosas
                        abre una puerta lateral si quien pinta decide también qué
                        se permite.
                    -->
                    <BloqueAcuse
                        v-if="acuse && versionVigente"
                        :documento-id="documento.id"
                        :version-id="versionVigente.id"
                        :acuse="acuse"
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle>Ficha</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl class="flex flex-col gap-3.5 text-sm">
                                <!--
                                    La versión en vigor, con su firma. Cuando hay
                                    un borrador encima, la tarjeta de la izquierda
                                    habla de ÉL, y esto es lo único que sigue
                                    diciendo qué está entregado ahora mismo.
                                -->
                                <div class="flex flex-col gap-0.5">
                                    <dt class="text-[13px] font-medium text-muted-foreground">En vigor</dt>
                                    <template v-if="versionVigente">
                                        <dd class="flex items-center gap-2">
                                            <span class="cifra font-medium">{{ versionVigente.etiqueta }}</span>
                                            <CeldaBadge
                                                :valor="{
                                                    valor: versionVigente.estado,
                                                    etiqueta: versionVigente.estadoEtiqueta,
                                                    tono: versionVigente.estadoTono,
                                                    icono: versionVigente.estadoIcono,
                                                }"
                                            />
                                        </dd>
                                        <dd v-if="versionVigente.aprobadaPor" class="text-secondary-foreground">
                                            Firmada por {{ versionVigente.aprobadaPor }} el
                                            {{ fechaLegible(versionVigente.aprobadaEn) }}
                                        </dd>
                                        <dd v-if="versionVigente.notaAprobacion" class="text-muted-foreground">
                                            {{ versionVigente.notaAprobacion }}
                                        </dd>
                                    </template>
                                    <dd v-else class="text-muted-foreground">Ninguna versión todavía</dd>
                                </div>
                                <div v-if="versionVigente?.proximaRevision" class="flex flex-col gap-0.5">
                                    <dt class="text-[13px] font-medium text-muted-foreground">Próxima revisión</dt>
                                    <dd>
                                        {{ fechaLegible(versionVigente.proximaRevision) }}
                                        <span class="text-muted-foreground">
                                            ({{ distanciaLegible(versionVigente.proximaRevision) }})
                                        </span>
                                    </dd>
                                </div>
                                <!--
                                    Un documento redactado —política, norma,
                                    procedimiento— es de la organización entera y
                                    normalmente no cuelga de ningún sistema.
                                    Enseñar «— —» donde no hay nada es peor que no
                                    enseñar la fila.
                                -->
                                <div v-if="documento.sistema" class="flex flex-col gap-0.5">
                                    <dt class="text-[13px] font-medium text-muted-foreground">Sistema</dt>
                                    <dd>{{ documento.sistemaCodigo }} — {{ documento.sistema }}</dd>
                                </div>
                                <div v-if="documento.marco" class="flex flex-col gap-0.5">
                                    <dt class="text-[13px] font-medium text-muted-foreground">Marco</dt>
                                    <dd>{{ documento.marco }}</dd>
                                </div>
                                <div v-if="documento.periodicidad_revision_meses" class="flex flex-col gap-0.5">
                                    <dt class="text-[13px] font-medium text-muted-foreground">Se revisa cada</dt>
                                    <dd>
                                        <span class="cifra">{{ documento.periodicidad_revision_meses }}</span>
                                        {{ documento.periodicidad_revision_meses === 1 ? 'mes' : 'meses' }}
                                    </dd>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <dt class="text-[13px] font-medium text-muted-foreground">Responsable</dt>
                                    <dd :class="{ 'text-muted-foreground': documento.responsable === null }">
                                        {{ documento.responsable ?? 'Sin asignar' }}
                                    </dd>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <dt class="text-[13px] font-medium text-muted-foreground">Clasificación</dt>
                                    <dd>{{ documento.clasificacionEtiqueta }}</dd>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <dt class="text-[13px] font-medium text-muted-foreground">Acuse de lectura</dt>
                                    <dd>{{ documento.exige_acuse ? 'Se exige' : 'No se exige' }}</dd>
                                </div>
                                <div v-if="documento.notas" class="flex flex-col gap-0.5">
                                    <dt class="text-[13px] font-medium text-muted-foreground">Notas</dt>
                                    <dd>{{ documento.notas }}</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <!--
                        La huella entera de lo que está en vigor, a la vista y
                        no dentro del historial: es lo que se contrasta con el
                        fichero que tiene el auditor.
                    -->
                    <Card v-if="versionVigente?.huella">
                        <CardHeader>
                            <CardTitle>Huella de la <span class="cifra">{{ versionVigente.etiqueta }}</span></CardTitle>
                            <CardDescription>
                                SHA-256 del PDF que se firmó. Contrástala con el fichero que tenga el auditor.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="flex items-start gap-2">
                            <HuellaRevelada
                                :key="versionVigente.id"
                                :huella="versionVigente.huella"
                                :revelar="revelarHuella"
                            />
                            <CopiarHuella :huella="versionVigente.huella" :etiqueta="versionVigente.etiqueta" />
                        </CardContent>
                    </Card>
                </motion.aside>
            </div>

            <!--
                Sólo un plan de continuidad vincula servicios (§ 4.11):
                `serviciosDelPlan` es nulo para cualquier otro tipo y la tarjeta
                no se ofrece, en vez de enseñarse vacía en un documento que no la
                tiene.
            -->
            <motion.div v-if="serviciosDelPlan !== null" :variants="variantesEntrada">
                <Card>
                    <CardHeader>
                        <CardTitle>Servicios cubiertos</CardTitle>
                        <CardDescription>
                            Los servicios del inventario que este plan cubre. Se vinculan, no se crean: un
                            servicio ES un activo de tipo «Servicios» y su BIA se registra aparte.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ServiciosDelPlan
                            :documento-id="documento.id"
                            :servicios="serviciosDelPlan"
                            :disponibles="serviciosDisponibles ?? []"
                            :puede-gestionar="puedeRedactar"
                        />
                    </CardContent>
                </Card>
            </motion.div>

            <motion.section :variants="variantesEntrada" aria-labelledby="versiones-emitidas" class="flex flex-col gap-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="versiones-emitidas" class="text-base font-semibold tracking-[-0.01em]">Versiones emitidas</h2>
                    <p v-if="versiones.length > 0" class="text-[13px] text-muted-foreground">
                        <span class="cifra">{{ versiones.length }}</span>
                        {{ versiones.length === 1 ? 'entrega' : 'entregas' }} · cada PDF se guarda tal como se firmó
                    </p>
                </div>
                <HistorialVersiones :versiones="versiones" :documento-id="documento.id" />
            </motion.section>
        </motion.div>
    </AppLayout>
</template>
