<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
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

interface IndicadorVinculado {
    id: number;
    codigo: string;
    nombre: string;
    periodicidad: string;
    objetivo: string | null;
    ultimoValor: string | null;
    ultimoPeriodo: string | null;
    cumplimiento: string;
    cumplimientoEtiqueta: string;
    cumplimientoTono: string;
    cumplimientoIcono: string;
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

interface Objetivo {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    recursos: string | null;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    esComprometido: boolean;
    responsable: string | null;
    fecha_objetivo: string | null;
    fechaCierre: string | null;
    aprobadoPor: string | null;
    aprobadoEn: string | null;
    notaAprobacion: string | null;
    plazoEtiqueta: string;
    plazoTono: string;
}

interface Avance {
    enObjetivo: number;
    medidos: number;
    total: number;
    etiqueta: string;
    tono: string;
    contradice: boolean;
}

/**
 * La ficha de un objetivo de seguridad: las cinco preguntas de la cláusula 6.2.
 *
 * Qué nos comprometemos a conseguir, qué se hará —las actuaciones, que son
 * tareas—, con qué recursos, quién responde, para cuándo y **cómo se evaluarán
 * los resultados**, que son sus indicadores. La última es la que el auditor mira
 * primero, y por eso tiene tarjeta propia y su hueco vacío se ve.
 */
const props = defineProps<{
    objetivo: Objetivo;
    avance: Avance;
    indicadores: IndicadorVinculado[];
    actuaciones: Actuacion[];
    coste: { total: string; sinEstimar: number };
    transiciones: Destino[];
    historial: Transicion[];
    prioridades: Opcion[];
    responsables: Opcion[];
    indicadoresDisponibles: Opcion[];
    puedeGestionar: boolean;
    puedeAprobar: boolean;
}>();

/* --- El ciclo --- */

/*
 * Tres transiciones piden algo escrito, así que el primer clic en ellas no
 * envía: abre el bloque de la nota. Mismo gesto que en una no conformidad y por
 * lo mismo — pedir la nota siempre convierte en trámite el único sitio donde se
 * dice qué pasó.
 */
const destino = ref<Destino | null>(null);
const nota = ref('');
const enviando = ref(false);

const disponibles = computed(() =>
    props.transiciones.filter((paso) =>
        paso.permiso === 'objetivos.aprobar' ? props.puedeAprobar : props.puedeGestionar,
    ),
);

const etiquetaNota = computed(() => {
    if (destino.value === null) {
        return 'Nota';
    }

    return destino.value.valor === 'no_alcanzado'
        ? 'Por qué no se alcanzó'
        : destino.value.valor === 'retirado'
          ? 'Por qué se retira'
          : 'Qué cambia';
});

function mover(paso: Destino): void {
    if (paso.exigeMotivo && destino.value?.valor !== paso.valor) {
        destino.value = paso;
        nota.value = '';

        return;
    }

    enviando.value = true;

    router.post(
        `/objetivos/${props.objetivo.id}/estado`,
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

/* --- Cómo se evalúa --- */

const indicador = useForm({ indicador_id: '' });

function vincularIndicador(): void {
    indicador.post(`/objetivos/${props.objetivo.id}/indicadores`, {
        preserveScroll: true,
        onSuccess: () => indicador.reset(),
    });
}

function desvincularIndicador(id: number): void {
    router.delete(`/objetivos/${props.objetivo.id}/indicadores/${id}`, { preserveScroll: true });
}

/*
 * Se ofrecen los que no están ya puestos: repetir uno no rompe nada —la
 * vinculación es idempotente— pero ofrecerlo hace pensar que falta.
 */
const sinVincular = computed(() => {
    const puestos = new Set(props.indicadores.map((item) => String(item.id)));

    return props.indicadoresDisponibles.filter((opcion) => !puestos.has(opcion.valor));
});

/* --- Qué se hará --- */

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
    actuacion.post(`/objetivos/${props.objetivo.id}/actuaciones`, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            actuacion.reset();
        },
    });
}

