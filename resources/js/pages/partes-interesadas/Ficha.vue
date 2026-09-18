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
 * La ficha de una parte interesada: quién es y qué exige. Cláusula 4.2.
 *
 * **Los requisitos se editan como una lista y se guardan de una vez**, igual que la
 * lista de comprobación de una tarea: lo que se está escribiendo es «qué nos pide
 * este regulador», y eso se piensa mirando la lista entera. Partirlo en una ruta
 * por línea obligaría a guardar cuatro veces lo que se pensó una.
 *
 * Y de aquí sale la costura que paga el módulo: atar un requisito legal a la medida
 * que lo cubre es lo que permite que la Declaración de Aplicabilidad lo imprima
 * como justificación de inclusión.
 */

interface ImplantacionVinculada {
    id: number;
    codigo: string | null;
    titulo: string | null;
    sistema: string | null;
    estado: string;
    estadoTono: string;
    estadoIcono: string;
}

interface Requisito {
    id: number;
    descripcion: string;
    naturaleza: string;
    naturalezaEtiqueta: string;
    naturalezaTono: string;
    naturalezaIcono: string;
    obliga: boolean;
    es_climatico: boolean;
    referencia: string | null;
    como_se_atiende: string | null;
    implantaciones: ImplantacionVinculada[];
}

interface Linea {
    id: number | null;
    descripcion: string;
    naturaleza: string;
    es_climatico: boolean;
    referencia: string;
    como_se_atiende: string;
}

interface Parte {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
    ambito: string;
    descripcion: string | null;
    responsable: string | null;
    vigente: boolean;
    motivoBaja: string | null;
    altaEn: string | null;
    bajaEn: string | null;
}

const props = defineProps<{
    parte: Parte;
    requisitos: Requisito[];
    puedeGestionar: boolean;
    naturalezas: Opcion[];
    implantacionesDisponibles: Opcion[];
}>();

const enviando = ref(false);

/* Retirar, con su motivo. */
const retirando = ref(false);
const motivo = ref('');

function retirar(): void {
    enviando.value = true;
    router.post(
        `/partes-interesadas/${props.parte.id}/retirada`,
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

/* La lista de requisitos, editable y guardada entera. */
const editando = ref(false);
const lineas = ref<Linea[]>([]);

function abrirEdicion(): void {
    lineas.value = props.requisitos.map((requisito) => ({
        id: requisito.id,
        descripcion: requisito.descripcion,
        naturaleza: requisito.naturaleza,
        es_climatico: requisito.es_climatico,
        referencia: requisito.referencia ?? '',
        como_se_atiende: requisito.como_se_atiende ?? '',
    }));
    editando.value = true;
}

function anadirLinea(): void {
    lineas.value.push({
        id: null,
        descripcion: '',
        naturaleza: 'legal',
        es_climatico: false,
        referencia: '',
        como_se_atiende: '',
    });
}

function quitarLinea(indice: number): void {
    lineas.value.splice(indice, 1);
}

function guardarRequisitos(): void {
    enviando.value = true;
    router.put(
        `/partes-interesadas/${props.parte.id}/requisitos`,
        { requisitos: lineas.value },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
            },
            onSuccess: () => {
                editando.value = false;
            },
        },
    );
}

/* Atar una medida a un requisito. */
const vinculando = ref<number | null>(null);
const implantacionElegida = ref('');

function vincular(): void {
    if (vinculando.value === null || !implantacionElegida.value) {
        return;
    }

    enviando.value = true;
    router.post(
        `/partes-interesadas/${props.parte.id}/requisitos/${vinculando.value}/implantaciones`,
        { implantacion_id: Number(implantacionElegida.value) },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
            },
            onSuccess: () => {
                vinculando.value = null;
                implantacionElegida.value = '';
            },
        },
    );
}

function desvincular(requisitoId: number, implantacionId: number): void {
    router.delete(
        `/partes-interesadas/${props.parte.id}/requisitos/${requisitoId}/implantaciones/${implantacionId}`,
        { preserveScroll: true },
    );
}
</script>

