<script setup lang="ts">
import BotonEstado from '@/components/BotonEstado.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import TramosImpacto from '@/components/continuidad/TramosImpacto.vue';
import { FileTextIcon, FlaskConicalIcon, NetworkIcon } from '@lucide/vue';
import HistoricoTransiciones, {
    type Transicion,
} from '@/components/HistoricoTransiciones.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
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

interface Tramo {
    clave: string;
    etiqueta: string;
    horas: number;
    nivel: string;
    nivelEtiqueta: string;
    nivelTono: string;
    nivelIcono: string;
    esUmbral: boolean;
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

/**
 * La ficha del BIA de un servicio: § 4.11.
 *
 * **Los tramos primero, la ficha antes que el ciclo.** Es lo que responde a la
 * pregunta que trae a alguien aquí —«¿qué tan grave es no tener esto, y cuánto
 * tiempo nos hemos dado para recuperarlo?»—, y el histórico de aprobaciones es
 * secundario a eso.
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
    dependencias: Dependencia[];
    planes: { id: number; codigo: string; titulo: string; aprobado: boolean }[];
    pruebas: {
        id: number;
        codigo: string;
        titulo: string;
        estado: string;
        estadoEtiqueta: string;
        estadoTono: string;
        estadoIcono: string;
        resultado: { valor: string; etiqueta: string; tono: string; icono: string } | null;
        fecha: string;
    }[];
    transiciones: Destino[];
    historial: Transicion[];
    puedeGestionar: boolean;
    puedeAprobar: boolean;
}>();

/* --- El ciclo --- */

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
</script>

<template>
    <AppLayout :titulo="bia.servicio ?? 'BIA'">
        <CabeceraPagina :titulo="bia.servicio ?? 'BIA'" :codigo="bia.servicioCodigo">
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/continuidad/bia/${bia.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: bia.estado,
                    etiqueta: bia.estadoEtiqueta,
                    tono: bia.estadoTono,
                    icono: bia.estadoIcono,
                }"
            />
            <span v-if="umbral.etiqueta" class="text-sm text-muted-foreground">
                Umbral tolerable: {{ umbral.etiqueta }} ({{ umbral.horas }} h)
            </span>
            <span v-else class="text-sm text-muted-foreground">
                Sin umbral intolerable: ningún tramo llega a «muy alto».
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Los cinco tramos</CardTitle>
                        <CardDescription>
                            El MTPD es el primer tramo en el que el impacto se vuelve intolerable.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TramosImpacto :tramos="tramos" :rto-horas="bia.rto_horas" :rto-incoherente="bia.rtoIncoherente" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>RTO, RPO y justificación</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="font-medium">RTO</dt>
                                <dd class="cifra text-muted-foreground">{{ bia.rto_horas }} h</dd>
                            </div>
                            <div>
                                <dt class="font-medium">RPO</dt>
                                <dd class="cifra text-muted-foreground">{{ bia.rpo_horas }} h</dd>
                            </div>
                        </dl>
                        <div v-if="bia.justificacion">
                            <dt class="font-medium">Justificación</dt>
                            <dd class="mt-1 whitespace-pre-line text-muted-foreground">{{ bia.justificacion }}</dd>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ciclo</CardTitle>
                        <CardDescription>
                            La pregunta del auditor no es «¿está aprobado?», es «¿desde cuándo?».
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="disponibles.length > 0" class="flex flex-wrap gap-2">
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
                                :etiqueta="`Por qué se vuelve a «${destino.etiqueta}»`"
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

                        <div class="border-t pt-4">
                            <HistoricoTransiciones :transiciones="historial" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card v-if="bia.responsable || bia.aprobadoPor || bia.fechaRevision">
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <dl class="grid gap-2">
                            <div v-if="bia.responsable" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Responsable</dt>
                                <dd>{{ bia.responsable }}</dd>
                            </div>
                            <div v-if="bia.aprobadoPor" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Aprobado por</dt>
                                <dd>{{ bia.aprobadoPor }}</dd>
                            </div>
                            <div v-if="bia.fechaAprobacion" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Aprobado el</dt>
                                <dd>{{ bia.fechaAprobacion }}</dd>
                            </div>
                            <div v-if="bia.fechaRevision" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Revisión</dt>
                                <dd>{{ bia.fechaRevision }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Dependencias</CardTitle>
                        <CardDescription>Todo lo que este servicio necesita para funcionar.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <EstadoVacio
                            v-if="dependencias.length === 0"
                            :icono="NetworkIcon"
                            titulo="Sin dependencias declaradas"
                            descripcion="El grafo de activos no tiene nada colgando de este servicio todavía."
                        />
                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="activo in dependencias"
                                :key="activo.id"
                                class="flex flex-wrap items-center gap-2 py-2 text-sm"
                            >
                                <CeldaBadge
                                    :valor="{
                                        valor: activo.tipo,
                                        etiqueta: activo.tipo,
                                        tono: activo.tipoTono,
                                        icono: activo.tipoIcono,
                                    }"
                                />
                                <Link :href="`/activos/${activo.id}`" class="underline underline-offset-4">
                                    {{ activo.nombre }}
                                </Link>
                                <span class="cifra text-xs text-muted-foreground">{{ activo.codigo }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Planes de continuidad</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <EstadoVacio
                            v-if="planes.length === 0"
                            :icono="FileTextIcon"
                            titulo="Sin plan vinculado"
                            descripcion="Ningún documento de continuidad cuelga todavía de este servicio."
                        />
                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="plan in planes"
                                :key="plan.id"
                                class="flex items-center justify-between gap-3 py-2 text-sm"
                            >
                                <Link :href="`/documentos/${plan.id}`" class="underline-offset-4 hover:underline">
                                    <span class="cifra">{{ plan.codigo }}</span> · {{ plan.titulo }}
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

                <Card>
                    <CardHeader>
                        <CardTitle>Últimas pruebas</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <EstadoVacio
                            v-if="pruebas.length === 0"
                            :icono="FlaskConicalIcon"
                            titulo="Sin pruebas registradas"
                            descripcion="Un RTO sin probar es una promesa, no un dato."
                        />
                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="prueba in pruebas"
                                :key="prueba.id"
                                class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                            >
                                <Link :href="`/continuidad/pruebas/${prueba.id}`" class="underline-offset-4 hover:underline">
                                    <span class="cifra">{{ prueba.codigo }}</span> · {{ prueba.titulo }}
                                </Link>
                                <div class="flex items-center gap-2">
                                    <CeldaBadge
                                        v-if="prueba.resultado"
                                        :valor="prueba.resultado"
                                    />
                                    <CeldaBadge
                                        v-else
                                        :valor="{
                                            valor: prueba.estado,
                                            etiqueta: prueba.estadoEtiqueta,
                                            tono: prueba.estadoTono,
                                            icono: prueba.estadoIcono,
                                        }"
                                    />
                                    <span class="text-xs text-muted-foreground">{{ prueba.fecha }}</span>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

            </div>
        </div>
    </AppLayout>
</template>