function desvincularActuacion(id: number): void {
    router.delete(`/objetivos/${props.objetivo.id}/actuaciones/${id}`, { preserveScroll: true });
}

const abiertas = computed(
    () => props.actuaciones.filter((item) => item.estado !== 'hecha' && item.estado !== 'descartada').length,
);
</script>

<template>
    <AppLayout :titulo="objetivo.codigo">
        <CabeceraPagina :titulo="objetivo.titulo" :codigo="objetivo.codigo">
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/objetivos/${objetivo.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: objetivo.estado,
                    etiqueta: objetivo.estadoEtiqueta,
                    tono: objetivo.estadoTono,
                    icono: objetivo.estadoIcono,
                }"
            />
            <CeldaBadge
                :valor="{
                    valor: 'plazo',
                    etiqueta: objetivo.plazoEtiqueta,
                    tono: objetivo.plazoTono,
                    icono: null,
                }"
            />
            <CeldaBadge
                :valor="{
                    valor: 'avance',
                    etiqueta: avance.etiqueta,
                    tono: avance.tono,
                    icono: null,
                }"
            />
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>El objetivo</CardTitle>
                        <CardDescription>
                            La cláusula 6.2 pide que sea medible y coherente con la política
                            de seguridad.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <p>{{ objetivo.titulo }}</p>
                        <p v-if="objetivo.descripcion" class="text-muted-foreground">
                            {{ objetivo.descripcion }}
                        </p>
                    </CardContent>
                </Card>

                <!--
                    La contradicción se señala y no se corrige: un objetivo dado
                    por alcanzado con indicadores por debajo de su objetivo se
                    pone delante, y la herramienta no toca el dato. Mismo papel
                    que el residual sin respaldo de un riesgo.
                -->
                <Card>
                    <CardHeader>
                        <CardTitle>Cómo se evalúan los resultados</CardTitle>
                        <CardDescription>
                            Los indicadores que juzgan este objetivo. Se vinculan, no se crean:
                            un indicador tiene periodicidad, responsable y método propios.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Aviso v-if="avance.contradice">
                            Este objetivo figura como alcanzado y
                            {{ avance.medidos - avance.enObjetivo }} de sus
                            {{ avance.medidos }} indicadores medidos no llegan a su objetivo.
                        </Aviso>

                        <EstadoVacio
                            v-if="indicadores.length === 0"
                            titulo="Sin indicador"
                            descripcion="La cláusula 6.2 exige que el objetivo sea medible. Sin ninguna cifra detrás, se cumple de palabra."
                        />

                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="item in indicadores"
                                :key="item.id"
                                class="flex items-start justify-between gap-4 py-3"
                            >
                                <div class="space-y-1">
                                    <Link
                                        :href="`/indicadores/${item.id}`"
                                        class="text-sm font-medium underline-offset-4 hover:underline"
                                    >
                                        <span class="cifra">{{ item.codigo }}</span> · {{ item.nombre }}
                                    </Link>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <CeldaBadge
                                            :valor="{
                                                valor: item.cumplimiento,
                                                etiqueta: item.cumplimientoEtiqueta,
                                                tono: item.cumplimientoTono,
                                                icono: item.cumplimientoIcono,
                                            }"
                                        />
                                        <span class="text-xs text-muted-foreground">
                                            <template v-if="item.ultimoValor">
                                                {{ item.ultimoValor }}
                                                <template v-if="item.objetivo">
                                                    de {{ item.objetivo }}
                                                </template>
                                                <template v-if="item.ultimoPeriodo">
                                                    · {{ item.ultimoPeriodo }}
                                                </template>
                                            </template>
                                            <template v-else>{{ item.periodicidad }}</template>
                                        </span>
                                    </div>
                                </div>
                                <Button
                                    v-if="puedeGestionar"
                                    variant="ghost"
                                    size="sm"
                                    @click="desvincularIndicador(item.id)"
                                >
                                    Desvincular
                                </Button>
                            </li>
                        </ul>

                        <div v-if="puedeGestionar && sinVincular.length > 0" class="space-y-2">
                            <CampoSelect
                                nombre="indicador_id"
                                etiqueta="Vincular un indicador"
                                :opciones="sinVincular"
                                :error="indicador.errors.indicador_id"
                                @update:model-value="(valor?: string) => (indicador.indicador_id = valor ?? '')"
                            />
                            <Button
                                variant="outline"
                                :disabled="indicador.processing || indicador.indicador_id === ''"
                                @click="vincularIndicador"
                            >
                                Vincular
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Qué se va a hacer</CardTitle>
                        <CardDescription>
                            Son tareas del plan de acción, con responsable, plazo y coste.
                            No entran en el presupuesto del plan de adecuación: ese plan
                            presupuesta medidas del Anexo II.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <EstadoVacio
                            v-if="actuaciones.length === 0"
                            titulo="Sin actuaciones"
                            descripcion="Nadie está empujando este objetivo todavía."
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
                                        @click="desvincularActuacion(item.id)"
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
                            Desde cuándo está en cada estado, y por qué. Es lo que el
                            auditor pregunta, y de aquí sale el «por qué no se alcanzó»
                            de la revisión por la dirección.
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
                        <CardDescription>
                            Retirar y dar por no alcanzado piden motivo escrito, y reabrir
                            también: es darle otro plazo a algo que ya se cerró.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <BotonEstado
                                v-for="paso in disponibles"
                                :key="paso.valor"
                                :destino="paso"
                                :deshabilitado="enviando"
                                @click="mover(paso)"
                            />
                        </div>

                        <div v-if="destino" class="space-y-2">
                            <CampoTextarea
                                nombre="nota"
                                :etiqueta="etiquetaNota"
                                :filas="4"
                                :valor-inicial="nota"
                                requerido
                                @input="nota = ($event.target as HTMLTextAreaElement).value"
                            />
                            <Button :disabled="enviando || nota.trim() === ''" @click="mover(destino)">
                                {{ destino.etiqueta }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div v-if="objetivo.responsable">
                            <dt class="text-muted-foreground">Responsable</dt>
                            <dd>{{ objetivo.responsable }}</dd>
                        </div>
                        <div v-if="objetivo.fecha_objetivo">
                            <dt class="text-muted-foreground">Fecha objetivo</dt>
                            <dd>{{ objetivo.fecha_objetivo }}</dd>
                        </div>
                        <div v-if="objetivo.fechaCierre">
                            <dt class="text-muted-foreground">Cerrado</dt>
                            <dd>{{ objetivo.fechaCierre }}</dd>
                        </div>
                        <div v-if="objetivo.recursos">
                            <dt class="text-muted-foreground">Recursos</dt>
                            <dd>{{ objetivo.recursos }}</dd>
                        </div>
                    </CardContent>
                </Card>

                <!--
                    La firma tiene tarjeta propia y no una línea más en la ficha:
                    es lo que separa un borrador de un compromiso, y su hueco
                    vacío tiene que verse.
                -->
                <Card>
                    <CardHeader>
                        <CardTitle>Aprobación</CardTitle>
                        <CardDescription>
                            Comprometerse a una cifra y a un plazo es de dirección.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <template v-if="objetivo.aprobadoEn">
                            <p v-if="objetivo.notaAprobacion">{{ objetivo.notaAprobacion }}</p>
                            <p class="text-muted-foreground">
                                {{ objetivo.aprobadoEn }}
                                <template v-if="objetivo.aprobadoPor">
                                    · {{ objetivo.aprobadoPor }}
                                </template>
                            </p>
                        </template>
                        <p v-else class="text-muted-foreground">
                            Todavía sin aprobar. Un objetivo propuesto no compromete a nadie,
                            y aprobarlo exige decir para cuándo.
                        </p>
                    </CardContent>
                </Card>

            </div>
        </div>

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Abrir actuación</DialogTitle>
                    <DialogDescription>
                        Nace como tarea del plan de acción, con el origen ya puesto. Es el
                        «qué se hará» que pide la cláusula 6.2.
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
