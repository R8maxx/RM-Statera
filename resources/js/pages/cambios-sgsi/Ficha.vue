<script setup lang="ts">
import { fechaLegible } from '@/lib/celdas';
import HistoricoTransiciones, { type Transicion } from '@/components/HistoricoTransiciones.vue';
import BotonEstado from '@/components/BotonEstado.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
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
import { Link, router, useForm } from '@inertiajs/vue3';
import { ShieldCheckIcon } from '@lucide/vue';
import { computed, ref } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Destino {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
    exigeMotivo: boolean;
    permiso: string;
}

interface Actuacion {
    id: number;
    titulo: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    responsable: string | null;
    plazoEtiqueta: string;
    plazoTono: string;
    fecha: string | null;
    coste: string | null;
}

interface Cambio {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    ambitoEtiqueta: string;
    origenEtiqueta: string;
    proposito: string | null;
    consecuencias: string | null;
    integridad: string | null;
    recursos: string | null;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    responsable: string | null;
    fecha_propuesta: string;
    fecha_prevista: string | null;
    fechaImplantacion: string | null;
    fechaCierre: string | null;
    aprobadoPor: string | null;
    aprobadoEn: string | null;
    revision: string | null;
    plazoEtiqueta: string;
    plazoTono: string;
}

/**
 * La ficha de un cambio del SGSI: la cláusula 6.3.
 *
 * Contesta lo que el auditor pregunta de un cambio planificado —para qué, qué
 * arrastraba, cómo siguió el sistema en pie, con qué y quién lo decidió— y, al
 * final, si sirvió.
 */
const props = defineProps<{
    cambio: Cambio;
    actuaciones: Actuacion[];
    coste: { total: string; sinEstimar: number };
    transiciones: Destino[];
    historial: Transicion[];
    prioridades: Opcion[];
    responsables: Opcion[];
    puedeGestionar: boolean;
    puedeAprobar: boolean;
}>();

/* --- El ciclo --- */

const destino = ref<Destino | null>(null);
const nota = ref('');
const enviando = ref(false);

const disponibles = computed(() =>
    props.transiciones.filter((paso) =>
        paso.permiso === 'cambios_sgsi.aprobar' ? props.puedeAprobar : props.puedeGestionar,
    ),
);

/*
 * Aprobar sin fecha lo rechaza el dominio; se dice antes de pulsar, y no
 * después con un error.
 */
const faltaPlazo = computed(() => props.cambio.fecha_prevista === null);
const bloqueado = (paso: Destino): boolean => paso.valor === 'aprobado' && faltaPlazo.value;

const etiquetaNota = computed(() => {
    switch (destino.value?.valor) {
        case 'descartado':
            return 'Por qué se descarta';
        case 'revisado':
            return 'Si consiguió lo que pretendía';
        default:
            return 'Qué cambia';
    }
});

function mover(paso: Destino): void {
    // Las que piden algo escrito no envían al primer clic: abren la nota.
    if (paso.exigeMotivo && destino.value?.valor !== paso.valor) {
        destino.value = paso;
        nota.value = '';

        return;
    }

    enviando.value = true;

    router.post(
        `/cambios-sgsi/${props.cambio.id}/estado`,
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

/* --- Qué se va a hacer --- */

const abierto = ref(false);

const actuacion = useForm({
    titulo: '',
    descripcion: '',
    prioridad: 'media',
    responsable_id: '',
    fecha_limite: '',
    coste_estimado: '',
});

function abrirActuacion(): void {
    actuacion.reset();
    actuacion.clearErrors();
    abierto.value = true;
}

function crearActuacion(): void {
    actuacion.post(`/cambios-sgsi/${props.cambio.id}/actuaciones`, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            actuacion.reset();
        },
    });
}

function desvincular(id: number): void {
    router.delete(`/cambios-sgsi/${props.cambio.id}/actuaciones/${id}`, { preserveScroll: true });
}

const abiertas = computed(
    () => props.actuaciones.filter((item) => item.estado !== 'hecha' && item.estado !== 'descartada').length,
);

const planificacion = computed(() =>
    [
        { etiqueta: 'Propósito', texto: props.cambio.proposito },
        { etiqueta: 'Consecuencias', texto: props.cambio.consecuencias },
        { etiqueta: 'Integridad del SGSI', texto: props.cambio.integridad },
        { etiqueta: 'Recursos', texto: props.cambio.recursos },
    ],
);

const sinPlanificar = computed(() => planificacion.value.filter((item) => !item.texto).length);
</script>

