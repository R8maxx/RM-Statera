<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import BotonEstado from '@/components/BotonEstado.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import HistoricoTransiciones, { type Transicion } from '@/components/HistoricoTransiciones.vue';
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
import { fechaLegible, formatoFechaHora } from '@/lib/celdas';
import type { Opcion } from '@/lib/formularios';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * La ficha de una vulnerabilidad (invariante 8, A.8.8, `op.exp.4`).
 *
 * La columna lateral va en el orden de `DESIGN.md`: «Estado» arriba y «Ficha»
 * después. **Los destinos que piden nota la piden antes de enviar**: aceptar
 * (motivo), cerrar (cómo se verificó), reabrir o devolver a remediación
 * (por qué). `CambiarEstadoVulnerabilidad` lo exige igual; aquí sólo se evita
 * el viaje de ida y vuelta.
 */
interface Destino {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
    exigeNota: boolean;
}

interface Vulnerabilidad {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    cve: string | null;
    cvss: string | null;
    vector: string | null;
    severidad: string;
    severidadEtiqueta: string;
    severidadTono: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    origen: string;
    fechaDeteccion: string;
    fechaLimite: string | null;
    fueraDePlazo: boolean;
    responsable: string | null;
    proveedor: { id: number; codigo: string; nombre: string } | null;
    riesgo: { id: number; codigo: string; titulo: string } | null;
    incidente: { id: number; codigo: string; titulo: string } | null;
    remediacion: string | null;
    motivoAceptacion: string | null;
    aceptadaPor: string | null;
    aceptadaEn: string | null;
    verificacion: string | null;
    verificadaPor: string | null;
    cerradaEn: string | null;
}

const props = defineProps<{
    vulnerabilidad: Vulnerabilidad;
    activos: { id: number; codigo: string; nombre: string }[];
    transiciones: Destino[];
    historial: Transicion[];
    tareas: { id: number; titulo: string; estado: string; tono: string; icono: string }[];
    responsables: Opcion[];
    prioridades: Opcion[];
    puedeGestionar: boolean;
    puedeAbrirTarea: boolean;
}>();

const pagina = usePage();
const errorEstado = computed(() => (pagina.props.errors as Record<string, string | undefined>).nota);

/* ---------------------------------------------------------------- Estado */

const destino = ref<Destino | null>(null);
const nota = ref('');
const enviando = ref(false);

const rotuloNota = computed(() => {
    switch (destino.value?.valor) {
        case 'cerrada':
            return 'Cómo se comprobó que ya no está';
        case 'aceptada':
            return 'Por qué no se corrige';
        case 'falso_positivo':
            return 'Por qué no era una vulnerabilidad';
        default:
            return `Por qué pasa a «${destino.value?.etiqueta ?? ''}»`;
    }
});

function mover(paso: Destino): void {
    if (paso.exigeNota && destino.value?.valor !== paso.valor) {
        destino.value = paso;
        nota.value = '';

        return;
    }

    router.post(
        `/vulnerabilidades/${props.vulnerabilidad.id}/estado`,
        { estado: paso.valor, nota: nota.value || null },
        {
            preserveScroll: true,
            onStart: () => (enviando.value = true),
            onFinish: () => (enviando.value = false),
            onSuccess: () => {
                destino.value = null;
                nota.value = '';
            },
        },
    );
}

/* ---------------------------------------------------------------- Tareas */

const abriendoTarea = ref(false);
const tarea = useForm({ titulo: '', descripcion: '', prioridad: 'alta', responsable_id: '', fecha_limite: '' });

function abrirTarea(): void {
    tarea.post(`/vulnerabilidades/${props.vulnerabilidad.id}/tareas`, {
        preserveScroll: true,
        onSuccess: () => {
            abriendoTarea.value = false;
            tarea.reset();
        },
    });
}

const cuando = (fecha: string | null): string => (fecha ? formatoFechaHora.format(new Date(fecha)) : '—');
</script>

