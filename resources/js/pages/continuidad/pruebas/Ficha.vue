<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import ComparativaRecuperacion, { type ServicioComparado } from '@/components/continuidad/ComparativaRecuperacion.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import HistoricoTransiciones, { type Transicion } from '@/components/HistoricoTransiciones.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { distanciaLegible, fechaDe, fechaLegible } from '@/lib/celdas';
import { conOpcionVacia, SIN_VALOR } from '@/lib/formularios';
import { FileTextIcon, FileWarningIcon, InfoIcon, ListTodoIcon, PencilIcon } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface TareaDerivada {
    id: number;
    titulo: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    prioridad: string;
    fechaLimite: string | null;
    responsable: string | null;
}

interface NoConformidadDerivada {
    id: number;
    codigo: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
}

interface Prueba {
    id: number;
    codigo: string;
    titulo: string;
    documento_id: number;
    plan: { id: number; codigo: string; titulo: string } | null;
    tipo: string;
    tipoEtiqueta: string;
    tipoTono: string;
    tipoIcono: string;
    tipoDescripcion: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    fecha_prevista: string;
    fechaPrevistaEtiqueta: string;
    fecha_realizacion: string | null;
    fechaRealizacionEtiqueta: string | null;
    resultado: string | null;
    resultadoEtiqueta: string | null;
    resultadoTono: string | null;
    resultadoIcono: string | null;
    conclusiones: string | null;
    motivo_cancelacion: string | null;
    evidencia_id: number | null;
    evidencia: string | null;
    responsable_id: number | null;
    responsable: string | null;
}

/**
 * La ficha de una prueba de continuidad: § 4.11 y `op.cont.3`.
 *
 * **Dos formas de terminar, y ninguna es un desplegable genérico.** No hay un
 * `CambiarEstadoPrueba` como el del BIA o un incidente —ver la cabecera de
 * `EstadoPrueba`—: «Registrar resultado» y «Cancelar» son dos formularios
 * distintos porque piden datos distintos, y colapsarlos en un único paso con
 * `estado` escondería esa diferencia.
 *
 * **La recuperación por servicio es la tarjeta fuerte**, con el titular que
 * cuenta cuántos servicios pasaron de su RTO y, debajo, las barras sobre un
 * mismo eje. Las conclusiones van dentro de «Lo que dejó esta prueba», junto a
 * lo que se derivó de ellas: son el mismo hecho contado dos veces.
 *
 * **Sin cambio de estado genérico, pero con tarjeta «Estado»** arriba en la
 * columna lateral, como el resto de fichas: dice cómo se cierra la prueba si
 * sigue planificada y, si ya no, lleva a planificar la siguiente.
 *
 * **«Editar» sólo aparece mientras la prueba sigue planificada.** El
 * guardián de verdad está en `PruebaContinuidadController::update()`; esto es
 * sólo evitarle a alguien un 403 con el que no contaba.
 */
const props = defineProps<{
    prueba: Prueba;
    servicios: ServicioComparado[];
    historial: Transicion[];
    pendientes: string[];
    evidencias: Opcion[];
    resultados: Opcion[];
    tareasDerivadas: TareaDerivada[];
    noConformidadDerivada: NoConformidadDerivada | null;
    sugerenciaCodigoNoConformidad: string;
    sugerenciaCodigoMejora: string;
    prioridades: Opcion[];
    responsables: Opcion[];
    puedeGestionar: boolean;
    puedeDerivar: boolean;
    puedeAbrirTarea: boolean;
    puedeTratar: boolean;
    puedeMejorar: boolean;
}>();

const planificada = computed(() => props.prueba.estado === 'planificada');

/* --- Lo que se lee de un vistazo --- */

const medidos = computed(() => props.servicios.filter((servicio) => servicio.excedeRto !== null).length);
const excedidos = computed(() => props.servicios.filter((servicio) => servicio.excedeRto === true).length);

