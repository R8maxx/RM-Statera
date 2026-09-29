<script setup lang="ts">
import BarraSegmentada from '@/components/BarraSegmentada.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CampoFichero from '@/components/formulario/CampoFichero.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import HistoricoTransiciones, { type Transicion } from '@/components/HistoricoTransiciones.vue';
import PendienteProveedor, { type Pendiente, type TareaPendiente } from '@/components/proveedor/PendienteProveedor.vue';
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
import { distanciaLegible, fechaLegible } from '@/lib/celdas';
import { conOpcionVacia, type Opcion } from '@/lib/formularios';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ChevronRightIcon, CircleHelpIcon, PlusIcon } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * La ficha de un proveedor (§ 4.9).
 *
 * **Arriba, lo que falta para homologarlo** (`PendienteProveedor`): el
 * certificado caducado, la condición de la última evaluación con las tareas que
 * la levantan y los datos de la ficha que no casan con lo evaluado. Antes
 * estaba repartido en cuatro tarjetas, y la condición, dentro de un plegable.
 *
 * La columna lateral va en el orden de `DESIGN.md`: «Estado» arriba —cuándo
 * toca reevaluar, evaluar, retirar— y «Ficha» después. **El estado no se
 * cambia desde un desplegable**: lo pone la última evaluación, y lo único que
 * se mueve a mano es retirar y reactivar. **Evaluar no espera a la fecha**:
 * resuelta la condición, evaluar otra vez es lo que homologa.
 *
 * Las evaluaciones van en la columna principal y plegadas salvo la última, que
 * enseña primero lo que no se cumple y deja lo que sí en una rejilla compacta:
 * doce filas iguales para decir que falla una no dejaban ver cuál.
 */
interface Proveedor {
    id: number;
    codigo: string;
    nombre: string;
    cif: string | null;
    servicio_prestado: string;
    estado: string;
    estadoEtiqueta: string;
    estadoDescripcion: string;
    estadoTono: string;
    estadoIcono: string;
    criticidad: string;
    criticidadEtiqueta: string;
    criticidadTono: string;
    criticidad_derivada: string | null;
    criticidad_declarada: string | null;
    justificacion_criticidad: string | null;
    rebajaLaDerivada: boolean;
    mesesReevaluacion: number;
    es_nube: boolean;
    modeloNubeEtiqueta: string | null;
    ubicacionDatosEtiqueta: string;
    ubicacion_detalle: string | null;
    es_subencargado_rgpd: boolean;
    responsable: string | null;
    notas: string | null;
    proximaEvaluacion: string | null;
    reevaluacionVencida: boolean;
    seReevalua: boolean;
}

interface Certificacion {
    id: number;
    tipo: string;
    etiqueta: string;
    entidadEmisora: string | null;
    emitidaEn: string | null;
    caducaEn: string | null;
    caducada: boolean;
    evidencia: { id: number; titulo: string } | null;
}

interface Evaluacion {
    id: number;
    fecha: string;
    resultadoEtiqueta: string;
    resultadoTono: string;
    resultadoIcono: string;
    criticidad: string;
    conclusiones: string | null;
    evaluadaPor: string | null;
    incumplidas: number;
    clausulas: { codigo: string; titulo: string; valor: string; resultado: string; tono: string; icono: string; nota: string | null }[];
}

const props = defineProps<{
    proveedor: Proveedor;
    activos: { id: number; codigo: string; nombre: string; nivel: string }[];
    certificaciones: Certificacion[];
    evaluaciones: Evaluacion[];
    historial: Transicion[];
    pendiente: Pendiente;
    tareas: TareaPendiente[];
    tiposCertificacion: Opcion[];
    evidencias: Opcion[];
    responsables: Opcion[];
    prioridades: Opcion[];
    puedeGestionar: boolean;
    puedeEvaluar: boolean;
    puedeAbrirTarea: boolean;
}>();

const abierta = ref<number | null>(props.evaluaciones[0]?.id ?? null);

/* ---------------------------------------------------------- Evaluaciones */

/*
 * El orden en que se leen las cláusulas de una evaluación: primero lo que falla,
 * que es lo que se busca, y lo que se cumple al final y en compacto.
 */
const grupos = [
    { valor: 'no_cumple', titulo: 'No se cumple' },
    { valor: 'no_aplica', titulo: 'No aplica' },
] as const;