<template>
    <AppLayout :titulo="cambio.codigo">
        <CabeceraPagina :titulo="cambio.codigo" :descripcion="cambio.ambitoEtiqueta">
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/cambios-sgsi/${cambio.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge anunciar
                :valor="{
                    valor: cambio.estado,
                    etiqueta: cambio.estadoEtiqueta,
                    tono: cambio.estadoTono,
                    icono: cambio.estadoIcono,
                }"
            />
            <CeldaBadge
                :valor="{
                    valor: 'plazo',
                    etiqueta: cambio.plazoEtiqueta,
                    tono: cambio.plazoTono,
                    icono: null,
                }"
            />
            <span class="text-sm text-muted-foreground">
                Propuesto el {{ fechaLegible(cambio.fecha_propuesta) }}
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Qué cambia</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <p>{{ cambio.titulo }}</p>
                        <p v-if="cambio.descripcion" class="text-muted-foreground">
                            {{ cambio.descripcion }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Cómo se planifica</CardTitle>
                        <CardDescription>
                            Lo que la cláusula 6.3 espera de un cambio hecho de forma planificada.
                            <template v-if="sinPlanificar > 0">
                                Faltan {{ sinPlanificar }} de 4.
                            </template>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl class="space-y-4 text-sm">
                            <div v-for="item in planificacion" :key="item.etiqueta">
                                <dt class="text-muted-foreground">{{ item.etiqueta }}</dt>
                                <dd v-if="item.texto" class="whitespace-pre-line">{{ item.texto }}</dd>
                                <dd v-else class="text-muted-foreground italic">Sin escribir.</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card v-if="cambio.revision">
                    <CardHeader>
                        <CardTitle>Revisión</CardTitle>
                        <CardDescription>Si el cambio consiguió lo que pretendía.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm whitespace-pre-line">{{ cambio.revision }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Qué se va a hacer</CardTitle>
                        <CardDescription>
                            Son tareas del plan de acción. No entran en el presupuesto del plan de
                            adecuación: ese plan presupuesta medidas pendientes del Anexo II.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <EstadoVacio
                            v-if="actuaciones.length === 0"
                            titulo="Sin actuaciones"
                            descripcion="Todavía no hay ninguna tarea que lleve a cabo este cambio."
                        />

                        <template v-else>
                            <p class="text-sm">
                                <span class="cifra text-2xl font-semibold">{{ abiertas }}</span>
                                <span class="text-muted-foreground">
                                    de {{ actuaciones.length }} abiertas · {{ coste.total }}
                                    <template v-if="coste.sinEstimar > 0">
                                        ({{ coste.sinEstimar }} sin estimar)
                                    </template>
                                </span>
                            </p>

                            <ul class="divide-y divide-border">
                                <li
                                    v-for="item in actuaciones"
                                    :key="item.id"
                                    class="flex items-start justify-between gap-4 py-3"
                                >
                                    <div class="space-y-1">
                                        <Link
                                            :href="`/tareas/${item.id}`"
                                            class="text-sm font-medium underline-offset-4 hover:underline"
                                        >
                                            {{ item.titulo }}
                                        </Link>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <CeldaBadge
                                                :valor="{
                                                    valor: item.estado,
                                                    etiqueta: item.estadoEtiqueta,
                                                    tono: item.estadoTono,
                                                    icono: item.estadoIcono,
                                                }"
                                            />
                                            <CeldaBadge
                                                :valor="{
                                                    valor: 'plazo',
                                                    etiqueta: item.plazoEtiqueta,
                                                    tono: item.plazoTono,
                                                    icono: null,
                                                }"
                                            />
                                            <span v-if="item.responsable" class="text-xs text-muted-foreground">
                                                {{ item.responsable }}
                                            </span>
                                        </div>
                                    </div>
                                    <Button
                                        v-if="puedeGestionar"
                                        variant="ghost"
                                        size="sm"
                                        @click="desvincular(item.id)"
                                    >
                                        Desvincular
                                    </Button>
                                </li>
                            </ul>
                        </template>

                        <Button v-if="puedeGestionar" variant="outline" @click="abrirActuacion">
                            Abrir actuación
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Histórico</CardTitle>
                        <CardDescription>
                            Desde cuándo está en cada estado, quién lo movió y por qué.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <HistoricoTransiciones :transiciones="historial" />
                    </CardContent>
                </Card>
            </div>

            <div class="h-fit space-y-6">
                <Card v-if="disponibles.length > 0">
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex flex-col gap-2">
                            <template v-for="paso in disponibles" :key="paso.valor">
                                <BotonEstado
                                    class="h-9 w-full justify-start"
                                    :destino="{ ...paso, pista: paso.exigeMotivo ? 'pide nota' : null }"
                                    :deshabilitado="enviando || bloqueado(paso)"
                                    :aria-describedby="bloqueado(paso) ? 'falta-plazo' : undefined"
                                    @click="mover(paso)"
                                />
                                <p v-if="bloqueado(paso)" id="falta-plazo" class="text-xs text-muted-foreground">
                                    Falta la fecha prevista.
                                    <Link
                                        v-if="puedeGestionar"
                                        :href="`/cambios-sgsi/${cambio.id}/editar`"
                                        class="text-primary underline-offset-4 hover:underline"
                                    >
                                        Ponerla
                                    </Link>
                                </p>
                            </template>
                        </div>

                        <div v-if="destino" class="space-y-2 border-t pt-3">
                            <CampoTextarea
                                nombre="nota"
                                :etiqueta="etiquetaNota"
                                :filas="4"
                                :valor-inicial="nota"
                                requerido
                                @input="nota = ($event.target as HTMLTextAreaElement).value"
                            />
                            <div class="flex gap-2">
                                <Button :disabled="enviando || nota.trim() === ''" @click="mover(destino)">
                                    {{ destino.etiqueta }}
                                </Button>
                                <Button variant="ghost" @click="destino = null">Cancelar</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!--
                    La firma tiene tarjeta propia, como en objetivos: es lo que
                    separa una propuesta de un compromiso, y su hueco vacío tiene
                    que verse.
                -->
                <Card>
                    <CardHeader>
                        <CardTitle>Aprobación</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <div v-if="cambio.aprobadoEn" class="flex items-start gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent text-primary">
                                <ShieldCheckIcon class="size-4.5" aria-hidden="true" />
                            </span>
                            <div class="flex flex-col">
                                <span class="font-medium">{{ cambio.aprobadoPor ?? 'Firmante desconocido' }}</span>
                                <span class="cifra text-xs text-muted-foreground">{{ cambio.aprobadoEn }}</span>
                            </div>
                        </div>
                        <p v-else class="rounded-lg border border-dashed p-4 text-[13px] text-muted-foreground">
                            Sin firmar. Cambiar el sistema de gestión es una decisión de dirección.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-muted-foreground">Origen</dt>
                                <dd>{{ cambio.origenEtiqueta }}</dd>
                            </div>
                            <div v-if="cambio.responsable">
                                <dt class="text-muted-foreground">Responsable</dt>
                                <dd>{{ cambio.responsable }}</dd>
                            </div>
                            <div v-if="cambio.fecha_prevista">
                                <dt class="text-muted-foreground">Prevista</dt>
                                <dd>{{ fechaLegible(cambio.fecha_prevista) }}</dd>
                            </div>
                            <div v-if="cambio.fechaImplantacion">
                                <dt class="text-muted-foreground">Implantado</dt>
                                <dd>{{ cambio.fechaImplantacion }}</dd>
                            </div>
                            <div v-if="cambio.fechaCierre">
                                <dt class="text-muted-foreground">Cerrado</dt>
                                <dd>{{ cambio.fechaCierre }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Abrir actuación</DialogTitle>
                    <DialogDescription>
                        Nace como tarea del plan de acción, con el origen ya puesto.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoTexto
                        nombre="titulo"
                        etiqueta="Título"
                        :error="actuacion.errors.titulo"
                        requerido
                        @input="actuacion.titulo = ($event.target as HTMLInputElement).value"
                    />
                    <CampoSelect
                        nombre="prioridad"
                        etiqueta="Prioridad"
                        :opciones="prioridades"
                        :valor-inicial="actuacion.prioridad"
                        :error="actuacion.errors.prioridad"
                        requerido
                        @update:model-value="(valor?: string) => (actuacion.prioridad = valor ?? 'media')"
                    />
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :error="actuacion.errors.responsable_id"
                        @update:model-value="(valor?: string) => (actuacion.responsable_id = valor ?? '')"
                    />
                    <CampoTexto
                        nombre="fecha_limite"
                        etiqueta="Fecha límite"
                        tipo="date"
                        :error="actuacion.errors.fecha_limite"
                        @input="actuacion.fecha_limite = ($event.target as HTMLInputElement).value"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abierto = false">Cancelar</Button>
                    <Button :disabled="actuacion.processing" @click="crearActuacion">Abrir</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