/** Cuánto se desvió la realización de lo previsto, en días: «Dos días después de lo previsto». */
const desvio = computed(() => {
    const prevista = fechaDe(props.prueba.fecha_prevista);
    const realizada = fechaDe(props.prueba.fecha_realizacion);

    if (prevista === null || realizada === null) {
        return null;
    }

    const dias = Math.round((realizada.getTime() - prevista.getTime()) / 86_400_000);

    if (dias === 0) {
        return 'El día previsto';
    }

    const cuantos = Math.abs(dias) === 1 ? 'Un día' : `${Math.abs(dias)} días`;

    return `${cuantos} ${dias > 0 ? 'después' : 'antes'} de lo previsto`;
});

function detalleTarea(tarea: TareaDerivada): string {
    const partes = ['Tarea', `prioridad ${tarea.prioridad.toLowerCase()}`];

    if (tarea.responsable) {
        partes.push(tarea.responsable);
    }

    if (tarea.fechaLimite) {
        partes.push(`vence el ${fechaLegible(tarea.fechaLimite)} (${distanciaLegible(tarea.fechaLimite)})`);
    }

    return partes.join(' · ');
}

/* --- Registrar resultado --- */

const mostrandoResultado = ref(false);
const fechaRealizacion = ref(new Date().toISOString().slice(0, 10));
const resultado = ref('superada');
const conclusiones = ref('');
const evidenciaId = ref(SIN_VALOR);
const enviandoResultado = ref(false);
const erroresResultado = ref<Record<string, string>>({});

const filasResultado = reactive(
    props.servicios.map((servicio) => ({
        id: servicio.id,
        codigo: servicio.codigo,
        nombre: servicio.nombre,
        rtoObjetivo: servicio.rtoObjetivo,
        rpoObjetivo: servicio.rpoObjetivo,
        rtoAlcanzado: '',
        rpoAlcanzado: '',
    })),
);

function registrarResultado(): void {
    enviandoResultado.value = true;
    erroresResultado.value = {};

    router.post(
        `/continuidad/pruebas/${props.prueba.id}/resultado`,
        {
            fecha_realizacion: fechaRealizacion.value,
            resultado: resultado.value,
            conclusiones: conclusiones.value.trim() === '' ? null : conclusiones.value,
            evidencia_id: evidenciaId.value === SIN_VALOR ? null : evidenciaId.value,
            servicios: Object.fromEntries(
                filasResultado.map((fila) => [
                    fila.id,
                    {
                        rto_alcanzado_horas: fila.rtoAlcanzado === '' ? null : Number(fila.rtoAlcanzado),
                        rpo_alcanzado_horas: fila.rpoAlcanzado === '' ? null : Number(fila.rpoAlcanzado),
                    },
                ]),
            ),
        },
        {
            preserveScroll: true,
            onError: (errores) => (erroresResultado.value = errores),
            onSuccess: () => (mostrandoResultado.value = false),
            onFinish: () => (enviandoResultado.value = false),
        },
    );
}

/* --- Cancelar --- */

const mostrandoCancelacion = ref(false);
const motivo = ref('');
const enviandoCancelacion = ref(false);
const errorCancelacion = ref<string | null>(null);

function cancelar(): void {
    if (motivo.value.trim() === '') {
        return;
    }

    enviandoCancelacion.value = true;
    errorCancelacion.value = null;

    router.post(
        `/continuidad/pruebas/${props.prueba.id}/cancelar`,
        { motivo: motivo.value },
        {
            preserveScroll: true,
            onError: (errores) => (errorCancelacion.value = errores.motivo ?? 'No se ha podido cancelar.'),
            onFinish: () => (enviandoCancelacion.value = false),
        },
    );
}

/*
 * --- Adjuntar la evidencia ---
 *
 * La única escritura sobre una prueba realizada (`AdjuntarEvidenciaPrueba`):
 * el informe del restore o el acta del simulacro suelen llegar días después
 * del resultado. La abre el chip de la tira de «Lo que falta» y la fila
 * «Evidencia» de la ficha; si ya hay una, la reemplaza.
 */

const adjuntandoEvidencia = ref(false);

const evidenciaForm = useForm({ evidencia_id: '' });

