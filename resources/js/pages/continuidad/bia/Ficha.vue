<script setup lang="ts">
import BotonEstado from '@/components/BotonEstado.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import TramosImpacto, { type Tramo } from '@/components/continuidad/TramosImpacto.vue';
import { CalendarIcon, CornerDownRightIcon, FileTextIcon, FlaskConicalIcon, NetworkIcon, PencilIcon } from '@lucide/vue';
import HistoricoTransiciones, {
    type Transicion,
} from '@/components/HistoricoTransiciones.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { distanciaLegible, fechaLegible } from '@/lib/celdas';
import { tono } from '@/lib/tonos';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Destino {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
    exigeMotivo: boolean;
    permiso: string;
}

interface Dependencia {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
    tipoTono: string;
    tipoIcono: string;
    profundidad: number;
}

interface Bia {
    id: number;
    activo_id: number;
    servicio: string | null;
    servicioCodigo: string | null;
    rto_horas: number;
    rpo_horas: number;
    justificacion: string | null;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    responsable: string | null;
    aprobadoPor: string | null;
    fechaAprobacion: string | null;
    fechaRevision: string | null;
    rtoIncoherente: boolean;
}

interface PruebaDelServicio {
    id: number;
    codigo: string;
    titulo: string;
    tipoEtiqueta: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    resultado: { valor: string; etiqueta: string; tono: string; icono: string } | null;
    /** Lo que tardó este servicio en volver en esa prueba; `null` mientras no se haya medido. */
    rtoAlcanzado: number | null;
    /** `true` = se pasó del RTO, `false` = cumplió, `null` = falta un dato para decirlo. */
    excedeRto: boolean | null;
    fecha: string;
}

/**
 * La ficha del BIA de un servicio: § 4.11.
 *
 * **Una tarjeta fuerte, «Cuánto aguanta caído».** Contesta a lo que trae a
 * alguien aquí —«¿qué tan grave es no tener esto, y cuánto tiempo nos hemos
 * dado para recuperarlo?»— con las tres cifras (RTO, MTPD y RPO), los cinco
 * tramos con el RTO y el umbral puestos encima, la justificación y lo que
 * midió la última prueba. Antes eran dos tarjetas —los tramos y «RTO, RPO y
 * justificación»— y el margen entre el RTO y el MTPD había que calcularlo a
 * mano.
 *
 * **La última prueba enfrenta la promesa al dato.** Es el único rojo de la
 * ficha, y sólo cuando el RTO alcanzado supera el objetivo: un incumplimiento
 * medido (`ComparativaRecuperacion` aplica la misma regla en la ficha de la
 * prueba).
 *
 * **Estado arriba en la columna lateral, histórico en la principal**, como el
 * resto de fichas (DESIGN.md § 9). El BIA tenía los dos juntos en una tarjeta
 * «Ciclo»; se separaron con el rediseño.
 *
 * **Aprobar está detrás de `puedeAprobar`, no de `puedeGestionar`.** Aceptar
 * un RTO es aceptar un riesgo: el controlador lo exige con `abort_unless`, y
 * aquí sólo se oculta el botón para que quien no puede aprobar no lo vea y
 * choque con un 403.
 */
const props = defineProps<{
    bia: Bia;
    umbral: { tramo: string | null; etiqueta: string | null; horas: number | null };
    tramos: Tramo[];
    pesoMaximo: number;
    dependencias: Dependencia[];
    planes: { id: number; codigo: string; titulo: string; aprobado: boolean }[];
    pruebas: PruebaDelServicio[];
    transiciones: Destino[];
    historial: Transicion[];
    puedeGestionar: boolean;
    puedeAprobar: boolean;
}>();

/** La prueba más reciente que midió este servicio: la que dice si el RTO se sostiene. */
const ultimaMedida = computed(() => props.pruebas.find((prueba) => prueba.rtoAlcanzado !== null) ?? null);

/* --- El estado --- */

const destino = ref<Destino | null>(null);
const nota = ref('');
const enviando = ref(false);

/**
 * Cada transición lleva su propio permiso: `continuidad.aprobar` para pasar a
 * «aprobado», `continuidad.gestionar` para el resto. Filtrar por un único
 * `puedeGestionar` dejaría ver el botón de aprobar a quien no puede pulsarlo.
 */
