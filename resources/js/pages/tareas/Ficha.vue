<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import BotonEstado from '@/components/BotonEstado.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import ListaComprobacion, { type Paso } from '@/components/tarea/ListaComprobacion.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFecha } from '@/lib/celdas';
import { Link, router } from '@inertiajs/vue3';
import { ListTodoIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, ref } from 'vue';

interface Vinculo {
    implantacionId: number;
    codigo: string;
    titulo: string;
    marco: string | null;
    sistema: string;
    estado: string;
    estadoEtiqueta: string;
}

interface Transicion {
    id: number;
    anterior: string | null;
    nuevo: string;
    tono: string;
    quien: string | null;
    cuando: string;
    nota: string | null;
}

const props = defineProps<{
    tarea: {
        id: number;
        titulo: string;
        descripcion: string | null;
        origen: string;
        origenEtiqueta: string;
        estado: string;
        estadoEtiqueta: string;
        estadoTono: string;
        prioridad: string;
        prioridadEtiqueta: string;
        responsable: string | null;
        fecha_limite: string | null;
        fecha_cierre: string | null;
        haVencido: boolean;
        coste_estimado: string | null;
        notas: string | null;
    };
    vinculos: Vinculo[];
    historico: Transicion[];
    transiciones: { valor: string; etiqueta: string; tono: string; icono: string }[];
    subtareas: Paso[];
    maximoSubtareas: number;
    puedeGestionar: boolean;
}>();

const { variantesEntrada } = useMovimientoReducido();

const fecha = (valor: string | null): string => (valor ? formatoFecha.format(new Date(valor)) : '—');

const fechaHora = (valor: string): string =>
    new Date(valor).toLocaleString('es-ES', { dateStyle: 'medium', timeStyle: 'short' });

const coste = computed(() =>
    props.tarea.coste_estimado === null
        ? null
        : `${Number(props.tarea.coste_estimado).toLocaleString('es-ES', { minimumFractionDigits: 2 })} €`,
);

/*
 * Descartar exige motivo y el resto no. Pedirlo siempre convertiría en un
 * trámite el único sitio donde se dice por qué no se va a hacer algo.
 */
const destino = ref<string | null>(null);
const nota = ref('');
const enviando = ref(false);

const exigeNota = computed(() => destino.value === 'descartada');

function mover(estado: string): void {
    if (estado === 'descartada' && destino.value !== 'descartada') {
        destino.value = 'descartada';

        return;
    }

    enviando.value = true;

    router.post(
        `/tareas/${props.tarea.id}/estado`,
        { estado, nota: nota.value || null },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
                destino.value = null;
                nota.value = '';
            },
        },
    );
}
</script>