const puedeAdjuntar = computed(() => props.puedeGestionar && props.prueba.estado === 'realizada');

function abrirEvidencia(): void {
    evidenciaForm.evidencia_id = props.prueba.evidencia_id === null ? '' : String(props.prueba.evidencia_id);
    evidenciaForm.clearErrors();
    adjuntandoEvidencia.value = true;
}

function adjuntarEvidencia(): void {
    evidenciaForm.post(`/continuidad/pruebas/${props.prueba.id}/evidencia`, {
        preserveScroll: true,
        onSuccess: () => (adjuntandoEvidencia.value = false),
    });
}

/*
 * --- Las tres costuras: tareas, no conformidades y mejoras ---
 *
 * Mismo patrón que «Abrir acción correctiva» en la ficha de una no
 * conformidad: un diálogo por destino, porque cada uno pide datos distintos.
 * El origen no se pregunta —lo pone `DerivarDePrueba`—, así que ninguno de
 * los tres formularios lleva un campo para elegirlo.
 */

const abriendoTarea = ref(false);

const tareaForm = useForm({
    titulo: '',
    descripcion: props.prueba.conclusiones ?? '',
    prioridad: 'media',
    responsable_id: '',
    fecha_limite: '',
    coste_estimado: '',
});

function abrirTarea(): void {
    tareaForm.reset();
    tareaForm.clearErrors();
    abriendoTarea.value = true;
}

function crearTarea(): void {
    tareaForm.post(`/continuidad/pruebas/${props.prueba.id}/tareas`, {
        preserveScroll: true,
        onSuccess: () => {
            abriendoTarea.value = false;
            tareaForm.reset();
        },
    });
}

const abriendoNoConformidad = ref(false);

/*
 * La descripción arranca con las conclusiones de la prueba, igual que la no
 * conformidad abierta desde un incidente arranca con su descripción: es el
 * mismo hecho y pedirlo otra vez es cómo se acaba con dos versiones de él.
 */
const ncForm = useForm({
    codigo: props.sugerenciaCodigoNoConformidad,
    descripcion: props.prueba.conclusiones ?? '',
    correccion_inmediata: '',
    analisis_causa_raiz: '',
    responsable_id: '',
    fecha_deteccion: props.prueba.fecha_realizacion ?? new Date().toISOString().slice(0, 10),
    fecha_prevista: '',
});

function abrirNoConformidad(): void {
    abriendoNoConformidad.value = true;
}

function crearNoConformidad(): void {
    ncForm.post(`/continuidad/pruebas/${props.prueba.id}/no-conformidades`, { preserveScroll: true });
}

const abriendoMejora = ref(false);

const mejoraForm = useForm({
    codigo: props.sugerenciaCodigoMejora,
    titulo: '',
    descripcion: props.prueba.conclusiones ?? '',
    beneficio_esperado: '',
    responsable_id: '',
    fecha_deteccion: props.prueba.fecha_realizacion ?? new Date().toISOString().slice(0, 10),
    fecha_prevista: '',
});

function abrirMejora(): void {
    abriendoMejora.value = true;
}