<template>
    <AppLayout :titulo="vulnerabilidad.codigo">
        <CabeceraPagina :titulo="vulnerabilidad.titulo" :codigo="vulnerabilidad.codigo">
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/vulnerabilidades/${vulnerabilidad.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: vulnerabilidad.estado,
                    etiqueta: vulnerabilidad.estadoEtiqueta,
                    tono: vulnerabilidad.estadoTono,
                    icono: vulnerabilidad.estadoIcono,
                }"
            />
            <CeldaBadge
                :valor="{
                    valor: vulnerabilidad.severidad,
                    etiqueta: vulnerabilidad.cvss ? `${vulnerabilidad.severidadEtiqueta} · CVSS ${vulnerabilidad.cvss}` : vulnerabilidad.severidadEtiqueta,
                    tono: vulnerabilidad.severidadTono,
                }"
            />
            <span v-if="vulnerabilidad.cve" class="cifra text-sm text-muted-foreground">{{ vulnerabilidad.cve }}</span>
        </div>

        <!-- El único rojo: el plazo que se comprometió y pasó sin arreglo. -->
        <Aviso v-if="vulnerabilidad.fueraDePlazo" tono="error" titulo="Fuera de plazo">
            Había que remediarla antes del {{ fechaLegible(vulnerabilidad.fechaLimite) }} y sigue sin arreglo. El plazo sale
            de su severidad y de lo que la organización fija en su ficha.
        </Aviso>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Qué es</CardTitle>
                        <CardDescription>{{ vulnerabilidad.origen }} · detectada el {{ fechaLegible(vulnerabilidad.fechaDeteccion) }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <p v-if="vulnerabilidad.descripcion" class="whitespace-pre-line">{{ vulnerabilidad.descripcion }}</p>
                        <dl class="grid gap-3">
                            <div v-if="vulnerabilidad.remediacion">
                                <dt class="font-medium">Cómo se arregla</dt>
                                <dd class="whitespace-pre-line text-muted-foreground">{{ vulnerabilidad.remediacion }}</dd>
                            </div>
                            <div v-if="vulnerabilidad.vector">
                                <dt class="font-medium">Vector CVSS</dt>
                                <dd class="cifra text-muted-foreground">{{ vulnerabilidad.vector }}</dd>
                            </div>
                            <div v-if="vulnerabilidad.motivoAceptacion">
                                <dt class="font-medium">Por qué se acepta</dt>
                                <dd class="whitespace-pre-line text-muted-foreground">
                                    {{ vulnerabilidad.motivoAceptacion }}
                                    <span class="block text-xs">
                                        {{ vulnerabilidad.aceptadaPor }} · {{ cuando(vulnerabilidad.aceptadaEn) }}
                                    </span>
                                </dd>
                            </div>
                            <div v-if="vulnerabilidad.verificacion">
                                <dt class="font-medium">Cómo se verificó el cierre</dt>
                                <dd class="whitespace-pre-line text-muted-foreground">
                                    {{ vulnerabilidad.verificacion }}
                                    <span class="block text-xs">
                                        {{ vulnerabilidad.verificadaPor }} · {{ cuando(vulnerabilidad.cerradaEn) }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Dónde está</CardTitle>
                        <CardDescription>Los activos afectados. Deciden también qué ve un auditor externo.</CardDescription>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <p v-if="activos.length === 0" class="text-muted-foreground">
                            Sin activos: no se sabe dónde está, y un auditor externo no la ve.
                        </p>
                        <ul v-else class="grid gap-1">
                            <li v-for="activo in activos" :key="activo.id">
                                <Link :href="`/activos/${activo.id}`" class="underline underline-offset-4">
                                    <span class="cifra">{{ activo.codigo }}</span> · {{ activo.nombre }}
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Tareas</CardTitle>
                        <CardDescription>
                            El trabajo de remediarla. Una tarea abierta aquí vence, si no se dice otra cosa, cuando vence el
                            plazo.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <p v-if="tareas.length === 0" class="text-muted-foreground">Ninguna abierta desde aquí.</p>
                        <ul v-else class="grid gap-1">
                            <li v-for="una in tareas" :key="una.id" class="flex flex-wrap items-center gap-2">
                                <Link :href="`/tareas/${una.id}`" class="underline underline-offset-4">{{ una.titulo }}</Link>
                                <CeldaBadge :valor="{ valor: una.estado, etiqueta: una.estado, tono: una.tono, icono: una.icono }" />
                            </li>
                        </ul>
                        <Button v-if="puedeAbrirTarea" variant="outline" size="sm" @click="abriendoTarea = true">
                            Abrir una tarea
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Histórico</CardTitle>
                        <CardDescription>El auditor no pregunta si está cerrada: pregunta cuánto tardó.</CardDescription>
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
                        <CardDescription v-if="vulnerabilidad.fechaLimite">
                            Plazo de remediación: {{ fechaLegible(vulnerabilidad.fechaLimite) }}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Aviso v-if="errorEstado" tono="error">{{ errorEstado }}</Aviso>

                        <!-- Sin esta línea, a quien sólo lee le queda una tarjeta con el título y nada debajo. -->
                        <p v-if="!puedeGestionar" class="text-sm text-muted-foreground">
                            El estado lo mueve quien gestiona las vulnerabilidades, y cada cambio queda en el
                            histórico, con quién y cuándo.
                        </p>

                        <div v-if="puedeGestionar && transiciones.length > 0" class="flex flex-wrap gap-2">
                            <BotonEstado
                                v-for="paso in transiciones"
                                :key="paso.valor"
                                :destino="paso"
                                :deshabilitado="enviando"
                                @click="mover(paso)"
                            />
                        </div>

                        <div v-if="destino" class="space-y-2 border-t pt-3">
                            <CampoTextarea v-model="nota" nombre="nota" :etiqueta="rotuloNota" :filas="3" requerido />
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="destino = null">Cancelar</Button>
                                <Button size="sm" :disabled="enviando || nota.trim() === ''" @click="mover(destino)">
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
                        <dl class="grid gap-2">
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Responsable</dt>
                                <dd>{{ vulnerabilidad.responsable ?? 'Sin asignar' }}</dd>
                            </div>
                            <div v-if="vulnerabilidad.proveedor" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Depende de</dt>
                                <dd>
                                    <Link :href="`/proveedores/${vulnerabilidad.proveedor.id}`" class="underline underline-offset-4">
                                        {{ vulnerabilidad.proveedor.nombre }}
                                    </Link>
                                </dd>
                            </div>
                            <div v-if="vulnerabilidad.riesgo" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Riesgo</dt>
                                <dd>
                                    <Link :href="`/riesgos/${vulnerabilidad.riesgo.id}`" class="underline underline-offset-4">
                                        <span class="cifra">{{ vulnerabilidad.riesgo.codigo }}</span>
                                    </Link>
                                </dd>
                            </div>
                            <div v-if="vulnerabilidad.incidente" class="flex flex-wrap gap-x-2">
                                <dt class="text-muted-foreground">Incidente</dt>
                                <dd>
                                    <Link :href="`/incidentes/${vulnerabilidad.incidente.id}`" class="underline underline-offset-4">
                                        <span class="cifra">{{ vulnerabilidad.incidente.codigo }}</span>
                                    </Link>
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="abriendoTarea">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Abrir una tarea</DialogTitle>
                    <DialogDescription>
                        Queda en el plan de acción con origen «Vulnerabilidad» y enlazada aquí. Sin fecha, vence con el plazo de
                        remediación.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4">
                    <CampoTexto v-model="tarea.titulo" nombre="titulo" etiqueta="Qué hay que hacer" :error="tarea.errors.titulo" requerido />
                    <CampoTextarea v-model="tarea.descripcion" nombre="descripcion" etiqueta="Detalle" :filas="2" :error="tarea.errors.descripcion" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <CampoSelect
                            v-model="tarea.prioridad"
                            nombre="prioridad"
                            etiqueta="Prioridad"
                            :opciones="prioridades"
                            :error="tarea.errors.prioridad"
                            requerido
                        />
                        <CampoTexto
                            v-model="tarea.fecha_limite"
                            nombre="fecha_limite"
                            etiqueta="Fecha límite"
                            tipo="date"
                            :error="tarea.errors.fecha_limite"
                        />
                    </div>
                    <CampoSelect
                        v-model="tarea.responsable_id"
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :error="tarea.errors.responsable_id"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="abriendoTarea = false">Cancelar</Button>
                    <Button :disabled="tarea.processing" @click="abrirTarea">Abrir</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