function clausulasDe(evaluacion: Evaluacion, valor: string) {
    return evaluacion.clausulas.filter((clausula) => clausula.valor === valor);
}

function repartoDe(evaluacion: Evaluacion) {
    const cuenta = (valor: string) => clausulasDe(evaluacion, valor).length;

    return [
        { clave: 'cumple', etiqueta: 'Se cumplen', valor: cuenta('cumple'), tono: 'implantado' },
        { clave: 'no_cumple', etiqueta: 'No se cumplen', valor: cuenta('no_cumple'), tono: 'no_iniciado' },
        { clave: 'no_aplica', etiqueta: 'No aplican', valor: cuenta('no_aplica'), tono: 'no_aplica' },
    ];
}

function plazoDe(tarea: TareaPendiente) {
    return {
        valor: tarea.plazo.etiqueta,
        etiqueta: tarea.plazo.fecha ? `${tarea.plazo.etiqueta} · ${fechaLegible(tarea.plazo.fecha)}` : tarea.plazo.etiqueta,
        tono: tarea.plazo.tono,
        icono: tarea.plazo.tono === 'caducada' ? 'CalendarX' : 'CalendarClock',
    };
}

/* --------------------------------------------------------------- Ficha */

/** Lo que dice la ficha y la última evaluación contradice, marcado en «Ficha». */
const datosEnDuda = computed(() => new Set(props.pendiente.contradicciones.map((una) => una.clave)));

/* ------------------------------------------------------------- Retirar */

const retirando = ref(false);
const retirada = useForm({ motivo: '' });

function retirar(): void {
    retirada.post(`/proveedores/${props.proveedor.id}/retirar`, {
        preserveScroll: true,
        onSuccess: () => {
            retirando.value = false;
            retirada.reset();
        },
    });
}

function reactivar(): void {
    router.post(`/proveedores/${props.proveedor.id}/reactivar`, {}, { preserveScroll: true });
}

/* ------------------------------------------------------- Certificaciones */

const certificando = ref(false);
const certificacion = useForm({
    tipo: 'iso27001',
    categoria_ens: '',
    descripcion: '',
    entidad_emisora: '',
    emitida_en: '',
    caduca_en: '',
    evidencia_id: '',
    fichero: null as File | null,
});

const categorias: Opcion[] = [
    { valor: 'basica', etiqueta: 'Básica' },
    { valor: 'media', etiqueta: 'Media' },
    { valor: 'alta', etiqueta: 'Alta' },
];

const evidencias = computed(() => conOpcionVacia(props.evidencias, 'Sin evidencia'));

function certificar(): void {
    certificacion
        .transform((datos) => ({
            ...datos,
            categoria_ens: datos.tipo === 'ens' ? datos.categoria_ens : null,
            descripcion: datos.descripcion || null,
            // Sin fichero no se manda la clave: un `null` en un multipart llega
            // como cadena vacía y la regla `file` lo rechaza.
            ...(datos.fichero ? { fichero: datos.fichero, evidencia_id: '' } : { fichero: undefined }),
        }))
        .post(`/proveedores/${props.proveedor.id}/certificaciones`, {
            preserveScroll: true,
            onSuccess: () => {
                certificando.value = false;
                certificacion.reset();
            },
        });
}

function borrarCertificacion(id: number): void {
    router.delete(`/proveedores/${props.proveedor.id}/certificaciones/${id}`, { preserveScroll: true });
}

/* ---------------------------------------------------------------- Tareas */

const abriendoTarea = ref(false);
const tarea = useForm({ titulo: '', descripcion: '', prioridad: 'media', responsable_id: '', fecha_limite: '' });

function abrirTarea(): void {
    tarea.post(`/proveedores/${props.proveedor.id}/tareas`, {
        preserveScroll: true,
        onSuccess: () => {
            abriendoTarea.value = false;
            tarea.reset();
        },
    });
}
</script>

