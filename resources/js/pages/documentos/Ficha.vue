<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import ServiciosDelPlan from '@/components/continuidad/ServiciosDelPlan.vue';
import BloqueAcuse from '@/components/documento/BloqueAcuse.vue';
import BloqueAprobacion from '@/components/documento/BloqueAprobacion.vue';
import HistorialVersiones, { type Version } from '@/components/documento/HistorialVersiones.vue';
import EsqueletoDocumento from '@/components/documento/EsqueletoDocumento.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Opcion } from '@/lib/formularios';
import { motion } from 'motion-v';
import { Link, router, useForm, usePoll } from '@inertiajs/vue3';
import { DownloadIcon, FileTextIcon, PencilIcon, RefreshCwIcon, TypeIcon } from '@lucide/vue';
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
    { only: ['versionEnCurso', 'versiones'] },
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
    router.reload({ only: ['versionEnCurso', 'versiones'] });
}

/*
 * Un solo botón de color lleno por vista (DESIGN.md §9): en cuanto hay un
 * borrador generado, la acción que manda está en el bloque de aprobación —mandar
 * a revisión, o firmar—, así que regenerar baja a secundaria. Dos llenos a la vez
 * y no manda ninguno.
 */
const varianteGenerar = computed(() => (props.versionEnCurso?.descargable ? 'outline' : 'default'));

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
                <Button as-child variant="outline">
                    <Link :href="`/documentos/${documento.id}/cuerpo`">
                        <TypeIcon class="size-4" />
                        Editar documento
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="`/documentos/${documento.id}/editar`">
                        <PencilIcon class="size-4" />
                        Editar
                    </Link>
                </Button>
            </template>
        </CabeceraPagina>

        <motion.div
            class="grid gap-6 lg:grid-cols-3"
            :variants="escalonado"
            initial="oculto"
            animate="visible"
        >
            <motion.div :variants="variantesEntrada" class="lg:col-span-2">
            <Card>
                <CardHeader>
                    <CardTitle>Borrador</CardTitle>
                    <CardDescription>
                        Se genera a partir de lo que hay registrado ahora mismo y se puede regenerar
                        cuantas veces haga falta. No es una entrega hasta que se emite.
                    </CardDescription>
                </CardHeader>

                <CardContent class="flex flex-col gap-4">
                    <!--
                        Mientras el worker trabaja se enseña la forma de lo que
                        va a salir, no una rueda. Es la única espera del producto
                        que dura decenas de segundos, que es donde DESIGN.md §9
                        decía que hacía falta un esqueleto y donde no lo había.
                    -->
                    <EsqueletoDocumento v-if="enCurso" />

                    <div v-if="versionEnCurso" class="flex flex-wrap items-center gap-3">
                        <CeldaBadge
                            :valor="{
                                valor: versionEnCurso.generacion,
                                etiqueta: versionEnCurso.generacionEtiqueta,
                                tono: versionEnCurso.generacionTono,
                            }"
                        />
                        <span v-if="versionEnCurso.descargable" class="text-sm text-muted-foreground">
                            {{ kb(versionEnCurso.tamano) }}
                            <template v-if="versionEnCurso.recuento">· {{ versionEnCurso.recuento }}</template>
                        </span>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        No hay ningún borrador pendiente.
                    </p>

                    <p v-if="versionEnCurso?.error" class="text-sm text-destructive">
                        {{ versionEnCurso.error }}
                    </p>

                    <p v-if="rendido" class="text-sm text-muted-foreground">
                        Está tardando más de lo normal. Puede que la cola no esté procesando trabajos.
                    </p>

                    <!--
                        El PDF en disco es anterior a la última edición del
                        documento. Sin decirlo, alguien edita, descarga, no ve su
                        texto y concluye que el módulo no funciona.
                    -->
                    <p v-if="cuerpoMasNuevoQueElBorrador" class="text-sm text-estado-en-progreso">
                        El borrador es anterior a la última edición del documento. Regenéralo para verla.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            :variant="varianteGenerar"
                            :disabled="enCurso || generar.processing"
                            @click="generar.post(`/documentos/${documento.id}/generar`, { preserveScroll: true })"
                        >
                            <RefreshCwIcon class="size-4" :class="{ 'animate-spin': enCurso }" />
                            {{ versionEnCurso ? 'Regenerar borrador' : 'Generar borrador' }}
                        </Button>

                        <Button v-if="rendido" variant="outline" @click="comprobar">Comprobar</Button>

                        <Button
                            v-if="versionEnCurso?.descargable"
                            as-child
                            variant="outline"
                        >
                            <a :href="`/documentos/${documento.id}/versiones/${versionEnCurso.id}/descargar`">
                                <DownloadIcon class="size-4" />
                                Descargar borrador
                            </a>
                        </Button>

                        <Button v-if="versionEnCurso?.descargable" as-child variant="ghost">
                            <a :href="`/documentos/${documento.id}/versiones/${versionEnCurso.id}/word`">
                                <FileTextIcon class="size-4" />
                                Word
                            </a>
                        </Button>
                    </div>

                    <p class="text-sm text-muted-foreground">
                        Las versiones aprobadas no se regeneran. El PDF que se descarga es exactamente
                        el que se generó ese día, y su SHA-256 lo demuestra.
                    </p>
                </CardContent>
            </Card>

            <!--
                La aprobación va bajo el borrador y no en la columna estrecha:
                es donde está la acción que manda de esta pantalla, y lo que se
                firma es el borrador que hay justo encima.
            -->
            <BloqueAprobacion
                v-if="versionEnCurso"
                class="mt-6 block"
                :documento-id="documento.id"
                :version="versionEnCurso"
                :puede-aprobar="puedeAprobar"
                :entregas="versiones.length"
            />

            <BloqueAprobacion
                v-else-if="versionVigente"
                class="mt-6 block"
                :documento-id="documento.id"
                :version="versionVigente"
                :puede-aprobar="puedeAprobar"
                :entregas="versiones.length"
            />

            </motion.div>

            <motion.div :variants="variantesEntrada" class="flex flex-col gap-6">
            <!--
                El bloque de acuse NO se pinta si el documento no lo exige, y el
                servidor tampoco lo manda: conectar dos cosas abre una puerta
                lateral si quien pinta decide también qué se permite.
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
                <CardContent class="flex flex-col gap-3 text-sm">
                    <div>
                        <div class="text-muted-foreground">Código</div>
                        <div class="cifra">{{ documento.codigo }}</div>
                    </div>
                    <!--
                        Un documento redactado —política, norma, procedimiento,
                        plan de continuidad— es de la organización entera y
                        normalmente no cuelga de ningún sistema. Enseñar «— —»
                        donde no hay nada es peor que no enseñar la fila.
                    -->
                    <div v-if="documento.sistema">
                        <div class="text-muted-foreground">Sistema</div>
                        <div>{{ documento.sistemaCodigo }} — {{ documento.sistema }}</div>
                    </div>
                    <div v-if="documento.marco">
                        <div class="text-muted-foreground">Marco</div>
                        <div>{{ documento.marco }}</div>
                    </div>
                    <div v-if="documento.periodicidad_revision_meses">
                        <div class="text-muted-foreground">Se revisa cada</div>
                        <div>
                            <span class="cifra">{{ documento.periodicidad_revision_meses }}</span>
                            {{ documento.periodicidad_revision_meses === 1 ? 'mes' : 'meses' }}
                        </div>
                    </div>

                    <!--
                        La versión en vigor, con su firma. Cuando hay un borrador
                        encima, el bloque de aprobación habla de ÉL —que es lo que
                        pide acción— y esto es lo único que sigue diciendo qué
                        está entregado ahora mismo.
                    -->
                    <div v-if="versionVigente?.aprobadaPor">
                        <div class="text-muted-foreground">Versión vigente</div>
                        <div>
                            <span class="cifra">{{ versionVigente.etiqueta }}</span>
                            · aprobada por {{ versionVigente.aprobadaPor }}
                            el {{ versionVigente.aprobadaEn }}
                        </div>
                    </div>
                    <div v-if="versionVigente?.proximaRevision">
                        <div class="text-muted-foreground">Próxima revisión</div>
                        <div class="cifra">{{ versionVigente.proximaRevision }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Clasificación</div>
                        <div>{{ documento.clasificacionEtiqueta }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Responsable</div>
                        <div>{{ documento.responsable ?? 'Sin asignar' }}</div>
                    </div>
                    <div v-if="documento.notas">
                        <div class="text-muted-foreground">Notas</div>
                        <div>{{ documento.notas }}</div>
                    </div>
                </CardContent>
            </Card>
            </motion.div>

            <!--
                Sólo un plan de continuidad vincula servicios (§ 4.11):
                `serviciosDelPlan` es nulo para cualquier otro tipo y la tarjeta
                no se ofrece, en vez de enseñarse vacía en un documento que no
                la tiene.
            -->
            <motion.div
                v-if="serviciosDelPlan !== null"
                :variants="variantesEntrada"
                class="lg:col-span-3"
            >
            <Card>
                <CardHeader>
                    <CardTitle>Servicios cubiertos</CardTitle>
                    <CardDescription>
                        Los servicios del inventario que este plan cubre. Se vinculan, no se crean:
                        un servicio ES un activo de tipo «Servicios» y su BIA se registra aparte.
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

            <motion.div :variants="variantesEntrada" class="lg:col-span-3">
            <Card>
                <CardHeader>
                    <CardTitle>Versiones emitidas</CardTitle>
                    <CardDescription>
                        Cada entrega con su huella SHA-256. Es lo que permite demostrar que el PDF
                        que se enseña es el que se emitió aquel día.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <HistorialVersiones :versiones="versiones" :documento-id="documento.id" />
                </CardContent>
            </Card>
            </motion.div>
        </motion.div>
    </AppLayout>
</template>
