<script setup lang="ts">
import IconoTipo from '@/components/IconoTipo.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { distanciaLegible, fechaLegible } from '@/lib/celdas';
import { tono } from '@/lib/tonos';
import { Link } from '@inertiajs/vue3';
import { CalendarXIcon, CircleHelpIcon, ClipboardListIcon, Link2Icon, TriangleAlertIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Lo que falta para homologar a un proveedor, arriba de su ficha.
 *
 * Es el elemento fuerte de la pantalla (DESIGN.md § 1): lo demás de la ficha
 * explica cómo se llegó aquí, y esto dice qué hacer ahora. Lo calcula
 * `PendientesDeProveedor` en el servidor; aquí sólo se pinta.
 *
 * **El rojo, sólo en lo que ya va mal**: la reevaluación vencida y el
 * certificado caducado. La condición lleva el tono de la evaluación y las
 * contradicciones van en gris, porque no dicen que algo esté mal, dicen que
 * ficha y evaluación no pueden tener razón las dos.
 */
export interface TareaPendiente {
    id: number;
    titulo: string;
    estado: string;
    tono: string;
    icono: string;
    responsable: string | null;
    plazo: { fecha: string | null; etiqueta: string; tono: string };
}

export interface Pendiente {
    sinEvaluar: boolean;
    reevaluacionVencida: string | null;
    certificacionesCaducadas: { id: number; etiqueta: string; caducaEn: string | null }[];
    condicion: {
        fecha: string;
        resultado: string;
        tono: string;
        icono: string;
        conclusiones: string | null;
        incumplidas: { codigo: string; titulo: string; nota: string | null }[];
    } | null;
    tareasAbiertas: TareaPendiente[];
    contradicciones: {
        codigo: string;
        titulo: string;
        clave: string;
        dato: string;
        valor: string;
        resultado: string;
        motivo: string;
        sugerencia: string;
    }[];
    total: number;
}

const props = defineProps<{
    pendiente: Pendiente;
    proveedorId: number;
    homologado: boolean;
    puedeGestionar: boolean;
    puedeEvaluar: boolean;
    puedeAbrirTarea: boolean;
}>();

defineEmits<{ registrarCertificacion: []; abrirTarea: [] }>();

const titulo = computed(() => (props.homologado ? 'Qué hay pendiente' : 'Qué falta para homologarlo'));

const descripcion = computed(() => {
    if (props.pendiente.total === 0) {
        return 'Nada. La última evaluación fue apta y ningún certificado registrado ha caducado.';
    }

    return props.pendiente.condicion
        ? `Lo que dejó abierto la evaluación del ${fechaLegible(props.pendiente.condicion.fecha)} y lo que ha caducado desde entonces. Resuelto, se evalúa otra vez: no hace falta esperar a la fecha.`
        : 'Lo que ha caducado o no casa desde la última evaluación.';
});

const tonoCondicion = computed(() => tono(props.pendiente.condicion?.tono).badge);

function plazoDe(tarea: TareaPendiente) {
    return {
        valor: tarea.plazo.etiqueta,
        etiqueta: tarea.plazo.fecha ? `${tarea.plazo.etiqueta} · ${fechaLegible(tarea.plazo.fecha)}` : tarea.plazo.etiqueta,
        tono: tarea.plazo.tono,
        icono: tarea.plazo.tono === 'caducada' ? 'CalendarX' : 'CalendarClock',
    };
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ titulo }}</CardTitle>
            <CardDescription class="max-w-2xl text-pretty">{{ descripcion }}</CardDescription>
        </CardHeader>

        <CardContent v-if="pendiente.total > 0">
            <ol class="divide-y rounded-lg border">
                <li v-if="pendiente.sinEvaluar" class="flex flex-wrap items-start gap-3 p-4">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground" aria-hidden="true">
                        <ClipboardListIcon class="size-4" />
                    </span>
                    <div class="grid min-w-0 flex-1 gap-1">
                        <p class="font-medium">Falta la primera evaluación</p>
                        <p class="text-sm text-muted-foreground">
                            Nunca se ha comprobado su contrato. Hasta entonces sigue «en evaluación» y no tiene fecha de
                            reevaluación.
                        </p>
                    </div>
                    <Button v-if="puedeEvaluar" as-child variant="outline" size="sm" class="self-center">
                        <Link :href="`/proveedores/${proveedorId}/evaluar`">Evaluar</Link>
                    </Button>
                </li>

                <li v-if="pendiente.reevaluacionVencida" class="flex flex-wrap items-start gap-3 bg-destructive/5 p-4">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive" aria-hidden="true">
                        <CalendarXIcon class="size-4" />
                    </span>
                    <div class="grid min-w-0 flex-1 gap-1">
                        <p class="font-medium">
                            La reevaluación venció el {{ fechaLegible(pendiente.reevaluacionVencida) }}
                            <span class="font-normal text-muted-foreground">({{ distanciaLegible(pendiente.reevaluacionVencida) }})</span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Lo que se comprobó la última vez ya no cubre el plazo que la organización fija para su criticidad.
                        </p>
                    </div>
                </li>

                <li
                    v-for="certificado in pendiente.certificacionesCaducadas"
                    :key="`cert-${certificado.id}`"
                    class="flex flex-wrap items-start gap-3 bg-destructive/5 p-4"
                >
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive" aria-hidden="true">
                        <TriangleAlertIcon class="size-4" />
                    </span>
                    <div class="grid min-w-0 flex-1 gap-1">
                        <p class="font-medium">
                            El certificado {{ certificado.etiqueta }} caducó el {{ fechaLegible(certificado.caducaEn) }}
                            <span class="font-normal text-muted-foreground">({{ distanciaLegible(certificado.caducaEn) }})</span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Se sigue trabajando con él, así que ya sale en rojo en el panel.
                        </p>
                    </div>
                    <Button v-if="puedeGestionar" variant="outline" size="sm" class="self-center" @click="$emit('registrarCertificacion')">
                        Registrar el vigente
                    </Button>
                </li>

                <li v-if="pendiente.condicion" class="flex flex-wrap items-start gap-3 p-4">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full" :class="tonoCondicion" aria-hidden="true">
                        <IconoTipo :nombre="pendiente.condicion.icono" clase="size-4" />
                    </span>
                    <div class="grid min-w-0 flex-1 gap-3">
                        <div class="grid gap-1">
                            <p class="font-medium">
                                La condición de la última evaluación
                                <span class="font-normal text-muted-foreground">
                                    · {{ pendiente.condicion.resultado.toLowerCase() }}
                                </span>
                            </p>
                            <ul v-if="pendiente.condicion.incumplidas.length > 0" class="grid gap-0.5 text-sm">
                                <li v-for="clausula in pendiente.condicion.incumplidas" :key="clausula.codigo">
                                    <span class="cifra text-xs text-muted-foreground">{{ clausula.codigo }}</span>
                                    {{ clausula.titulo }} no se cumple<template v-if="clausula.nota">: {{ clausula.nota }}</template>
                                </li>
                            </ul>
                        </div>

                        <blockquote
                            v-if="pendiente.condicion.conclusiones"
                            class="border-l-2 pl-3 text-sm whitespace-pre-line text-secondary-foreground"
                        >
                            {{ pendiente.condicion.conclusiones }}
                        </blockquote>

                        <ul v-if="pendiente.tareasAbiertas.length > 0" class="grid gap-2 text-sm" aria-label="Tareas abiertas">
                            <li v-for="tarea in pendiente.tareasAbiertas" :key="tarea.id" class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <Link2Icon class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <Link :href="`/tareas/${tarea.id}`" class="underline underline-offset-4">{{ tarea.titulo }}</Link>
                                <CeldaBadge :valor="{ valor: tarea.estado, etiqueta: tarea.estado, tono: tarea.tono, icono: tarea.icono }" />
                                <CeldaBadge :valor="plazoDe(tarea)" />
                                <span class="text-muted-foreground">· {{ tarea.responsable ?? 'Sin responsable' }}</span>
                            </li>
                        </ul>
                        <div v-else class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                            <span>Nadie tiene asignado levantarla.</span>
                            <Button v-if="puedeAbrirTarea" variant="outline" size="sm" @click="$emit('abrirTarea')">
                                Abrir una tarea
                            </Button>
                        </div>
                    </div>
                </li>

                <li v-if="pendiente.contradicciones.length > 0" class="flex flex-wrap items-start gap-3 p-4">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground" aria-hidden="true">
                        <CircleHelpIcon class="size-4" />
                    </span>
                    <div class="grid min-w-0 flex-1 gap-3">
                        <div class="grid gap-1">
                            <p class="font-medium">
                                {{
                                    pendiente.contradicciones.length === 1
                                        ? 'La ficha y la última evaluación dicen cosas distintas'
                                        : `La ficha y la última evaluación dicen cosas distintas en ${pendiente.contradicciones.length} puntos`
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Las dos no pueden tener razón. Cuál está mal lo sabe quien leyó el contrato: se corrige la
                                ficha o se evalúa otra vez.
                            </p>
                        </div>
                        <ul class="grid gap-3">
                            <li v-for="una in pendiente.contradicciones" :key="una.codigo" class="grid gap-2 rounded-md bg-superficie p-3 text-sm">
                                <p class="font-medium">
                                    <span class="cifra text-xs font-normal text-muted-foreground">{{ una.codigo }}</span>
                                    {{ una.titulo }}
                                </p>
                                <dl class="grid gap-x-4 gap-y-1 sm:grid-cols-2">
                                    <div class="grid gap-0.5">
                                        <dt class="text-xs text-muted-foreground">En la ficha · {{ una.dato }}</dt>
                                        <dd class="font-medium">{{ una.valor }}</dd>
                                    </div>
                                    <div class="grid gap-0.5">
                                        <dt class="text-xs text-muted-foreground">En la evaluación</dt>
                                        <dd class="font-medium">{{ una.resultado }}</dd>
                                    </div>
                                </dl>
                                <p class="text-secondary-foreground">{{ una.motivo }}</p>
                                <p class="text-muted-foreground">{{ una.sugerencia }}</p>
                            </li>
                        </ul>
                        <div class="flex flex-wrap gap-2">
                            <Button v-if="puedeGestionar" as-child variant="outline" size="sm">
                                <Link :href="`/proveedores/${proveedorId}/editar`">Corregir la ficha</Link>
                            </Button>
                            <Button v-if="puedeEvaluar" as-child variant="outline" size="sm">
                                <Link :href="`/proveedores/${proveedorId}/evaluar`">Evaluar otra vez</Link>
                            </Button>
                        </div>
                    </div>
                </li>
            </ol>
        </CardContent>
    </Card>
</template>
