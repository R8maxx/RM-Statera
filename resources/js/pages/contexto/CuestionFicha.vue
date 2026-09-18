<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Opcion } from '@/lib/formularios';
import { tono } from '@/lib/tonos';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * La ficha de una cuestión del contexto.
 *
 * De aquí salen los dos vínculos que hacen que el DAFO no sea un papel suelto: el
 * riesgo que la cuestión abrió —ISO 6.1.1 pide que la apreciación de riesgos se
 * haga **considerando** las cuestiones del 4.1— y el trabajo que genera.
 *
 * **Retirar y eliminar son dos botones distintos y el orden importa.** Retirar deja
 * la cuestión en el registro con su motivo, que es lo que explicará en la próxima
 * revisión por la dirección por qué ya no está; eliminar se lleva por delante los
 * riesgos y las tareas vinculados. El primero es el que se usa.
 */

interface Cuestion {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string | null;
    tipo: { valor: string; etiqueta: string; tono: string; icono: string };
    ambito: string;
    ambitoAyuda: string;
    signo: string;
    materia: string;
    esClimatica: boolean;
    responsable: string | null;
    vigente: boolean;
    motivoBaja: string | null;
    altaEn: string | null;
    bajaEn: string | null;
}

interface RiesgoVinculado {
    id: number;
    codigo: string;
    titulo: string;
}

interface TareaVinculada {
    id: number;
    titulo: string;
    estado: string;
    estadoTono: string;
    estadoIcono: string;
    responsable: string | null;
    plazoEtiqueta: string;
    plazoTono: string;
}

const props = defineProps<{
    cuestion: Cuestion;
    riesgos: RiesgoVinculado[];
    tareas: TareaVinculada[];
    puedeGestionar: boolean;
    prioridades: Opcion[];
    riesgosDisponibles: Opcion[];
    tareasDisponibles: Opcion[];
}>();

const enviando = ref(false);

/* Retirar. */
const retirando = ref(false);
const motivo = ref('');

function retirar(): void {
    enviando.value = true;
    router.post(
        `/contexto/cuestiones/${props.cuestion.id}/retirada`,
        { motivo: motivo.value },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
            },
            onSuccess: () => {
                retirando.value = false;
                motivo.value = '';
            },
        },
    );
}

/* Vincular un riesgo que ya existe. */
const riesgoElegido = ref('');

function vincularRiesgo(): void {
    if (!riesgoElegido.value) {
        return;
    }

    enviando.value = true;
    router.post(
        `/contexto/cuestiones/${props.cuestion.id}/riesgos`,
        { riesgo_id: Number(riesgoElegido.value) },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
                riesgoElegido.value = '';
            },
        },
    );
}

function desvincularRiesgo(id: number): void {
    router.delete(`/contexto/cuestiones/${props.cuestion.id}/riesgos/${id}`, { preserveScroll: true });
}

/* Abrir una tarea nueva. */
const abriendoTarea = ref(false);
const tarea = ref({ titulo: '', descripcion: '', prioridad: 'media', fecha_limite: '' });

function abrirTarea(): void {
    enviando.value = true;
    router.post(
        `/contexto/cuestiones/${props.cuestion.id}/tareas`,
        {
            titulo: tarea.value.titulo,
            descripcion: tarea.value.descripcion || null,
            prioridad: tarea.value.prioridad,
            fecha_limite: tarea.value.fecha_limite || null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
            },
            onSuccess: () => {
                abriendoTarea.value = false;
                tarea.value = { titulo: '', descripcion: '', prioridad: 'media', fecha_limite: '' };
            },
        },
    );
}

const tareaElegida = ref('');

function vincularTarea(): void {
    if (!tareaElegida.value) {
        return;
    }

    enviando.value = true;
    router.post(
        `/contexto/cuestiones/${props.cuestion.id}/tareas/vincular`,
        { tarea_id: Number(tareaElegida.value) },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
                tareaElegida.value = '';
            },
        },
    );
}