const disponibles = computed(() =>
    props.transiciones.filter((paso) =>
        paso.permiso === 'continuidad.aprobar' ? props.puedeAprobar : props.puedeGestionar,
    ),
);

function mover(paso: Destino): void {
    if (paso.exigeMotivo && destino.value?.valor !== paso.valor) {
        destino.value = paso;
        nota.value = '';

        return;
    }

    enviando.value = true;

    router.post(
        `/continuidad/bia/${props.bia.id}/estado`,
        { estado: paso.valor, nota: nota.value },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
                destino.value = null;
            },
        },
    );
}

/** Lo que dice el estado en una frase: desde cuándo y quién, si está aprobado. */
const fraseEstado = computed(() => {
    if (props.bia.estado === 'aprobado' && props.bia.fechaAprobacion) {
        return `Desde el ${fechaLegible(props.bia.fechaAprobacion)}${props.bia.aprobadoPor ? `, por ${props.bia.aprobadoPor}` : ''}.`;
    }

    if (props.bia.estado === 'borrador') {
        return 'Sin aprobar: el RTO todavía no es un compromiso de la organización.';
    }

    return 'Fuera de uso: el servicio ya no promete nada.';
});
</script>

<template>
    <AppLayout :titulo="bia.servicio ?? 'BIA'">
        <CabeceraPagina
            :titulo="bia.servicio ?? 'BIA'"
            :codigo="bia.servicioCodigo"
            descripcion="Análisis de impacto del servicio · cinco tramos, RTO y RPO"
        >
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/continuidad/bia/${bia.id}/editar`">
                        <PencilIcon aria-hidden="true" />
                        Editar
                    </Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Cuánto aguanta caído</CardTitle>
                        <CardDescription>
                            El impacto de no tener el servicio en cada tramo, con lo que el BIA promete encima.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <dl class="grid rounded-xl border sm:grid-cols-3">
                            <div class="flex flex-col gap-1 px-5 py-4">
                                <dt class="text-[13px] font-medium text-muted-foreground">RTO · se recupera en</dt>
                                <dd class="cifra text-3xl font-medium">{{ bia.rto_horas }} h</dd>
                                <dd class="text-xs text-muted-foreground">
                                    {{ bia.estado === 'aprobado' ? 'Objetivo aprobado' : 'Objetivo sin aprobar' }}
                                </dd>
                            </div>
                            <div class="flex flex-col gap-1 border-t px-5 py-4 sm:border-t-0 sm:border-l">
                                <dt class="text-[13px] font-medium text-muted-foreground">MTPD · aguanta hasta</dt>
                                <dd class="cifra text-3xl font-medium" :class="umbral.etiqueta ? 'text-primary' : 'text-muted-foreground'">
                                    {{ umbral.etiqueta ?? 'Sin umbral' }}
                                </dd>
                                <dd class="text-xs text-muted-foreground">
                                    {{ umbral.etiqueta ? 'Primer tramo «muy alto», derivado' : 'Ningún tramo llega a «muy alto»' }}
                                </dd>
                            </div>
                            <div class="flex flex-col gap-1 border-t px-5 py-4 sm:border-t-0 sm:border-l">
                                <dt class="text-[13px] font-medium text-muted-foreground">RPO · pierde como mucho</dt>
                                <dd class="cifra text-3xl font-medium">{{ bia.rpo_horas }} h</dd>
                                <dd class="text-xs text-muted-foreground">De datos, desde la última copia</dd>
                            </div>
                        </dl>

                        <TramosImpacto
                            :tramos="tramos"
                            :peso-maximo="pesoMaximo"
                            :rto-horas="bia.rto_horas"
                            :umbral-horas="umbral.horas"
                            :rto-incoherente="bia.rtoIncoherente"
                        />

                        <blockquote v-if="bia.justificacion" class="space-y-1 border-l-2 pl-4">
                            <p class="text-[13px] font-medium text-secondary-foreground">Justificación</p>
                            <p class="max-w-prose text-sm whitespace-pre-line text-pretty text-muted-foreground">
                                {{ bia.justificacion }}
                            </p>
                        </blockquote>

                        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-superficie px-4 py-3 text-sm">
                            <FlaskConicalIcon class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <p v-if="ultimaMedida" class="flex-1 text-secondary-foreground">
                                Última prueba,
                                <Link :href="`/continuidad/pruebas/${ultimaMedida.id}`" class="cifra text-primary underline-offset-4 hover:underline">
                                    {{ ultimaMedida.codigo }}
                                </Link>
                                del {{ fechaLegible(ultimaMedida.fecha) }}: volvió en
                                <span class="cifra font-medium" :class="ultimaMedida.excedeRto ? 'text-destructive' : undefined">
                                    {{ ultimaMedida.rtoAlcanzado }} h</span
                                ><template v-if="ultimaMedida.excedeRto">,
                                    {{ (ultimaMedida.rtoAlcanzado ?? 0) - bia.rto_horas }} h por encima del RTO.</template
                                ><template v-else>, dentro del RTO.</template>
                            </p>
                            <p v-else class="flex-1 text-muted-foreground">
                                Ninguna prueba ha medido todavía este RTO: sin probar es una promesa, no un dato.
                            </p>
                            <CeldaBadge
                                v-if="ultimaMedida?.excedeRto"
                                :valor="{ valor: 'caducada', etiqueta: 'Por encima del RTO', tono: 'caducada' }"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Pruebas que cubren este servicio</CardTitle>
                        <CardDescription>Un RTO sin probar es una promesa, no un dato.</CardDescription>
                        <CardAction v-if="puedeGestionar">
                            <Button as-child variant="outline" size="sm">
                                <Link href="/continuidad/pruebas/crear">Planificar prueba</Link>
                            </Button>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <EstadoVacio
                            v-if="pruebas.length === 0"
                            :icono="FlaskConicalIcon"
                            titulo="Sin pruebas registradas"
                            descripcion="Ninguna prueba de continuidad ha cubierto todavía este servicio."
                        />
                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Prueba</TableHead>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead class="text-right">Fecha</TableHead>
                                    <TableHead class="text-right">RTO alcanzado</TableHead>
                                    <TableHead>Resultado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="prueba in pruebas" :key="prueba.id">
                                    <TableCell class="whitespace-normal">
                                        <Link :href="`/continuidad/pruebas/${prueba.id}`" class="flex flex-col underline-offset-4 hover:underline">
                                            <span class="cifra text-xs text-muted-foreground">{{ prueba.codigo }}</span>
                                            <span>{{ prueba.titulo }}</span>
                                        </Link>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">{{ prueba.tipoEtiqueta }}</TableCell>
                                    <TableCell class="cifra text-right text-muted-foreground">{{ fechaLegible(prueba.fecha) }}</TableCell>
                                    <TableCell class="cifra text-right">
                                        <template v-if="prueba.rtoAlcanzado !== null">
                                            <span :class="prueba.excedeRto ? 'font-medium text-destructive' : undefined">{{ prueba.rtoAlcanzado }} h</span>
                                            <span class="text-muted-foreground"> / {{ bia.rto_horas }} h</span>
                                        </template>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </TableCell>
                                    <TableCell>
                                        <CeldaBadge v-if="prueba.resultado" :valor="prueba.resultado" />
                                        <CeldaBadge
                                            v-else
                                            :valor="{
                                                valor: prueba.estado,
                                                etiqueta: prueba.estadoEtiqueta,
                                                tono: prueba.estadoTono,
                                                icono: prueba.estadoIcono,
                                            }"
                                        />
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Histórico</CardTitle>
                        <CardDescription>
                            La pregunta del auditor no es «¿está aprobado?», es «¿desde cuándo?».
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
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <CeldaBadge anunciar
                                :valor="{
                                    valor: bia.estado,
                                    etiqueta: bia.estadoEtiqueta,
                                    tono: bia.estadoTono,
                                    icono: bia.estadoIcono,
                                }"
                            />
                            <p class="text-sm text-secondary-foreground">{{ fraseEstado }}</p>
                        </div>

                        <div v-if="bia.fechaRevision" class="flex items-center gap-3 rounded-xl border px-3 py-2.5">
                            <CalendarIcon class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <div class="flex flex-col">
                                <span class="text-[13px] font-medium">Revisión el {{ fechaLegible(bia.fechaRevision) }}</span>
                                <span class="text-xs text-muted-foreground first-letter:uppercase">{{ distanciaLegible(bia.fechaRevision) }}</span>
                            </div>
                        </div>

                        <div v-if="disponibles.length > 0" class="flex flex-wrap gap-2 border-t pt-4">
                            <BotonEstado
                                v-for="paso in disponibles"
                                :key="paso.valor"
                                :destino="paso"
                                :deshabilitado="enviando"
                                @click="mover(paso)"
                            />
                        </div>

                        <div v-if="destino" class="space-y-2 border-t pt-4">
                            <CampoTexto
                                v-model="nota"
                                nombre="nota"
                                :etiqueta="`Por qué pasa a «${destino.etiqueta}»`"
                                ayuda="Dejar de estar vigente sin nota es dejar sin explicar por qué el RTO en el que todos confiaban ya no vale."
                            />
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="destino = null">
                                    Cancelar
                                </Button>
                                <Button
                                    size="sm"
                                    :disabled="enviando || nota.trim() === ''"
                                    @click="mover(destino)"
                                >
                                    Confirmar
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <dl class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-2.5">
                            <dt class="text-muted-foreground">Servicio</dt>
                            <dd>
                                <Link :href="`/activos/${bia.activo_id}`" class="text-primary underline-offset-4 hover:underline">
                                    {{ bia.servicio ?? '—' }}
                                </Link>
                            </dd>
                            <dt class="text-muted-foreground">Responsable</dt>
                            <dd :class="bia.responsable ? undefined : 'text-muted-foreground'">{{ bia.responsable ?? 'Sin asignar' }}</dd>
                            <template v-if="bia.aprobadoPor">
                                <dt class="text-muted-foreground">Aprobado por</dt>
                                <dd>{{ bia.aprobadoPor }}</dd>
                            </template>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Lo que necesita</CardTitle>
                        <CardDescription>Si cae cualquiera de estos, cae el servicio.</CardDescription>
                        <CardAction v-if="dependencias.length > 0">
                            <Link
                                :href="`/activos/${bia.activo_id}/grafo`"
                                class="text-[13px] font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Ver grafo
                            </Link>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <EstadoVacio
                            v-if="dependencias.length === 0"
                            :icono="NetworkIcon"
                            titulo="Sin dependencias declaradas"
                            descripcion="El grafo de activos no tiene nada colgando de este servicio todavía."
                        />
                        <ul v-else class="space-y-1 text-sm">
                            <li
                                v-for="activo in dependencias"
                                :key="activo.id"
                                class="flex items-center gap-2 py-1.5"
                                :style="{ paddingLeft: `${(Math.max(activo.profundidad, 1) - 1) * 1.25}rem` }"
                            >
                                <CornerDownRightIcon
                                    v-if="activo.profundidad > 1"
                                    class="size-3.5 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span
                                    class="flex size-6 shrink-0 items-center justify-center rounded-md"
                                    :class="tono(activo.tipoTono).badge"
                                >
                                    <IconoTipo :nombre="activo.tipoIcono" clase="size-3.5" />
                                </span>
                                <div class="flex min-w-0 flex-col">
                                    <Link :href="`/activos/${activo.id}`" class="truncate underline-offset-4 hover:underline">
                                        {{ activo.nombre }}
                                    </Link>
                                    <span class="text-xs text-muted-foreground">
                                        <span class="cifra">{{ activo.codigo }}</span> · {{ activo.tipo }}
                                    </span>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Plan que lo cubre</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <EstadoVacio
                            v-if="planes.length === 0"
                            :icono="FileTextIcon"
                            titulo="Sin plan vinculado"
                            descripcion="Ningún documento de continuidad cuelga todavía de este servicio."
                        />
                        <ul v-else class="space-y-3">
                            <li v-for="plan in planes" :key="plan.id" class="flex items-center justify-between gap-3 text-sm">
                                <Link :href="`/documentos/${plan.id}`" class="flex items-center gap-2.5 underline-offset-4 hover:underline">
                                    <FileTextIcon class="size-4 shrink-0 text-marca-500" aria-hidden="true" />
                                    <span class="flex flex-col">
                                        <span>{{ plan.titulo }}</span>
                                        <span class="cifra text-xs text-muted-foreground">{{ plan.codigo }}</span>
                                    </span>
                                </Link>
                                <CeldaBadge
                                    :valor="{
                                        valor: plan.aprobado ? 'aprobado' : 'sin_aprobar',
                                        etiqueta: plan.aprobado ? 'Aprobado' : 'Sin aprobar',
                                        tono: plan.aprobado ? 'implantado' : 'no_iniciado',
                                    }"
                                />
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