<template>
    <AppLayout :titulo="proveedor.nombre">
        <CabeceraPagina :titulo="proveedor.nombre" :codigo="proveedor.codigo" :descripcion="proveedor.servicio_prestado">
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/proveedores/${proveedor.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: proveedor.estado,
                    etiqueta: proveedor.estadoEtiqueta,
                    tono: proveedor.estadoTono,
                    icono: proveedor.estadoIcono,
                }"
            />
            <CeldaBadge
                :valor="{
                    valor: proveedor.criticidad,
                    etiqueta: `Criticidad ${proveedor.criticidadEtiqueta.toLowerCase()}`,
                    tono: proveedor.criticidadTono,
                }"
            />
            <span v-if="proveedor.es_nube" class="text-sm text-muted-foreground">
                Nube · {{ proveedor.modeloNubeEtiqueta }}
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <PendienteProveedor
                    v-if="proveedor.seReevalua"
                    :pendiente="pendiente"
                    :proveedor-id="proveedor.id"
                    :homologado="proveedor.estado === 'homologado'"
                    :puede-gestionar="puedeGestionar"
                    :puede-evaluar="puedeEvaluar"
                    :puede-abrir-tarea="puedeAbrirTarea"
                    @registrar-certificacion="certificando = true"
                    @abrir-tarea="abriendoTarea = true"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Evaluaciones</CardTitle>
                        <CardDescription>
                            Lo que se comprobó en su contrato cada vez, cláusula a cláusula. No se editan: si el contrato
                            cambia, se evalúa otra vez.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <EstadoVacio
                            v-if="evaluaciones.length === 0"
                            titulo="Sin evaluar"
                            descripcion="Nunca se ha comprobado su contrato. Hasta la primera evaluación sigue «en evaluación» y no tiene fecha de reevaluación."
                        />
                        <ul v-else class="grid gap-3">
                            <li v-for="(evaluacion, indice) in evaluaciones" :key="evaluacion.id" class="rounded-lg border p-4">
                                <button
                                    type="button"
                                    class="flex w-full flex-wrap items-center gap-2 text-left"
                                    :aria-expanded="abierta === evaluacion.id"
                                    @click="abierta = abierta === evaluacion.id ? null : evaluacion.id"
                                >
                                    <ChevronRightIcon
                                        class="size-4 shrink-0 text-muted-foreground transition-transform duration-(--duracion)"
                                        :class="abierta === evaluacion.id && 'rotate-90'"
                                    />
                                    <span class="text-sm font-medium">{{ fechaLegible(evaluacion.fecha) }}</span>
                                    <CeldaBadge
                                        :valor="{
                                            valor: evaluacion.resultadoEtiqueta,
                                            etiqueta: evaluacion.resultadoEtiqueta,
                                            tono: evaluacion.resultadoTono,
                                            icono: evaluacion.resultadoIcono,
                                        }"
                                    />
                                    <span class="text-xs text-muted-foreground">
                                        {{ evaluacion.evaluadaPor ?? 'Sin autor' }} · con criticidad
                                        {{ evaluacion.criticidad.toLowerCase() }}
                                    </span>
                                    <span v-if="indice === 0" class="ml-auto text-xs text-muted-foreground">La última</span>
                                </button>

                                <div v-show="abierta === evaluacion.id" class="mt-4 grid gap-4 pl-6">
                                    <BarraSegmentada :segmentos="repartoDe(evaluacion)" leyenda />

                                    <p v-if="evaluacion.conclusiones" class="text-sm whitespace-pre-line">
                                        {{ evaluacion.conclusiones }}
                                    </p>

                                    <template v-for="grupo in grupos" :key="grupo.valor">
                                        <div v-if="clausulasDe(evaluacion, grupo.valor).length > 0" class="grid gap-1">
                                            <p class="text-xs font-medium text-muted-foreground">{{ grupo.titulo }}</p>
                                            <ul class="grid gap-1 text-sm">
                                                <li
                                                    v-for="clausula in clausulasDe(evaluacion, grupo.valor)"
                                                    :key="clausula.codigo"
                                                    class="grid gap-0.5 rounded-md bg-superficie px-3 py-2"
                                                >
                                                    <span class="flex flex-wrap items-center gap-2">
                                                        <CeldaBadge
                                                            :valor="{
                                                                valor: clausula.valor,
                                                                etiqueta: clausula.resultado,
                                                                tono: clausula.tono,
                                                                icono: clausula.icono,
                                                            }"
                                                        />
                                                        <span class="cifra text-xs text-muted-foreground">{{ clausula.codigo }}</span>
                                                        <span class="font-medium">{{ clausula.titulo }}</span>
                                                    </span>
                                                    <span v-if="clausula.nota" class="text-muted-foreground">{{ clausula.nota }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </template>

                                    <div v-if="clausulasDe(evaluacion, 'cumple').length > 0" class="grid gap-1">
                                        <p class="text-xs font-medium text-muted-foreground">Se cumplen</p>
                                        <ul class="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-2 xl:grid-cols-3">
                                            <li
                                                v-for="clausula in clausulasDe(evaluacion, 'cumple')"
                                                :key="clausula.codigo"
                                                class="flex min-w-0 items-baseline gap-2"
                                                :title="clausula.nota ?? undefined"
                                            >
                                                <span class="cifra shrink-0 text-xs text-muted-foreground">{{ clausula.codigo }}</span>
                                                <span class="truncate">{{ clausula.titulo }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Certificaciones</CardTitle>
                        <CardDescription>
                            Lo que acredita: ISO 27001, la conformidad con el ENS y su categoría, u otra cosa. Un
                            certificado caducado de alguien con quien se sigue trabajando sube al panel.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <p v-if="certificaciones.length === 0" class="text-muted-foreground">Ninguna registrada.</p>
                        <ul v-else class="divide-y">
                            <li v-for="una in certificaciones" :key="una.id" class="flex flex-wrap items-center gap-2 py-2">
                                <span class="font-medium">{{ una.etiqueta }}</span>
                                <span class="text-muted-foreground">· {{ una.entidadEmisora ?? 'Entidad emisora sin registrar' }}</span>
                                <CeldaBadge
                                    v-if="una.caducaEn"
                                    :valor="{
                                        valor: una.caducaEn,
                                        etiqueta: una.caducada ? `Caducó el ${fechaLegible(una.caducaEn)}` : `Hasta el ${fechaLegible(una.caducaEn)}`,
                                        tono: una.caducada ? 'caducada' : 'implantado',
                                        icono: una.caducada ? 'TriangleAlert' : 'CircleCheck',
                                    }"
                                />
                                <Link v-if="una.evidencia" :href="`/evidencias/${una.evidencia.id}`" class="underline underline-offset-4">
                                    {{ una.evidencia.titulo }}
                                </Link>
                                <span v-else class="text-muted-foreground">· sin evidencia vinculada</span>
                                <Button
                                    v-if="puedeGestionar"
                                    variant="ghost"
                                    size="sm"
                                    class="ml-auto"
                                    @click="borrarCertificacion(una.id)"
                                >
                                    Quitar
                                </Button>
                            </li>
                        </ul>
                        <Button v-if="puedeGestionar" variant="outline" size="sm" @click="certificando = true">
                            <PlusIcon />
                            Registrar una certificación
                        </Button>
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
                        <CardDescription>{{ proveedor.estadoDescripcion }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <dl v-if="proveedor.seReevalua && proveedor.proximaEvaluacion" class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2">
                            <dt class="text-muted-foreground">Próxima evaluación</dt>
                            <dd>
                                <strong class="font-semibold">{{ fechaLegible(proveedor.proximaEvaluacion) }}</strong>
                                <span class="text-muted-foreground"> ({{ distanciaLegible(proveedor.proximaEvaluacion) }})</span>
                            </dd>
                            <dt class="text-muted-foreground">Plazo</dt>
                            <dd>Cada {{ proveedor.mesesReevaluacion }} meses, por criticidad {{ proveedor.criticidadEtiqueta.toLowerCase() }}</dd>
                        </dl>
                        <div v-if="puedeEvaluar && proveedor.seReevalua" class="grid gap-2">
                            <Button as-child>
                                <Link :href="`/proveedores/${proveedor.id}/evaluar`">
                                    {{ evaluaciones.length === 0 ? 'Evaluar' : 'Evaluar ahora' }}
                                </Link>
                            </Button>
                            <p v-if="evaluaciones.length > 0 && proveedor.estado !== 'homologado'" class="text-xs text-muted-foreground">
                                No hace falta esperar a la fecha: con la condición resuelta, evaluar otra vez es lo que lo
                                homologa.
                            </p>
                        </div>
                        <div v-if="puedeGestionar" class="border-t pt-4">
                            <Button v-if="proveedor.seReevalua" variant="outline" size="sm" @click="retirando = true">
                                Retirar proveedor
                            </Button>
                            <Button v-else variant="outline" size="sm" @click="reactivar">Reactivar</Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <dl class="grid gap-3">
                            <div class="grid gap-0.5">
                                <dt class="text-xs text-muted-foreground">Criticidad</dt>
                                <dd>
                                    {{ proveedor.criticidadEtiqueta }}
                                    <span class="text-muted-foreground">
                                        <template v-if="proveedor.criticidad_declarada && proveedor.criticidad_derivada">
                                            · declarada; sus activos dan {{ proveedor.criticidad_derivada }}
                                        </template>
                                        <template v-else-if="proveedor.criticidad_declarada">· declarada, sin activos</template>
                                        <template v-else>· la de sus activos</template>
                                    </span>
                                </dd>
                                <dd v-if="proveedor.justificacion_criticidad" class="text-muted-foreground whitespace-pre-line">
                                    {{ proveedor.justificacion_criticidad }}
                                </dd>
                            </div>
                            <div class="grid gap-0.5">
                                <dt class="text-xs text-muted-foreground">Presta</dt>
                                <dd v-if="activos.length === 0" class="text-muted-foreground">
                                    Ningún activo del inventario. Se le asigna uno desde la ficha del activo.
                                </dd>
                                <dd v-for="activo in activos" v-else :key="activo.id" class="flex flex-wrap items-baseline gap-x-2">
                                    <Link :href="`/activos/${activo.id}`" class="underline underline-offset-4">
                                        <span class="cifra">{{ activo.codigo }}</span> · {{ activo.nombre }}
                                    </Link>
                                    <span class="text-xs text-muted-foreground">valoración {{ activo.nivel.toLowerCase() }}</span>
                                </dd>
                            </div>
                            <div class="grid gap-0.5">
                                <dt class="text-xs text-muted-foreground">Responsable</dt>
                                <dd :class="!proveedor.responsable && 'text-muted-foreground'">{{ proveedor.responsable ?? 'Sin asignar' }}</dd>
                            </div>
                            <div class="grid gap-0.5">
                                <dt class="text-xs text-muted-foreground">CIF</dt>
                                <dd :class="proveedor.cif ? 'cifra' : 'text-muted-foreground'">{{ proveedor.cif ?? 'Sin registrar' }}</dd>
                            </div>
                            <div class="grid gap-0.5">
                                <dt class="text-xs text-muted-foreground">Datos en</dt>
                                <dd class="flex items-center gap-1.5">
                                    <CircleHelpIcon v-if="datosEnDuda.has('ubicacion_datos')" class="size-3.5 text-muted-foreground" aria-label="No casa con la última evaluación" />
                                    {{ proveedor.ubicacionDatosEtiqueta }}
                                    <template v-if="proveedor.ubicacion_detalle">· {{ proveedor.ubicacion_detalle }}</template>
                                </dd>
                            </div>
                            <div class="grid gap-0.5">
                                <dt class="text-xs text-muted-foreground">Encargado del tratamiento</dt>
                                <dd class="flex items-center gap-1.5">
                                    <CircleHelpIcon
                                        v-if="datosEnDuda.has('encargado_tratamiento')"
                                        class="size-3.5 text-muted-foreground"
                                        aria-label="No casa con la última evaluación"
                                    />
                                    {{ proveedor.es_subencargado_rgpd ? 'Sí' : 'No' }}
                                </dd>
                            </div>
                            <div v-if="proveedor.es_nube" class="grid gap-0.5">
                                <dt class="text-xs text-muted-foreground">Nube</dt>
                                <dd>{{ proveedor.modeloNubeEtiqueta }}</dd>
                            </div>
                            <div v-if="proveedor.notas" class="grid gap-0.5">
                                <dt class="text-xs text-muted-foreground">Notas</dt>
                                <dd class="whitespace-pre-line">{{ proveedor.notas }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Tareas</CardTitle>
                        <CardDescription>
                            Lo que queda pendiente con este proveedor: firmar el encargo de tratamiento, pedir el informe
                            de auditoría.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <p v-if="tareas.length === 0" class="text-muted-foreground">Ninguna abierta desde aquí.</p>
                        <ul v-else class="grid gap-3">
                            <li v-for="una in tareas" :key="una.id" class="grid gap-1">
                                <Link :href="`/tareas/${una.id}`" class="underline underline-offset-4">{{ una.titulo }}</Link>
                                <span class="flex flex-wrap items-center gap-2">
                                    <CeldaBadge :valor="{ valor: una.estado, etiqueta: una.estado, tono: una.tono, icono: una.icono }" />
                                    <CeldaBadge :valor="plazoDe(una)" />
                                </span>
                                <span class="text-xs text-muted-foreground">{{ una.responsable ?? 'Sin responsable' }}</span>
                            </li>
                        </ul>
                        <Button v-if="puedeAbrirTarea" variant="outline" size="sm" @click="abriendoTarea = true">
                            <PlusIcon />
                            Abrir una tarea
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="retirando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Retirar a {{ proveedor.nombre }}</DialogTitle>
                    <DialogDescription>
                        Deja de trabajarse con él: no se le reevalúa y no se le ofrece a un activo nuevo. No se borra nada;
                        sus evaluaciones siguen donde están.
                    </DialogDescription>
                </DialogHeader>
                <CampoTextarea
                    v-model="retirada.motivo"
                    nombre="motivo"
                    etiqueta="Motivo"
                    :filas="2"
                    :error="retirada.errors.motivo"
                    requerido
                    ayuda="«Fin del contrato», «sustituido por otro proveedor»."
                />
                <DialogFooter>
                    <Button variant="outline" @click="retirando = false">Cancelar</Button>
                    <Button :disabled="retirada.processing || retirada.motivo.trim() === ''" @click="retirar">Retirar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="certificando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registrar una certificación</DialogTitle>
                    <DialogDescription>
                        Si subes el certificado, se guarda como evidencia, que es donde ya viven los ficheros con caducidad.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4">
                    <CampoSelect
                        v-model="certificacion.tipo"
                        nombre="tipo"
                        etiqueta="Qué acredita"
                        :opciones="tiposCertificacion"
                        :error="certificacion.errors.tipo"
                        requerido
                    />
                    <CampoSelect
                        v-if="certificacion.tipo === 'ens'"
                        v-model="certificacion.categoria_ens"
                        nombre="categoria_ens"
                        etiqueta="Categoría"
                        :opciones="categorias"
                        :error="certificacion.errors.categoria_ens"
                        requerido
                        ayuda="Tiene que ser igual o superior a la del sistema al que presta servicio."
                    />
                    <CampoTexto
                        v-if="certificacion.tipo === 'otra'"
                        v-model="certificacion.descripcion"
                        nombre="descripcion"
                        etiqueta="Cuál"
                        :error="certificacion.errors.descripcion"
                        requerido
                        placeholder="Informe SOC 2 tipo II, cualificación CPSTIC…"
                    />
                    <CampoTexto
                        v-model="certificacion.entidad_emisora"
                        nombre="entidad_emisora"
                        etiqueta="Entidad emisora"
                        :error="certificacion.errors.entidad_emisora"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <CampoTexto
                            v-model="certificacion.emitida_en"
                            nombre="emitida_en"
                            etiqueta="Emitida el"
                            tipo="date"
                            :error="certificacion.errors.emitida_en"
                        />
                        <CampoTexto
                            v-model="certificacion.caduca_en"
                            nombre="caduca_en"
                            etiqueta="Caduca el"
                            tipo="date"
                            :error="certificacion.errors.caduca_en"
                        />
                    </div>
                    <CampoFichero
                        nombre="fichero"
                        etiqueta="El certificado"
                        acepta=".pdf,image/*"
                        :error="certificacion.errors.fichero"
                        ayuda="Si lo tienes. Queda como evidencia, con la misma caducidad, y se puede vincular a A.5.19. Hasta 50 MB."
                        @elegir="certificacion.fichero = $event"
                    />
                    <CampoSelect
                        v-if="!certificacion.fichero"
                        v-model="certificacion.evidencia_id"
                        nombre="evidencia_id"
                        etiqueta="O una evidencia que ya existe"
                        :opciones="evidencias"
                        :error="certificacion.errors.evidencia_id"
                        ayuda="Si el certificado ya está registrado en «Evidencias»."
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="certificando = false">Cancelar</Button>
                    <Button :disabled="certificacion.processing" @click="certificar">Registrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="abriendoTarea">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Abrir una tarea</DialogTitle>
                    <DialogDescription>
                        Queda en el plan de acción con origen «Proveedor» y enlazada aquí. Lleva responsable y fecha: es lo
                        que levanta la condición, y sin ellas nadie la mueve.
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
                            requerido
                        />
                    </div>
                    <CampoSelect
                        v-model="tarea.responsable_id"
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="props.responsables"
                        :error="tarea.errors.responsable_id"
                        requerido
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