function desvincularTarea(id: number): void {
    router.delete(`/contexto/cuestiones/${props.cuestion.id}/tareas/${id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :titulo="`${cuestion.codigo} · ${cuestion.titulo}`">
        <CabeceraPagina :titulo="cuestion.titulo" :descripcion="cuestion.descripcion">
            <template #acciones>
                <Button v-if="puedeGestionar" variant="outline" size="sm" as-child>
                    <Link :href="`/contexto/cuestiones/${cuestion.id}/editar`">Editar</Link>
                </Button>
                <Button
                    v-if="puedeGestionar && cuestion.vigente"
                    variant="outline"
                    size="sm"
                    @click="retirando = true"
                >
                    Retirar
                </Button>
            </template>
        </CabeceraPagina>

        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="flex flex-wrap items-center gap-2">
                        <span class="cifra text-sm text-muted-foreground">{{ cuestion.codigo }}</span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="tono(cuestion.tipo.tono).badge"
                        >
                            <IconoTipo :nombre="cuestion.tipo.icono" />
                            {{ cuestion.tipo.etiqueta }}
                        </span>
                        <span
                            v-if="!cuestion.vigente"
                            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="tono('no_aplica').badge"
                        >
                            <IconoTipo nombre="Archive" />
                            Retirada
                        </span>
                    </CardTitle>
                </CardHeader>

                <CardContent>
                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">Ámbito</dt>
                            <dd :title="cuestion.ambitoAyuda">{{ cuestion.ambito }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Signo</dt>
                            <dd>{{ cuestion.signo }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Materia</dt>
                            <dd>{{ cuestion.materia }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Responsable</dt>
                            <dd>{{ cuestion.responsable ?? 'Sin asignar' }}</dd>
                        </div>
                        <div v-if="cuestion.esClimatica" class="sm:col-span-2">
                            <dt class="text-xs text-muted-foreground">Cambio climático</dt>
                            <dd>Declarada como cuestión relacionada con el cambio climático (enmienda 1:2024).</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Dada de alta en</dt>
                            <dd>{{ cuestion.altaEn ?? '—' }}</dd>
                        </div>
                        <div v-if="!cuestion.vigente">
                            <dt class="text-xs text-muted-foreground">Retirada en</dt>
                            <dd>{{ cuestion.bajaEn ?? '—' }}</dd>
                        </div>
                        <div v-if="cuestion.motivoBaja" class="sm:col-span-2">
                            <dt class="text-xs text-muted-foreground">Por qué se retiró</dt>
                            <dd>{{ cuestion.motivoBaja }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <!-- Los riesgos que abrió. -->
            <Card>
                <CardHeader>
                    <CardTitle>Riesgos que salen de aquí</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        ISO 6.1.1 pide que la apreciación de riesgos se haga considerando las
                        cuestiones del contexto. Esto es lo que lo deja por escrito.
                    </p>
                </CardHeader>
                <CardContent class="space-y-3">
                    <ul v-if="riesgos.length > 0" class="divide-y divide-border">
                        <li v-for="riesgo in riesgos" :key="riesgo.id" class="flex items-center justify-between gap-3 py-2">
                            <Link :href="`/riesgos/${riesgo.id}`" class="min-w-0 text-sm hover:underline">
                                <span class="cifra text-xs text-muted-foreground">{{ riesgo.codigo }}</span>
                                {{ riesgo.titulo }}
                            </Link>
                            <Button
                                v-if="puedeGestionar"
                                variant="ghost"
                                size="sm"
                                @click="desvincularRiesgo(riesgo.id)"
                            >
                                Desvincular
                            </Button>
                        </li>
                    </ul>

                    <EstadoVacio
                        v-else
                        titulo="Sin riesgos vinculados"
                        descripcion="Si esta cuestión no acaba en ningún riesgo, conviene poder decir por qué."
                    />

                    <div v-if="puedeGestionar && riesgosDisponibles.length > 0" class="flex flex-wrap items-end gap-2">
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <Label for="riesgo">Vincular un riesgo ya registrado</Label>
                            <select
                                id="riesgo"
                                v-model="riesgoElegido"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="">Elige un riesgo…</option>
                                <option v-for="opcion in riesgosDisponibles" :key="opcion.valor" :value="opcion.valor">
                                    {{ opcion.etiqueta }}
                                </option>
                            </select>
                        </div>
                        <Button variant="outline" :disabled="enviando || !riesgoElegido" @click="vincularRiesgo">
                            Vincular
                        </Button>
                    </div>

                    <!--
                        No se ofrece crear el riesgo desde aquí: un riesgo necesita
                        probabilidad, impacto y propietario, y deducirlo de una
                        amenaza produciría riesgos que ISO 6.1.3 f) no admite.
                    -->
                    <p v-if="puedeGestionar" class="text-xs text-muted-foreground">
                        ¿No está registrado?
                        <Link href="/riesgos/crear" class="text-primary hover:underline">Regístralo primero</Link>
                        y vuelve a vincularlo: un riesgo necesita su probabilidad, su impacto y su
                        propietario, y eso no se deduce de una cuestión.
                    </p>
                </CardContent>
            </Card>

            <!-- El trabajo que genera. -->
            <Card>
                <CardHeader class="flex flex-row flex-wrap items-start justify-between gap-2">
                    <div>
                        <CardTitle>Qué se está haciendo</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Tareas de pleno derecho, con su responsable y su plazo: salen en el
                            tablero, en el calendario y en el aviso diario.
                        </p>
                    </div>
                    <Button v-if="puedeGestionar" variant="outline" size="sm" @click="abriendoTarea = true">
                        Nueva tarea
                    </Button>
                </CardHeader>
                <CardContent class="space-y-3">
                    <ul v-if="tareas.length > 0" class="divide-y divide-border">
                        <li v-for="item in tareas" :key="item.id" class="flex flex-wrap items-center justify-between gap-3 py-2">
                            <Link :href="`/tareas/${item.id}`" class="min-w-0 text-sm hover:underline">
                                {{ item.titulo }}
                            </Link>
                            <div class="flex shrink-0 items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="tono(item.estadoTono).badge"
                                >
                                    <IconoTipo :nombre="item.estadoIcono" />
                                    {{ item.estado }}
                                </span>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="tono(item.plazoTono).badge"
                                >
                                    {{ item.plazoEtiqueta }}
                                </span>
                                <Button
                                    v-if="puedeGestionar"
                                    variant="ghost"
                                    size="sm"
                                    @click="desvincularTarea(item.id)"
                                >
                                    Desvincular
                                </Button>
                            </div>
                        </li>
                    </ul>

                    <EstadoVacio
                        v-else
                        titulo="Nada en marcha"
                        descripcion="Una debilidad sin nada detrás sigue siendo una debilidad el año que viene."
                    />

                    <div v-if="puedeGestionar && tareasDisponibles.length > 0" class="flex flex-wrap items-end gap-2">
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <Label for="tarea">Vincular una tarea que ya existe</Label>
                            <select
                                id="tarea"
                                v-model="tareaElegida"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="">Elige una tarea…</option>
                                <option v-for="opcion in tareasDisponibles" :key="opcion.valor" :value="opcion.valor">
                                    {{ opcion.etiqueta }}
                                </option>
                            </select>
                        </div>
                        <Button variant="outline" :disabled="enviando || !tareaElegida" @click="vincularTarea">
                            Vincular
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Retirar, con su motivo obligatorio. -->
        <Dialog v-model:open="retirando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Retirar {{ cuestion.codigo }}</DialogTitle>
                    <DialogDescription>
                        La cuestión se queda en el registro con su motivo, y los riesgos y tareas que
                        colgaban de ella no se tocan. Es lo que explicará, en la próxima revisión por
                        la dirección, por qué este año hay una cuestión menos.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-1.5">
                    <Label for="motivo">Por qué se retira</Label>
                    <textarea
                        id="motivo"
                        v-model="motivo"
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="enviando" @click="retirando = false">Cancelar</Button>
                    <Button :disabled="enviando || motivo.trim().length < 3" @click="retirar">Retirar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Abrir una tarea. -->
        <Dialog v-model:open="abriendoTarea">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nueva tarea</DialogTitle>
                    <DialogDescription>
                        El origen se pone solo —«cuestión del contexto»— y no se pregunta:
                        preguntarlo invita a cambiarlo, y entonces deja de explicar nada.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="tarea-titulo">Título</Label>
                        <input
                            id="tarea-titulo"
                            v-model="tarea.titulo"
                            type="text"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="tarea-descripcion">Descripción</Label>
                        <textarea
                            id="tarea-descripcion"
                            v-model="tarea.descripcion"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="tarea-prioridad">Prioridad</Label>
                        <select
                            id="tarea-prioridad"
                            v-model="tarea.prioridad"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option v-for="opcion in prioridades" :key="opcion.valor" :value="opcion.valor">
                                {{ opcion.etiqueta }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="tarea-fecha">Fecha límite</Label>
                        <input
                            id="tarea-fecha"
                            v-model="tarea.fecha_limite"
                            type="date"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="enviando" @click="abriendoTarea = false">Cancelar</Button>
                    <Button :disabled="enviando || tarea.titulo.trim() === ''" @click="abrirTarea">
                        Abrir tarea
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