function crearMejora(): void {
    mejoraForm.post(`/continuidad/pruebas/${props.prueba.id}/mejoras`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :titulo="prueba.codigo">
        <CabeceraPagina :titulo="prueba.titulo" :codigo="prueba.codigo">
            <template #acciones>
                <Button v-if="puedeGestionar && planificada" as-child variant="outline">
                    <Link :href="`/continuidad/pruebas/${prueba.id}/editar`">
                        <PencilIcon aria-hidden="true" />
                        Editar
                    </Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="-mt-2 flex flex-wrap items-center gap-2">
            <CeldaBadge anunciar
                :valor="{ valor: prueba.estado, etiqueta: prueba.estadoEtiqueta, tono: prueba.estadoTono, icono: prueba.estadoIcono }"
            />
            <CeldaBadge anunciar
                v-if="prueba.resultado"
                :valor="{ valor: prueba.resultado, etiqueta: prueba.resultadoEtiqueta ?? prueba.resultado, tono: prueba.resultadoTono, icono: prueba.resultadoIcono }"
            />
            <span class="text-[13px] text-muted-foreground">
                {{ prueba.tipoEtiqueta }} · {{ fechaLegible(prueba.fecha_realizacion ?? prueba.fecha_prevista) }}
            </span>
        </div>

        <section
            v-if="pendientes.length > 0"
            aria-labelledby="pendientes"
            class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border bg-card px-5 py-3.5"
        >
            <InfoIcon class="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
            <h2 id="pendientes" class="text-sm font-semibold">
                {{ pendientes.length }} {{ pendientes.length === 1 ? 'dato sin completar' : 'datos sin completar' }}
            </h2>
            <p class="text-[13px] text-muted-foreground">
                Una prueba realizada sin evidencia es difícil de demostrar ante el auditor.
            </p>
            <ul class="ml-auto flex flex-wrap gap-2">
                <li v-for="pendiente in pendientes" :key="pendiente">
                    <button
                        v-if="puedeAdjuntar"
                        type="button"
                        class="inline-flex h-7 cursor-pointer items-center rounded-full bg-muted px-2.5 text-[13px] font-medium text-secondary-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                        @click="abrirEvidencia"
                    >
                        {{ pendiente }}
                    </button>
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
                        <CardTitle>Recuperación por servicio</CardTitle>
                        <CardDescription v-if="medidos === 0">
                            Lo que el BIA promete a cada servicio. Lo alcanzado se mide al registrar el resultado.
                        </CardDescription>
                        <p v-else class="text-sm text-secondary-foreground">
                            <template v-if="excedidos > 0">
                                <span class="cifra font-medium text-destructive">{{ excedidos }} de {{ medidos }}</span>
                                {{ excedidos === 1 ? 'servicio volvió' : 'servicios volvieron' }} más tarde de lo que promete su BIA.
                            </template>
                            <template v-else>
                                <span class="cifra font-medium">{{ medidos }} de {{ medidos }}</span>
                                {{ medidos === 1 ? 'servicio volvió' : 'servicios volvieron' }} dentro de lo que promete su BIA.
                            </template>
                        </p>
                    </CardHeader>
                    <CardContent>
                        <ComparativaRecuperacion :servicios="servicios" />
                    </CardContent>
                </Card>

                <Card v-if="prueba.motivo_cancelacion">
                    <CardHeader>
                        <CardTitle>Motivo de la cancelación</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm whitespace-pre-line text-muted-foreground">
                        {{ prueba.motivo_cancelacion }}
                    </CardContent>
                </Card>

                <Card v-if="puedeGestionar && planificada">
                    <CardHeader>
                        <CardTitle>Cerrar la prueba</CardTitle>
                        <CardDescription>
                            La pregunta de op.cont.3 no es «¿hay un plan?», es «¿se ha comprobado?».
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="mostrandoCancelacion"
                                @click="mostrandoResultado = !mostrandoResultado"
                            >
                                Registrar resultado
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                :disabled="mostrandoResultado"
                                @click="mostrandoCancelacion = !mostrandoCancelacion"
                            >
                                Cancelar prueba
                            </Button>
                        </div>

                        <div v-if="mostrandoResultado" class="space-y-4 border-t pt-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <CampoTexto
                                    v-model="fechaRealizacion"
                                    nombre="fecha_realizacion"
                                    etiqueta="Fecha de realización"
                                    tipo="date"
                                    :error="erroresResultado.fecha_realizacion"
                                    requerido
                                />
                                <CampoSelect
                                    v-model="resultado"
                                    nombre="resultado"
                                    etiqueta="Resultado"
                                    :opciones="resultados"
                                    :error="erroresResultado.resultado"
                                    requerido
                                />
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-medium">RTO y RPO alcanzados, por servicio</p>
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[32rem] text-sm">
                                        <thead>
                                            <tr class="border-b text-left text-xs text-muted-foreground">
                                                <th scope="col" class="pb-2 font-medium">Servicio</th>
                                                <th scope="col" class="pb-2 font-medium">RTO objetivo</th>
                                                <th scope="col" class="pb-2 font-medium">RTO alcanzado</th>
                                                <th scope="col" class="pb-2 font-medium">RPO objetivo</th>
                                                <th scope="col" class="pb-2 font-medium">RPO alcanzado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border">
                                            <tr v-for="fila in filasResultado" :key="fila.id">
                                                <th scope="row" class="py-2 pr-2 text-left font-normal">{{ fila.nombre }}</th>
                                                <td class="py-2 pr-2 text-muted-foreground">
                                                    {{ fila.rtoObjetivo === null ? '—' : `${fila.rtoObjetivo} h` }}
                                                </td>
                                                <td class="py-2 pr-2">
                                                    <Input
                                                        v-model="fila.rtoAlcanzado"
                                                        type="number"
                                                        min="0"
                                                        class="w-20"
                                                        :aria-label="`RTO alcanzado por ${fila.nombre}`"
                                                    />
                                                </td>
                                                <td class="py-2 pr-2 text-muted-foreground">
                                                    {{ fila.rpoObjetivo === null ? '—' : `${fila.rpoObjetivo} h` }}
                                                </td>
                                                <td class="py-2">
                                                    <Input
                                                        v-model="fila.rpoAlcanzado"
                                                        type="number"
                                                        min="0"
                                                        class="w-20"
                                                        :aria-label="`RPO alcanzado por ${fila.nombre}`"
                                                    />
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <CampoTextarea
                                v-model="conclusiones"
                                nombre="conclusiones"
                                etiqueta="Conclusiones"
                                :filas="3"
                                :error="erroresResultado.conclusiones"
                                ayuda="Qué salió bien, qué no, y qué habría que cambiar en el plan."
                            />

                            <CampoSelect
                                v-model="evidenciaId"
                                nombre="evidencia_id"
                                etiqueta="Evidencia"
                                :opciones="conOpcionVacia(evidencias, 'Sin evidencia')"
                                :error="erroresResultado.evidencia_id"
                            />

                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="mostrandoResultado = false">
                                    Cancelar
                                </Button>
                                <Button size="sm" :disabled="enviandoResultado" @click="registrarResultado">
                                    Guardar resultado
                                </Button>
                            </div>
                        </div>

                        <div v-if="mostrandoCancelacion" class="space-y-2 border-t pt-4">
                            <CampoTextarea
                                v-model="motivo"
                                nombre="motivo"
                                etiqueta="Motivo de la cancelación"
                                :filas="3"
                                :error="errorCancelacion"
                                requerido
                                ayuda="Por qué una prueba planificada no se llegó a realizar."
                            />
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="mostrandoCancelacion = false">
                                    Volver
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    :disabled="enviandoCancelacion || motivo.trim() === ''"
                                    @click="cancelar"
                                >
                                    Confirmar cancelación
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="prueba.estado === 'realizada'">
                    <CardHeader>
                        <CardTitle>Lo que dejó esta prueba</CardTitle>
                        <CardDescription>
                            op.cont.3 pregunta si se probó, no si salió bien: una prueba parcial o fallida es la que
                            tiene algo que corregir.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <blockquote v-if="prueba.conclusiones" class="space-y-1 border-l-2 pl-4">
                            <p class="text-[13px] font-medium text-secondary-foreground">Conclusiones</p>
                            <p class="max-w-prose text-sm whitespace-pre-line text-pretty text-muted-foreground">
                                {{ prueba.conclusiones }}
                            </p>
                        </blockquote>

                        <EstadoVacio
                            v-if="tareasDerivadas.length === 0 && !noConformidadDerivada"
                            titulo="Nada derivado todavía"
                            :descripcion="
                                puedeDerivar
                                    ? 'Esta prueba puede abrir una tarea, una no conformidad o una oportunidad de mejora.'
                                    : 'Esta prueba se superó: no dejó nada que corregir.'
                            "
                        />

                        <ul
                            v-if="tareasDerivadas.length > 0 || noConformidadDerivada"
                            class="divide-y divide-border border-y"
                        >
                            <li v-for="tarea in tareasDerivadas" :key="tarea.id" class="flex items-center gap-3 py-3">
                                <ListTodoIcon class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <Link
                                        :href="`/tareas/${tarea.id}`"
                                        class="text-sm font-medium underline-offset-4 hover:underline"
                                    >
                                        {{ tarea.titulo }}
                                    </Link>
                                    <span class="text-xs text-muted-foreground">{{ detalleTarea(tarea) }}</span>
                                </div>
                                <CeldaBadge
                                    :valor="{
                                        valor: tarea.estado,
                                        etiqueta: tarea.estadoEtiqueta,
                                        tono: tarea.estadoTono,
                                        icono: tarea.estadoIcono,
                                    }"
                                />
                            </li>
                            <li v-if="noConformidadDerivada" class="flex items-center gap-3 py-3">
                                <FileWarningIcon class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <Link
                                        :href="`/no-conformidades/${noConformidadDerivada.id}`"
                                        class="cifra text-sm font-medium underline-offset-4 hover:underline"
                                    >
                                        {{ noConformidadDerivada.codigo }}
                                    </Link>
                                    <span class="text-xs text-muted-foreground">No conformidad</span>
                                </div>
                                <CeldaBadge
                                    :valor="{
                                        valor: noConformidadDerivada.estado,
                                        etiqueta: noConformidadDerivada.estadoEtiqueta,
                                        tono: noConformidadDerivada.estadoTono,
                                        icono: noConformidadDerivada.estadoIcono,
                                    }"
                                />
                            </li>
                        </ul>

                        <div v-if="puedeDerivar" class="flex flex-wrap gap-2">
                            <Button v-if="puedeAbrirTarea" variant="outline" size="sm" @click="abrirTarea">
                                Abrir tarea
                            </Button>
                            <Button
                                v-if="puedeTratar && !noConformidadDerivada"
                                variant="outline"
                                size="sm"
                                @click="abrirNoConformidad"
                            >
                                Abrir no conformidad
                            </Button>
                            <Button v-if="puedeMejorar" variant="outline" size="sm" @click="abrirMejora">
                                Registrar mejora
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Histórico</CardTitle>
                        <CardDescription>
                            La pregunta del auditor no es «¿se probó?», es «¿cuándo, y con qué resultado?».
                        </CardDescription>
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
                    <CardContent class="space-y-3 text-sm">
                        <p v-if="planificada" class="text-secondary-foreground">
                            Planificada para el {{ fechaLegible(prueba.fecha_prevista) }}
                            <span class="text-muted-foreground">({{ distanciaLegible(prueba.fecha_prevista) }})</span>.
                            Se cierra registrando el resultado o cancelándola.
                        </p>
                        <p v-else-if="prueba.estado === 'realizada'" class="text-secondary-foreground">
                            Realizada y cerrada. Una prueba terminada no se reabre: lo que sigue es planificar la
                            siguiente.
                        </p>
                        <p v-else class="text-secondary-foreground">
                            Cancelada sin llegar a realizarse. Lo que sigue es planificar otra.
                        </p>
                        <Button v-if="puedeGestionar && !planificada" as-child class="w-full">
                            <Link href="/continuidad/pruebas/crear">Planificar la siguiente prueba</Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <dl class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-x-3 gap-y-2.5">
                            <dt class="text-muted-foreground">Tipo</dt>
                            <dd class="flex flex-col gap-0.5">
                                <span>{{ prueba.tipoEtiqueta }}</span>
                                <span class="text-xs text-muted-foreground">{{ prueba.tipoDescripcion }}</span>
                            </dd>
                            <dt class="text-muted-foreground">Responsable</dt>
                            <dd :class="prueba.responsable ? undefined : 'text-muted-foreground'">
                                {{ prueba.responsable ?? 'Sin asignar' }}
                            </dd>
                            <dt class="text-muted-foreground">Prevista</dt>
                            <dd class="cifra">{{ fechaLegible(prueba.fecha_prevista) }}</dd>
                            <template v-if="prueba.fecha_realizacion">
                                <dt class="text-muted-foreground">Realizada</dt>
                                <dd class="flex flex-col gap-0.5">
                                    <span class="cifra">{{ fechaLegible(prueba.fecha_realizacion) }}</span>
                                    <span v-if="desvio" class="text-xs text-muted-foreground">{{ desvio }}</span>
                                </dd>
                            </template>
                            <template v-if="prueba.estado === 'realizada'">
                                <dt class="text-muted-foreground">Evidencia</dt>
                                <dd class="flex flex-wrap items-baseline gap-x-1.5">
                                    <Link
                                        v-if="prueba.evidencia_id !== null"
                                        :href="`/evidencias/${prueba.evidencia_id}`"
                                        class="text-primary underline-offset-4 hover:underline"
                                    >
                                        {{ prueba.evidencia }}
                                    </Link>
                                    <span v-else class="text-muted-foreground">Sin evidencia</span>
                                    <template v-if="puedeAdjuntar">
                                        <span aria-hidden="true" class="text-muted-foreground">·</span>
                                        <button
                                            type="button"
                                            class="cursor-pointer text-primary underline-offset-4 hover:underline"
                                            @click="abrirEvidencia"
                                        >
                                            {{ prueba.evidencia_id === null ? 'Adjuntar' : 'Cambiar' }}
                                        </button>
                                    </template>
                                </dd>
                            </template>
                        </dl>
                    </CardContent>
                </Card>

                <Card v-if="prueba.plan">
                    <CardHeader>
                        <CardTitle>Plan que pone a prueba</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <Link :href="`/documentos/${prueba.documento_id}`" class="flex items-center gap-2.5 underline-offset-4 hover:underline">
                            <FileTextIcon class="size-4 shrink-0 text-marca-500" aria-hidden="true" />
                            <span class="flex flex-col">
                                <span>{{ prueba.plan.titulo }}</span>
                                <span class="cifra text-xs text-muted-foreground">{{ prueba.plan.codigo }}</span>
                            </span>
                        </Link>
                    </CardContent>
                </Card>

                <Card v-if="servicios.length > 0">
                    <CardHeader>
                        <CardTitle>BIA de los servicios</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y divide-border text-sm">
                            <li
                                v-for="servicio in servicios"
                                :key="servicio.id"
                                class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0"
                            >
                                <Link
                                    v-if="servicio.biaId"
                                    :href="`/continuidad/bia/${servicio.biaId}`"
                                    class="min-w-0 truncate underline-offset-4 hover:underline"
                                >
                                    {{ servicio.nombre }}
                                </Link>
                                <span v-else class="min-w-0 truncate">{{ servicio.nombre }}</span>
                                <span class="cifra shrink-0 text-xs text-muted-foreground">
                                    <template v-if="servicio.rtoObjetivo !== null">
                                        RTO {{ servicio.rtoObjetivo }} h · RPO {{ servicio.rpoObjetivo }} h
                                    </template>
                                    <template v-else>Sin BIA</template>
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="adjuntandoEvidencia">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ prueba.evidencia_id === null ? 'Adjuntar evidencia' : 'Cambiar la evidencia' }}</DialogTitle>
                    <DialogDescription>
                        Lo que demuestra que la prueba se hizo como dice el resultado: el informe del restore, el acta
                        del simulacro. El resultado y lo alcanzado no cambian.
                    </DialogDescription>
                </DialogHeader>

                <CampoSelect
                    v-if="evidencias.length > 0"
                    v-model="evidenciaForm.evidencia_id"
                    nombre="evidencia_id"
                    etiqueta="Evidencia"
                    :opciones="evidencias"
                    :error="evidenciaForm.errors.evidencia_id"
                    requerido
                />
                <EstadoVacio
                    v-else
                    titulo="Sin evidencias registradas"
                    descripcion="Súbela primero al registro de evidencias y vuelve a adjuntarla aquí."
                    :accion="{ etiqueta: 'Registrar evidencia', href: '/evidencias/crear' }"
                />

                <DialogFooter>
                    <Button variant="outline" @click="adjuntandoEvidencia = false">Cancelar</Button>
                    <Button
                        v-if="evidencias.length > 0"
                        :disabled="evidenciaForm.processing"
                        @click="adjuntarEvidencia"
                    >
                        Adjuntar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="abriendoTarea">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Abrir tarea</DialogTitle>
                    <DialogDescription>
                        Nace en el plan de acción con el origen ya puesto: prueba de continuidad.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoTexto
                        nombre="titulo"
                        etiqueta="Título"
                        :error="tareaForm.errors.titulo"
                        requerido
                        @input="tareaForm.titulo = ($event.target as HTMLInputElement).value"
                    />
                    <CampoSelect
                        nombre="prioridad"
                        etiqueta="Prioridad"
                        :opciones="prioridades"
                        :valor-inicial="tareaForm.prioridad"
                        :error="tareaForm.errors.prioridad"
                        requerido
                        @update:model-value="(valor?: string) => (tareaForm.prioridad = valor ?? 'media')"
                    />
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :error="tareaForm.errors.responsable_id"
                        @update:model-value="(valor?: string) => (tareaForm.responsable_id = valor ?? '')"
                    />
                    <CampoTexto
                        nombre="fecha_limite"
                        etiqueta="Fecha límite"
                        tipo="date"
                        :error="tareaForm.errors.fecha_limite"
                        @input="tareaForm.fecha_limite = ($event.target as HTMLInputElement).value"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abriendoTarea = false">Cancelar</Button>
                    <Button :disabled="tareaForm.processing" @click="crearTarea">Abrir</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="abriendoNoConformidad">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Abrir no conformidad</DialogTitle>
                    <DialogDescription>
                        Con el origen y la prueba ya puestos: la cláusula 10.2 pide causa raíz y
                        verificación de eficacia, y eso se lleva desde la propia ficha.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoTexto
                        v-model="ncForm.codigo"
                        nombre="codigo"
                        etiqueta="Código"
                        :error="ncForm.errors.codigo"
                        requerido
                    />
                    <CampoTextarea
                        v-model="ncForm.descripcion"
                        nombre="descripcion"
                        etiqueta="Descripción"
                        :filas="3"
                        :error="ncForm.errors.descripcion"
                        requerido
                    />
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :error="ncForm.errors.responsable_id"
                        @update:model-value="(valor?: string) => (ncForm.responsable_id = valor ?? '')"
                    />
                    <CampoTexto
                        v-model="ncForm.fecha_deteccion"
                        nombre="fecha_deteccion"
                        etiqueta="Fecha de detección"
                        tipo="date"
                        :error="ncForm.errors.fecha_deteccion"
                        requerido
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abriendoNoConformidad = false">Cancelar</Button>
                    <Button :disabled="ncForm.processing" @click="crearNoConformidad">Abrir</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="abriendoMejora">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registrar oportunidad de mejora</DialogTitle>
                    <DialogDescription>
                        Con el origen ya puesto: la lección aprendida de esta prueba, sin volver a
                        escribirla en otro sitio.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoTexto
                        v-model="mejoraForm.codigo"
                        nombre="codigo"
                        etiqueta="Código"
                        :error="mejoraForm.errors.codigo"
                        requerido
                    />
                    <CampoTexto
                        v-model="mejoraForm.titulo"
                        nombre="titulo"
                        etiqueta="Título"
                        :error="mejoraForm.errors.titulo"
                        requerido
                    />
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :error="mejoraForm.errors.responsable_id"
                        @update:model-value="(valor?: string) => (mejoraForm.responsable_id = valor ?? '')"
                    />
                    <CampoTexto
                        v-model="mejoraForm.fecha_deteccion"
                        nombre="fecha_deteccion"
                        etiqueta="Fecha de detección"
                        tipo="date"
                        :error="mejoraForm.errors.fecha_deteccion"
                        requerido
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abriendoMejora = false">Cancelar</Button>
                    <Button :disabled="mejoraForm.processing" @click="crearMejora">Registrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
