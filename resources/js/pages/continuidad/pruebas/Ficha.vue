<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import ComparativaRecuperacion, { type ServicioComparado } from '@/components/continuidad/ComparativaRecuperacion.vue';
import HistoricoTransiciones, { type Transicion } from '@/components/implantacion/HistoricoTransiciones.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { conOpcionVacia, SIN_VALOR } from '@/lib/formularios';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
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
    puedeGestionar: boolean;
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
</script>

<template>
    <AppLayout :titulo="prueba.codigo">
        <CabeceraPagina :titulo="prueba.titulo" :descripcion="prueba.codigo">
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
            </div>
        </div>
    </AppLayout>
</template>