<template>
    <AppLayout :titulo="`${parte.codigo} · ${parte.nombre}`">
        <CabeceraPagina :titulo="parte.nombre" :descripcion="parte.descripcion">
            <template #acciones>
                <Button v-if="puedeGestionar" variant="outline" size="sm" as-child>
                    <Link :href="`/partes-interesadas/${parte.id}/editar`">Editar</Link>
                </Button>
                <Button
                    v-if="puedeGestionar && parte.vigente"
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
                        <span class="cifra text-sm text-muted-foreground">{{ parte.codigo }}</span>
                        <span class="text-sm font-normal text-muted-foreground">
                            {{ parte.tipo }} · {{ parte.ambito }}
                        </span>
                        <span
                            v-if="!parte.vigente"
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
                            <dt class="text-xs text-muted-foreground">Quién la atiende</dt>
                            <dd>{{ parte.responsable ?? 'Sin asignar' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Dada de alta en</dt>
                            <dd>{{ parte.altaEn ?? '—' }}</dd>
                        </div>
                        <div v-if="!parte.vigente">
                            <dt class="text-xs text-muted-foreground">Retirada en</dt>
                            <dd>{{ parte.bajaEn ?? '—' }}</dd>
                        </div>
                        <div v-if="parte.motivoBaja" class="sm:col-span-2">
                            <dt class="text-xs text-muted-foreground">Por qué se retiró</dt>
                            <dd>{{ parte.motivoBaja }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row flex-wrap items-start justify-between gap-2">
                    <div>
                        <CardTitle>Qué exige o espera</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Lo legal y lo contractual obligan; una expectativa no. La diferencia
                            decide qué se cuenta como laguna y qué puede justificar la inclusión de
                            un control en la Declaración de Aplicabilidad.
                        </p>
                    </div>
                    <Button v-if="puedeGestionar" variant="outline" size="sm" @click="abrirEdicion">
                        Editar la lista
                    </Button>
                </CardHeader>

                <CardContent class="space-y-4">
                    <EstadoVacio
                        v-if="requisitos.length === 0"
                        titulo="Sin requisitos escritos"
                        descripcion="Una parte interesada sin nada anotado no contesta a la pregunta de la cláusula 4.2."
                    />

                    <article
                        v-for="requisito in requisitos"
                        :key="requisito.id"
                        class="space-y-2 rounded-xl border border-border p-4"
                    >
                        <header class="flex flex-wrap items-start justify-between gap-2">
                            <p class="min-w-0 text-sm">{{ requisito.descripcion }}</p>
                            <span
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="tono(requisito.naturalezaTono).badge"
                            >
                                <IconoTipo :nombre="requisito.naturalezaIcono" />
                                {{ requisito.naturalezaEtiqueta }}
                            </span>
                        </header>

                        <p v-if="requisito.referencia" class="cifra text-xs text-muted-foreground">
                            {{ requisito.referencia }}
                        </p>
                        <p v-if="requisito.como_se_atiende" class="text-sm text-muted-foreground">
                            {{ requisito.como_se_atiende }}
                        </p>
                        <p v-if="requisito.es_climatico" class="text-xs text-muted-foreground">
                            Relacionado con el cambio climático (enmienda 1:2024).
                        </p>

                        <ul v-if="requisito.implantaciones.length > 0" class="space-y-1">
                            <li
                                v-for="implantacion in requisito.implantaciones"
                                :key="implantacion.id"
                                class="flex flex-wrap items-center justify-between gap-2 text-sm"
                            >
                                <Link
                                    :href="`/implantaciones/${implantacion.id}`"
                                    class="min-w-0 hover:underline"
                                >
                                    <span class="cifra text-xs text-muted-foreground">
                                        {{ implantacion.codigo }}
                                    </span>
                                    {{ implantacion.titulo }}
                                    <span class="text-xs text-muted-foreground">({{ implantacion.sistema }})</span>
                                </Link>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="tono(implantacion.estadoTono).badge"
                                    >
                                        <IconoTipo :nombre="implantacion.estadoIcono" />
                                        {{ implantacion.estado }}
                                    </span>
                                    <Button
                                        v-if="puedeGestionar"
                                        variant="ghost"
                                        size="sm"
                                        @click="desvincular(requisito.id, implantacion.id)"
                                    >
                                        Quitar
                                    </Button>
                                </div>
                            </li>
                        </ul>

                        <p v-else-if="requisito.obliga" class="text-sm text-muted-foreground">
                            Sin ninguna medida detrás. Esto es lo que cuenta el indicador de
                            obligaciones sin cubrir.
                        </p>

                        <Button
                            v-if="puedeGestionar"
                            variant="outline"
                            size="sm"
                            @click="
                                vinculando = requisito.id;
                                implantacionElegida = '';
                            "
                        >
                            Atar una medida
                        </Button>
                    </article>
                </CardContent>
            </Card>
        </div>

        <!-- La lista entera, en una sola petición. -->
        <Dialog v-model:open="editando">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Qué exige {{ parte.nombre }}</DialogTitle>
                    <DialogDescription>
                        Se guarda la lista entera. Lo que quites de aquí se borra, junto con los
                        vínculos a las medidas que lo cubrían; lo que ya se firmó en un análisis
                        aprobado sigue congelado en su instantánea.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div
                        v-for="(linea, indice) in lineas"
                        :key="indice"
                        class="space-y-3 rounded-xl border border-border p-3"
                    >
                        <div class="space-y-1.5">
                            <Label :for="`descripcion-${indice}`">Qué pide</Label>
                            <textarea
                                :id="`descripcion-${indice}`"
                                v-model="linea.descripcion"
                                rows="2"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label :for="`naturaleza-${indice}`">Naturaleza</Label>
                                <select
                                    :id="`naturaleza-${indice}`"
                                    v-model="linea.naturaleza"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <option v-for="opcion in naturalezas" :key="opcion.valor" :value="opcion.valor">
                                        {{ opcion.etiqueta }}
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-1.5">
                                <Label :for="`referencia-${indice}`">Referencia</Label>
                                <input
                                    :id="`referencia-${indice}`"
                                    v-model="linea.referencia"
                                    type="text"
                                    placeholder="RD 311/2022, anexo II"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label :for="`atiende-${indice}`">Cómo se atiende</Label>
                            <textarea
                                :id="`atiende-${indice}`"
                                v-model="linea.como_se_atiende"
                                rows="2"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            />
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="linea.es_climatico" type="checkbox" class="size-4" />
                                Relacionado con el cambio climático
                            </label>
                            <Button variant="ghost" size="sm" @click="quitarLinea(indice)">Quitar</Button>
                        </div>
                    </div>

                    <Button variant="outline" size="sm" @click="anadirLinea">Añadir un requisito</Button>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="enviando" @click="editando = false">Cancelar</Button>
                    <Button :disabled="enviando" @click="guardarRequisitos">Guardar la lista</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Atar una medida. -->
        <Dialog :open="vinculando !== null" @update:open="(abierto) => { if (!abierto) vinculando = null; }">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Atar una medida</DialogTitle>
                    <DialogDescription>
                        Sólo se ofrecen las medidas aplicables: un requisito legal no se cubre con un
                        control que al sistema no se le exige.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-1.5">
                    <Label for="implantacion">Medida</Label>
                    <select
                        id="implantacion"
                        v-model="implantacionElegida"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option value="">Elige una medida…</option>
                        <option
                            v-for="opcion in implantacionesDisponibles"
                            :key="opcion.valor"
                            :value="opcion.valor"
                        >
                            {{ opcion.etiqueta }}
                        </option>
                    </select>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="enviando" @click="vinculando = null">Cancelar</Button>
                    <Button :disabled="enviando || !implantacionElegida" @click="vincular">Atar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Retirar, con su motivo obligatorio. -->
        <Dialog v-model:open="retirando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Retirar {{ parte.codigo }}</DialogTitle>
                    <DialogDescription>
                        Se queda en el registro con su motivo, y sus requisitos no se tocan. Es lo
                        que explicará por qué este año hay una parte interesada menos.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-1.5">
                    <Label for="motivo-parte">Por qué se retira</Label>
                    <textarea
                        id="motivo-parte"
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
    </AppLayout>
</template>