<template>
    <AppLayout :titulo="tarea.titulo">
        <CabeceraPagina :titulo="tarea.titulo" :descripcion="tarea.descripcion">
            <template #acciones>
                <Link :href="`/tareas/${tarea.id}/editar`">
                    <Button variant="outline">Editar</Button>
                </Link>
            </template>
        </CabeceraPagina>

        <motion.div
            :variants="variantesEntrada"
            initial="oculto"
            animate="visible"
            class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
        >
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Qué hace avanzar</CardTitle>
                        <CardDescription>
                            Los requisitos que esta tarea deja más cerca de estar cumplidos, de cualquier marco.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <EstadoVacio
                            v-if="vinculos.length === 0"
                            :icono="ListTodoIcon"
                            titulo="No está vinculada a ningún requisito"
                            descripcion="Una tarea suelta se hace igual, pero no cuenta en ningún marco ni aparece en la ficha de ningún requisito."
                            :accion="{ etiqueta: 'Ir a implantaciones', href: '/implantaciones' }"
                        />

                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="vinculo in vinculos"
                                :key="vinculo.implantacionId"
                                class="py-3 first:pt-0 last:pb-0"
                            >
                                <Link
                                    :href="`/implantaciones/${vinculo.implantacionId}`"
                                    class="group flex flex-wrap items-center gap-x-3 gap-y-1.5"
                                >
                                    <span class="cifra text-sm font-medium group-hover:underline">
                                        {{ vinculo.codigo }}
                                    </span>
                                    <CeldaBadge
                                        v-if="vinculo.marco"
                                        :valor="{ valor: vinculo.marco, etiqueta: vinculo.marco, tono: 'marco' }"
                                    />
                                    <CeldaBadge
                                        :valor="{
                                            valor: vinculo.estado,
                                            etiqueta: vinculo.estadoEtiqueta,
                                            tono: vinculo.estado,
                                        }"
                                    />
                                    <span class="cifra text-xs text-muted-foreground">{{ vinculo.sistema }}</span>
                                </Link>

                                <p class="mt-1 text-sm text-muted-foreground">{{ vinculo.titulo }}</p>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!--
                    La lista de comprobación va antes que el histórico: es lo que
                    se toca cada día. Y va en la columna ancha porque se escribe,
                    no sólo se lee.
                -->
                <Card>
                    <CardHeader>
                        <CardTitle>Lista de comprobación</CardTitle>
                        <CardDescription>
                            Los pasos de esta tarea. No son tareas: no tienen responsable ni plazo, y no cuentan
                            en el panel ni en los avisos — la tarea sigue siendo la unidad.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <ListaComprobacion
                            :tarea-id="tarea.id"
                            :pasos="subtareas"
                            :maximo="maximoSubtareas"
                            :editable="puedeGestionar"
                        />
                    </CardContent>
                </Card>

                <!--
                    El histórico no es decoración: el auditor no pregunta si la
                    tarea está cerrada, pregunta desde cuándo — y si se descartó,
                    por qué.
                -->
                <Card>
                    <CardHeader>
                        <CardTitle>Histórico</CardTitle>
                        <CardDescription>Cada cambio de estado, con su fecha y quién lo hizo.</CardDescription>
                    </CardHeader>

                    <CardContent>
                        <ol class="space-y-4">
                            <li v-for="paso in historico" :key="paso.id" class="flex gap-3 text-sm">
                                <CeldaBadge :valor="{ valor: paso.nuevo, etiqueta: paso.nuevo, tono: paso.tono }" />

                                <div class="min-w-0">
                                    <p class="text-muted-foreground">
                                        <template v-if="paso.anterior">desde «{{ paso.anterior }}» · </template>
                                        {{ fechaHora(paso.cuando) }}
                                        <template v-if="paso.quien"> · {{ paso.quien }}</template>
                                    </p>
                                    <p v-if="paso.nota" class="mt-0.5">{{ paso.nota }}</p>
                                </div>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-sm text-muted-foreground">Ahora</span>
                            <CeldaBadge
                                :valor="{
                                    valor: tarea.estado,
                                    etiqueta: tarea.estadoEtiqueta,
                                    tono: tarea.estadoTono,
                                }"
                            />
                        </div>

                        <!--
                            Cada botón se parece al badge que vas a obtener al
                            pulsarlo, para no tener que leerlos uno a uno.
                        -->
                        <div class="flex flex-wrap gap-2">
                            <BotonEstado
                                v-for="paso in transiciones"
                                :key="paso.valor"
                                :destino="paso"
                                :deshabilitado="enviando"
                                @click="mover(paso.valor)"
                            />
                        </div>

                        <div v-if="exigeNota" class="space-y-2">
                            <label for="nota-descarte" class="text-sm font-medium">
                                Por qué se descarta
                            </label>
                            <textarea
                                id="nota-descarte"
                                v-model="nota"
                                rows="3"
                                class="w-full rounded-md border border-border bg-background p-2 text-sm"
                                placeholder="El servicio se retira en octubre y la medida deja de aplicar."
                            />
                            <div class="flex gap-2">
                                <Button size="sm" :disabled="enviando || nota.trim() === ''" @click="mover('descartada')">
                                    Descartar
                                </Button>
                                <Button size="sm" variant="ghost" @click="destino = null">Cancelar</Button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Es una decisión que un auditor puede cuestionar: sin motivo, el requisito se queda sin
                                rastro de qué se hizo con él.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>La tarea</CardTitle>
                    </CardHeader>

                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Origen</span>
                            <span class="text-right">{{ tarea.origenEtiqueta }}</span>
                        </div>

                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Prioridad</span>
                            <span>{{ tarea.prioridadEtiqueta }}</span>
                        </div>

                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Responsable</span>
                            <span class="text-right">{{ tarea.responsable ?? 'Sin asignar' }}</span>
                        </div>

                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Fecha límite</span>
                            <span :class="tarea.haVencido ? 'text-destructive font-medium' : ''">
                                {{ fecha(tarea.fecha_limite) }}
                            </span>
                        </div>

                        <div v-if="tarea.fecha_cierre" class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Cerrada</span>
                            <span>{{ fecha(tarea.fecha_cierre) }}</span>
                        </div>

                        <div v-if="coste" class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Coste estimado</span>
                            <span>{{ coste }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="tarea.notas">
                    <CardHeader>
                        <CardTitle>Notas</CardTitle>
                    </CardHeader>

                    <CardContent>
                        <p class="text-sm whitespace-pre-line">{{ tarea.notas }}</p>
                    </CardContent>
                </Card>
            </div>
        </motion.div>
    </AppLayout>
</template>
