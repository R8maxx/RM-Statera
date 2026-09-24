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
import { conOpcionVacia, SIN_VALOR } from '@/lib/formularios';
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
 * **«Editar» sólo aparece mientras la prueba sigue planificada.** El
 * guardián de verdad está en `PruebaContinuidadController::update()`; esto es
 * sólo evitarle a alguien un 403 con el que no contaba.
 */
const props = defineProps<{
    prueba: Prueba;
    servicios: ServicioComparado[];
    historial: Transicion[];
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
                    <Link :href="`/continuidad/pruebas/${prueba.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{ valor: prueba.estado, etiqueta: prueba.estadoEtiqueta, tono: prueba.estadoTono, icono: prueba.estadoIcono }"
            />
            <CeldaBadge
                :valor="{ valor: prueba.tipo, etiqueta: prueba.tipoEtiqueta, tono: prueba.tipoTono, icono: prueba.tipoIcono }"
            />
            <CeldaBadge
                v-if="prueba.resultado"
                :valor="{ valor: prueba.resultado, etiqueta: prueba.resultadoEtiqueta ?? prueba.resultado, tono: prueba.resultadoTono, icono: prueba.resultadoIcono }"
            />
            <span class="text-sm text-muted-foreground">Prevista: {{ prueba.fechaPrevistaEtiqueta }}</span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Recuperación por servicio</CardTitle>
                        <CardDescription>
                            Lo que el BIA prometía frente a lo que se alcanzó de verdad.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ComparativaRecuperacion :servicios="servicios" />
                    </CardContent>
                </Card>

                <Card v-if="prueba.conclusiones">
                    <CardHeader>
                        <CardTitle>Conclusiones</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm whitespace-pre-line text-muted-foreground">
                        {{ prueba.conclusiones }}
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
                            Una prueba parcial o fallida es la que de verdad tiene algo que corregir:
                            op.cont.3 pregunta si se probó, no si salió bien.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <EstadoVacio
                            v-if="tareasDerivadas.length === 0 && !noConformidadDerivada"
                            titulo="Nada derivado todavía"
                            :descripcion="
                                puedeDerivar
                                    ? 'Esta prueba puede abrir una tarea, una no conformidad o una oportunidad de mejora.'
                                    : 'Esta prueba se superó: no dejó nada que corregir.'
                            "
                        />

                        <ul v-if="tareasDerivadas.length > 0" class="divide-y divide-border">
                            <li
                                v-for="tarea in tareasDerivadas"
                                :key="tarea.id"
                                class="flex items-center justify-between gap-4 py-2"
                            >
                                <Link
                                    :href="`/tareas/${tarea.id}`"
                                    class="text-sm font-medium underline-offset-4 hover:underline"
                                >
                                    {{ tarea.titulo }}
                                </Link>
                                <CeldaBadge
                                    :valor="{
                                        valor: tarea.estado,
                                        etiqueta: tarea.estadoEtiqueta,
                                        tono: tarea.estadoTono,
                                        icono: null,
                                    }"
                                />
                            </li>
                        </ul>

                        <div v-if="noConformidadDerivada" class="flex items-center justify-between gap-4 border-t pt-4">
                            <Link
                                :href="`/no-conformidades/${noConformidadDerivada.id}`"
                                class="cifra text-sm font-medium underline-offset-4 hover:underline"
                            >
                                {{ noConformidadDerivada.codigo }}
                            </Link>
                            <CeldaBadge
                                :valor="{
                                    valor: noConformidadDerivada.estado,
                                    etiqueta: noConformidadDerivada.estadoEtiqueta,
                                    tono: noConformidadDerivada.estadoTono,
                                    icono: noConformidadDerivada.estadoIcono,
                                }"
                            />
                        </div>

                        <div v-if="puedeDerivar" class="flex flex-wrap gap-2 border-t pt-4">
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
                <Card v-if="prueba.responsable || prueba.evidencia">
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <dl class="grid gap-2">
                            <div v-if="prueba.responsable" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Responsable</dt>
                                <dd>{{ prueba.responsable }}</dd>
                            </div>
                            <div v-if="prueba.fechaRealizacionEtiqueta" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Realizada el</dt>
                                <dd>{{ prueba.fechaRealizacionEtiqueta }}</dd>
                            </div>
                            <div v-if="prueba.evidencia" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Evidencia</dt>
                                <dd>{{ prueba.evidencia }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card v-if="prueba.plan">
                    <CardHeader>
                        <CardTitle>Plan de continuidad</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <Link :href="`/documentos/${prueba.documento_id}`" class="underline-offset-4 hover:underline">
                            <span class="cifra">{{ prueba.plan.codigo }}</span> · {{ prueba.plan.titulo }}
                        </Link>
                    </CardContent>
                </Card>

            </div>
        </div>

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
