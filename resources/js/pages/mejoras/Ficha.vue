<script setup lang="ts">
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

interface Transicion {
    id: number;
    anterior: string | null;
    nuevo: string;
    tono: string;
    icono: string;
    usuario: string | null;
    fecha: string;
    nota: string | null;
}

interface Mejora {
    id: number;
    codigo: string;
    origen: string;
    origenEtiqueta: string;
    titulo: string;
    descripcion: string | null;
    beneficio_esperado: string | null;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    responsable: string | null;
    fecha_deteccion: string;
    fecha_prevista: string | null;
    fechaCierre: string | null;
    previstaEtiqueta: string;
    previstaTono: string;
}

/**
 * La ficha de una oportunidad de mejora: la cláusula 10.1.
 *
 * Es la hermana pobre de la de una no conformidad, y a propósito: sin causa raíz,
 * sin corrección inmediata y sin verificación de eficacia, porque no había nada
 * roto. Lo que sí tiene es lo que se va a hacer y por qué se descartó, si se
 * descartó — que es lo que la revisión por la dirección lee de la 10.1.
 */
const props = defineProps<{
    mejora: Mejora;
    hallazgo: Hallazgo | null;
    actuaciones: Actuacion[];
    coste: { total: string; sinEstimar: number };
    transiciones: Destino[];
    historial: Transicion[];
    prioridades: Opcion[];
    responsables: Opcion[];
    puedeGestionar: boolean;
}>();

/* --- El ciclo --- */

const destino = ref<Destino | null>(null);
const nota = ref('');
const enviando = ref(false);

const disponibles = computed(() => (props.puedeGestionar ? props.transiciones : []));

function mover(paso: Destino): void {
    // Descartar es la única que pide algo escrito, así que el primer clic no
    // envía: abre el bloque de la nota.
    if (paso.exigeMotivo && destino.value?.valor !== paso.valor) {
        destino.value = paso;
        nota.value = '';

        return;
    }

    enviando.value = true;

    router.post(
        `/mejoras/${props.mejora.id}/estado`,
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
    actuacion.post(`/mejoras/${props.mejora.id}/actuaciones`, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            actuacion.reset();
        },
    });
}

function desvincular(id: number): void {
    router.delete(`/mejoras/${props.mejora.id}/actuaciones/${id}`, { preserveScroll: true });
}

const abiertas = computed(
    () => props.actuaciones.filter((item) => item.estado !== 'hecha' && item.estado !== 'descartada').length,
);
</script>

<template>
    <AppLayout :titulo="mejora.codigo">
        <CabeceraPagina :titulo="mejora.codigo" :descripcion="mejora.origenEtiqueta">
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/mejoras/${mejora.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: mejora.estado,
                    etiqueta: mejora.estadoEtiqueta,
                    tono: mejora.estadoTono,
                    icono: mejora.estadoIcono,
                }"
            />
            <CeldaBadge
                :valor="{
                    valor: 'prevista',
                    etiqueta: mejora.previstaEtiqueta,
                    tono: mejora.previstaTono,
                    icono: null,
                }"
            />
            <span class="text-sm text-muted-foreground">
                Apuntada el {{ mejora.fecha_deteccion }}
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Qué se puede hacer mejor</CardTitle>
                        <CardDescription>
                            La cláusula 10.1 pide mejorar de forma continua. Esto no incumple
                            nada: si incumpliera, sería una no conformidad.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <div
                            v-if="hallazgo"
                            class="space-y-2 rounded-xl border border-border bg-muted/40 p-4"
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

                        <p>{{ mejora.titulo }}</p>

                        <p v-if="mejora.descripcion" class="text-muted-foreground">
                            {{ mejora.descripcion }}
                        </p>

                        <div v-if="mejora.beneficio_esperado">
                            <dt class="text-muted-foreground">Beneficio esperado</dt>
                            <dd>{{ mejora.beneficio_esperado }}</dd>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Qué se va a hacer</CardTitle>
                        <CardDescription>
                            Son tareas del plan de acción. No entran en el presupuesto del
                            plan de adecuación: ese plan presupuesta medidas pendientes, y
                            una mejora no es una brecha.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <EstadoVacio
                            v-if="actuaciones.length === 0"
                            titulo="Sin nada en marcha"
                            descripcion="Está apuntada y nadie la ha empezado. Es una decisión válida; lo que no vale es no saberlo."
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

                <Card v-if="historial.length > 0">
                    <CardHeader>
                        <CardTitle>Histórico</CardTitle>
                        <CardDescription>
                            Desde cuándo está en cada estado, y por qué. Aquí vive el motivo
                            de una descartada, que es lo que impide que el registro se llene
                            de ideas muertas sin explicación.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y divide-border">
                            <li v-for="paso in historial" :key="paso.id" class="space-y-1 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <CeldaBadge
                                        :valor="{
                                            valor: paso.nuevo,
                                            etiqueta: paso.nuevo,
                                            tono: paso.tono,
                                            icono: paso.icono,
                                        }"
                                    />
                                    <span class="text-xs text-muted-foreground">
                                        {{ paso.fecha }}
                                        <template v-if="paso.usuario"> · {{ paso.usuario }}</template>
                                    </span>
                                </div>
                                <p v-if="paso.nota" class="text-sm">{{ paso.nota }}</p>
                            </li>
                        </ul>
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
                            <dd>{{ mejora.origenEtiqueta }}</dd>
                        </div>
                        <div v-if="mejora.responsable">
                            <dt class="text-muted-foreground">Responsable</dt>
                            <dd>{{ mejora.responsable }}</dd>
                        </div>
                        <div v-if="mejora.fecha_prevista">
                            <dt class="text-muted-foreground">Prevista</dt>
                            <dd>{{ mejora.fecha_prevista }}</dd>
                        </div>
                        <div v-if="mejora.fechaCierre">
                            <dt class="text-muted-foreground">Cerrada</dt>
                            <dd>{{ mejora.fechaCierre }}</dd>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="disponibles.length > 0">
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                        <CardDescription>
                            Sólo descartar pide motivo escrito: es la decisión que un auditor
                            puede cuestionar.
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
                                etiqueta="Por qué se descarta"
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
                    <DialogTitle>Abrir actuación</DialogTitle>
                    <DialogDescription>
                        Nace como tarea del plan de acción, con el origen ya puesto. No es una
                        acción correctiva: aquí no hay nada que corregir.
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
