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

interface Hallazgo {
    id: number;
    tipo: string;
    tipoEtiqueta: string;
    tipoTono: string;
    tipoIcono: string;
    descripcion: string;
    auditoria: string | null;
    auditoria_id: number | null;
    medida: string | null;
}

interface PruebaContinuidad {
    id: number;
    codigo: string;
}

interface Accion {
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


interface NoConformidad {
    id: number;
    codigo: string;
    origen: string;
    origenEtiqueta: string;
    descripcion: string;
    correccion_inmediata: string | null;
    analisis_causa_raiz: string | null;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    responsable: string | null;
    fecha_deteccion: string;
    fecha_prevista: string | null;
    fechaCierre: string | null;
    fechaVerificacion: string | null;
    verificadaPor: string | null;
    resultado_verificacion: string | null;
    plazoEtiqueta: string;
    plazoTono: string;
}

/**
 * La ficha de una no conformidad: las cuatro preguntas de la cláusula 10.2.
 *
 * Qué falló, por qué —la causa raíz—, qué se está haciendo —las acciones
 * correctivas, que son tareas— y si funcionó —la verificación—. La última es la
 * que el auditor comprueba, y por eso tiene bloque propio en vez de una casilla.
 */
const props = defineProps<{
    noConformidad: NoConformidad;
    hallazgo: Hallazgo | null;
    pruebaContinuidad: PruebaContinuidad | null;
    acciones: Accion[];
    coste: { total: string; sinEstimar: number };
    transiciones: Destino[];
    historial: Transicion[];
    prioridades: Opcion[];
    responsables: Opcion[];
    puedeGestionar: boolean;
    puedeVerificar: boolean;
}>();

/* --- El ciclo --- */

/*
 * Tres transiciones piden algo escrito, así que el primer clic en ellas no
 * envía: abre el bloque de la nota. Mismo gesto que cerrar una auditoría o
 * descartar una tarea, y por lo mismo — pedir la nota siempre convierte en
 * trámite el único sitio donde se dice qué pasó.
 */
const destino = ref<Destino | null>(null);
const nota = ref('');
const enviando = ref(false);

const disponibles = computed(() =>
    props.transiciones.filter((paso) =>
        paso.permiso === 'no_conformidades.verificar' ? props.puedeVerificar : props.puedeGestionar,
    ),
);

const etiquetaNota = computed(() => {
    if (destino.value === null) {
        return 'Nota';
    }

    return destino.value.valor === 'verificada'
        ? 'Qué se ha comprobado'
        : destino.value.valor === 'anulada'
          ? 'Por qué se anula'
          : 'Qué ha fallado';
});

function mover(paso: Destino): void {
    if (paso.exigeMotivo && destino.value?.valor !== paso.valor) {
        destino.value = paso;
        nota.value = '';

        return;
    }

    enviando.value = true;

    router.post(
        `/no-conformidades/${props.noConformidad.id}/estado`,
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

/* --- Las acciones correctivas --- */

const abierto = ref(false);

const accion = useForm({
    titulo: '',
    descripcion: '',
    prioridad: 'media',
    responsable_id: '',
    fecha_limite: '',
    coste_estimado: '',
});

function abrirAccion(): void {
    accion.reset();
    accion.clearErrors();
    abierto.value = true;
}

function crearAccion(): void {
    accion.post(`/no-conformidades/${props.noConformidad.id}/acciones`, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            accion.reset();
        },
    });
}

function desvincular(id: number): void {
    router.delete(`/no-conformidades/${props.noConformidad.id}/acciones/${id}`, {
        preserveScroll: true,
    });
}

const abiertas = computed(
    () => props.acciones.filter((item) => item.estado !== 'hecha' && item.estado !== 'descartada').length,
);
</script>

<template>
    <AppLayout :titulo="noConformidad.codigo">
        <CabeceraPagina :titulo="noConformidad.codigo" :descripcion="noConformidad.origenEtiqueta">
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/no-conformidades/${noConformidad.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: noConformidad.estado,
                    etiqueta: noConformidad.estadoEtiqueta,
                    tono: noConformidad.estadoTono,
                    icono: noConformidad.estadoIcono,
                }"
            />
            <CeldaBadge
                :valor="{
                    valor: 'plazo',
                    etiqueta: noConformidad.plazoEtiqueta,
                    tono: noConformidad.plazoTono,
                    icono: null,
                }"
            />
            <span class="text-sm text-muted-foreground">
                Detectada el {{ fechaLegible(noConformidad.fecha_deteccion) }}
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Qué ha fallado</CardTitle>
                        <CardDescription>
                            La cláusula 10.2 pide reaccionar, analizar la causa, corregir
                            y comprobar que funcionó.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <div
                            v-if="hallazgo"
                            class="space-y-2 border-l-2 border-border pl-4"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <CeldaBadge
                                    :valor="{
                                        valor: hallazgo.tipo,
                                        etiqueta: hallazgo.tipoEtiqueta,
                                        tono: hallazgo.tipoTono,
                                        icono: hallazgo.tipoIcono,
                                    }"
                                />
                                <Link
                                    v-if="hallazgo.auditoria_id"
                                    :href="`/auditorias/${hallazgo.auditoria_id}`"
                                    class="cifra text-xs text-muted-foreground underline-offset-4 hover:underline"
                                >
                                    {{ hallazgo.auditoria }}
                                </Link>
                                <span v-if="hallazgo.medida" class="cifra text-xs text-muted-foreground">
                                    {{ hallazgo.medida }}
                                </span>
                            </div>
                            <p class="text-muted-foreground">{{ hallazgo.descripcion }}</p>
                        </div>

                        <p v-if="pruebaContinuidad" class="text-sm text-muted-foreground">
                            Procede de la prueba de continuidad
                            <Link
                                :href="`/continuidad/pruebas/${pruebaContinuidad.id}`"
                                class="cifra underline-offset-4 hover:underline"
                            >
                                {{ pruebaContinuidad.codigo }}
                            </Link>
                        </p>

                        <p>{{ noConformidad.descripcion }}</p>

                        <div v-if="noConformidad.correccion_inmediata">
                            <dt class="text-muted-foreground">Corrección inmediata</dt>
                            <dd>{{ noConformidad.correccion_inmediata }}</dd>
                        </div>

                        <div v-if="noConformidad.analisis_causa_raiz">
                            <dt class="text-muted-foreground">Causa raíz</dt>
                            <dd>{{ noConformidad.analisis_causa_raiz }}</dd>
                        </div>

                        <!--
                            Un hueco vacío no se disimula: sin causa raíz, la
                            acción correctiva trata el síntoma y la no conformidad
                            vuelve el año que viene.
                        -->
                        <p v-else class="text-muted-foreground">
                            Sin análisis de causa raíz. La cláusula 10.2 b) lo pide por escrito.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Acciones correctivas</CardTitle>
                        <CardDescription>
                            Son tareas del plan de acción: con responsable, plazo y coste.
                            Cuando la no conformidad viene de una medida, la acción cuenta
                            también como trabajo planificado sobre ella.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <EstadoVacio
                            v-if="acciones.length === 0"
                            titulo="Sin acciones correctivas"
                            descripcion="Nadie está tratando esta no conformidad todavía."
                        />

                        <template v-else>
                            <p class="text-sm">
                                <span class="cifra text-2xl font-semibold">{{ abiertas }}</span>
                                <span class="text-muted-foreground">
                                    de {{ acciones.length }} abiertas · {{ coste.total }}
                                    <template v-if="coste.sinEstimar > 0">
                                        ({{ coste.sinEstimar }} sin estimar)
                                    </template>
                                </span>
                            </p>

                            <ul class="divide-y divide-border">
                                <li
                                    v-for="item in acciones"
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

                        <Button v-if="puedeGestionar" variant="outline" @click="abrirAccion">
                            Abrir acción correctiva
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Histórico</CardTitle>
                        <CardDescription>
                            Desde cuándo está en cada estado, y por qué. Es lo que el
                            auditor pregunta.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <HistoricoTransiciones :transiciones="historial" />
                    </CardContent>
                </Card>
            </div>

            <div class="h-fit space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Origen</dt>
                            <dd>{{ noConformidad.origenEtiqueta }}</dd>
                        </div>
                        <div v-if="noConformidad.responsable">
                            <dt class="text-muted-foreground">Responsable</dt>
                            <dd>{{ noConformidad.responsable }}</dd>
                        </div>
                        <div v-if="noConformidad.fecha_prevista">
                            <dt class="text-muted-foreground">Prevista</dt>
                            <dd>{{ noConformidad.fecha_prevista }}</dd>
                        </div>
                        <div v-if="noConformidad.fechaCierre">
                            <dt class="text-muted-foreground">Tratamiento cerrado</dt>
                            <dd>{{ noConformidad.fechaCierre }}</dd>
                        </div>
                    </CardContent>
                </Card>

                <!--
                    La verificación tiene tarjeta propia y no una línea más en la
                    ficha: es el paso que la norma pide y el que más se olvida, y
                    su hueco vacío tiene que verse.
                -->
                <Card>
                    <CardHeader>
                        <CardTitle>Verificación de eficacia</CardTitle>
                        <CardDescription>
                            Comprobar que la corrección sirvió. No la firma quien la ejecutó.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <template v-if="noConformidad.fechaVerificacion">
                            <p>{{ noConformidad.resultado_verificacion }}</p>
                            <p class="text-muted-foreground">
                                {{ noConformidad.fechaVerificacion }}
                                <template v-if="noConformidad.verificadaPor">
                                    · {{ noConformidad.verificadaPor }}
                                </template>
                            </p>
                        </template>
                        <p v-else class="text-muted-foreground">
                            Todavía sin verificar. Una no conformidad cerrada y sin verificar
                            se lee como resuelta y no lo está.
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="disponibles.length > 0">
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                        <CardDescription>
                            Anular y verificar piden motivo escrito, y reabrir el
                            tratamiento también: es el resultado de una verificación que
                            salió mal.
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
            </div>
        </div>

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Abrir acción correctiva</DialogTitle>
                    <DialogDescription>
                        Nace como tarea del plan de acción, con el origen ya puesto. Ataca
                        la causa: la contención inmediata va en la ficha.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoTexto
                        nombre="titulo"
                        etiqueta="Título"
                        :error="accion.errors.titulo"
                        requerido
                        @input="accion.titulo = ($event.target as HTMLInputElement).value"
                    />
                    <CampoSelect
                        nombre="prioridad"
                        etiqueta="Prioridad"
                        :opciones="prioridades"
                        :valor-inicial="accion.prioridad"
                        :error="accion.errors.prioridad"
                        requerido
                        @update:model-value="(valor?: string) => (accion.prioridad = valor ?? 'media')"
                    />
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :error="accion.errors.responsable_id"
                        @update:model-value="(valor?: string) => (accion.responsable_id = valor ?? '')"
                    />
                    <CampoTexto
                        nombre="fecha_limite"
                        etiqueta="Fecha límite"
                        tipo="date"
                        :error="accion.errors.fecha_limite"
                        @input="accion.fecha_limite = ($event.target as HTMLInputElement).value"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abierto = false">Cancelar</Button>
                    <Button :disabled="accion.processing" @click="crearAccion">Abrir</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
