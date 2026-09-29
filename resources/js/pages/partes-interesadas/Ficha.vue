<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
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
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible } from '@/lib/celdas';
import type { Opcion } from '@/lib/formularios';
import { tono } from '@/lib/tonos';
import { Link, router } from '@inertiajs/vue3';
import { ArchiveIcon, ChartLineIcon, CircleDotIcon, CircleOffIcon, FileTextIcon, PlusIcon, XIcon } from '@lucide/vue';
import { computed, ref } from 'vue';

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
 *
 * **La tira de cobertura tiene tres escalones y no dos**: obliga, tiene medida y la
 * medida está implantada. Con sólo los dos primeros, una obligación atada a una
 * medida sin iniciar se leía como cubierta. Las cifras las cuenta
 * `CoberturaParteInteresada`, no este fichero.
 *
 * La columna lateral va como en el resto de fichas —«Estado», «Ficha» y detrás lo
 * demás—, y por eso «Retirar» vive en «Estado» y no en la cabecera.
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
    sinCubrir: boolean;
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
    altaAnalisisId: number | null;
    altaFecha: string | null;
    bajaEn: string | null;
}

interface Cobertura {
    obligan: number;
    legales: number;
    contractuales: number;
    conMedida: number;
    conMedidaImplantada: number;
    sinMedida: number;
    pendientesDeImplantar: string[];
    citadasEnSoa: string[];
    medidasEnsAtadas: string[];
    partesConObligacionSinCubrir: number;
    cuentaEnElIndicador: boolean;
}

const props = defineProps<{
    parte: Parte;
    requisitos: Requisito[];
    cobertura: Cobertura;
    puedeGestionar: boolean;
    naturalezas: Opcion[];
    implantacionesDisponibles: Opcion[];
}>();

const enviando = ref(false);

/** El primero que obliga sin nada detrás: a donde lleva «N sin medida detrás». */
const primeroSinCubrir = computed(() => props.requisitos.find((requisito) => requisito.sinCubrir) ?? null);

const repartoObligan = computed(() =>
    [
        props.cobertura.legales > 0 ? `${props.cobertura.legales} ${props.cobertura.legales === 1 ? 'legal' : 'legales'}` : null,
        props.cobertura.contractuales > 0
            ? `${props.cobertura.contractuales} ${props.cobertura.contractuales === 1 ? 'contractual' : 'contractuales'}`
            : null,
    ]
        .filter(Boolean)
        .join(' · '),
);

function abrirVinculo(requisitoId: number): void {
    vinculando.value = requisitoId;
    implantacionElegida.value = '';
}

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
        <CabeceraPagina :titulo="parte.nombre" :codigo="parte.codigo" :descripcion="parte.descripcion">
            <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] font-medium text-secondary-foreground">
                <span>{{ parte.tipo }}</span>
                <span aria-hidden="true" class="text-border">|</span>
                <span>{{ parte.ambito }}</span>
                <span aria-hidden="true" class="text-border">|</span>
                <span class="text-muted-foreground">Atiende: {{ parte.responsable ?? 'sin asignar' }}</span>
            </p>

            <template #acciones>
                <Button v-if="puedeGestionar" variant="outline" size="sm" as-child>
                    <Link :href="`/partes-interesadas/${parte.id}/editar`">Editar la ficha</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <Card class="h-fit">
                <CardHeader class="flex flex-row flex-wrap items-start justify-between gap-2">
                    <div class="space-y-1">
                        <CardTitle>Qué exige o espera</CardTitle>
                        <CardDescription class="max-w-2xl">
                            Lo legal y lo contractual obligan y pueden justificar un control en la
                            Declaración de Aplicabilidad. Una expectativa se tiene en cuenta, pero no obliga.
                        </CardDescription>
                    </div>
                    <Button v-if="puedeGestionar" variant="outline" size="sm" @click="abrirEdicion">
                        Editar la lista
                    </Button>
                </CardHeader>

                <CardContent class="space-y-2">
                    <EstadoVacio
                        v-if="requisitos.length === 0"
                        titulo="Sin requisitos escritos"
                        descripcion="Una parte interesada sin nada anotado no contesta a la pregunta de la cláusula 4.2."
                    />

                    <!-- La tira: cada cifra con su denominador (DESIGN.md § 1). -->
                    <dl
                        v-else-if="cobertura.obligan > 0"
                        class="grid divide-y divide-border rounded-xl border border-border bg-superficie sm:grid-cols-3 sm:divide-x sm:divide-y-0"
                    >
                        <div class="flex flex-col gap-0.5 px-4 py-3.5">
                            <dt class="order-2 text-[13px] font-medium text-secondary-foreground">
                                {{ cobertura.obligan === 1 ? 'obliga' : 'obligan' }}
                            </dt>
                            <dd class="order-1 flex items-baseline gap-1.5">
                                <span class="cifra text-xl leading-7 font-semibold">{{ cobertura.obligan }}</span>
                                <span class="text-[13px] text-muted-foreground">de {{ requisitos.length }}</span>
                            </dd>
                            <dd class="order-3 text-xs text-muted-foreground">{{ repartoObligan }}</dd>
                        </div>
                        <div class="flex flex-col gap-0.5 px-4 py-3.5">
                            <dt class="order-2 text-[13px] font-medium text-secondary-foreground">con una medida atada</dt>
                            <dd class="order-1 flex items-baseline gap-1.5">
                                <span class="cifra text-xl leading-7 font-semibold">{{ cobertura.conMedida }}</span>
                                <span class="text-[13px] text-muted-foreground">de {{ cobertura.obligan }}</span>
                            </dd>
                            <dd class="order-3 text-xs">
                                <a
                                    v-if="primeroSinCubrir"
                                    :href="`#requisito-${primeroSinCubrir.id}`"
                                    class="text-primary hover:underline"
                                >
                                    {{ cobertura.sinMedida }} sin medida detrás
                                </a>
                                <span v-else class="text-muted-foreground">Ninguna sin medida</span>
                            </dd>
                        </div>
                        <div class="flex flex-col gap-0.5 px-4 py-3.5">
                            <dt class="order-2 text-[13px] font-medium text-secondary-foreground">con la medida implantada</dt>
                            <dd class="order-1 flex items-baseline gap-1.5">
                                <span class="cifra text-xl leading-7 font-semibold">{{ cobertura.conMedidaImplantada }}</span>
                                <span class="text-[13px] text-muted-foreground">de {{ cobertura.obligan }}</span>
                            </dd>
                            <dd class="order-3 text-xs text-muted-foreground">
                                <template v-if="cobertura.pendientesDeImplantar.length > 0">
                                    <span class="cifra">{{ cobertura.pendientesDeImplantar.join(', ') }}</span>
                                    sin implantar
                                </template>
                                <template v-else-if="cobertura.conMedida > 0">Todas las atadas, implantadas</template>
                                <template v-else>Sin medidas atadas todavía</template>
                            </dd>
                        </div>
                    </dl>

                    <p v-else class="text-sm text-muted-foreground">
                        Nada de lo anotado obliga: son expectativas, y no cuentan como laguna.
                    </p>

                    <article
                        v-for="requisito in requisitos"
                        :id="`requisito-${requisito.id}`"
                        :key="requisito.id"
                        class="grid scroll-mt-24 gap-3 border-b border-border py-5 last:border-b-0 last:pb-0 sm:grid-cols-[11rem_minmax(0,1fr)] sm:gap-4"
                    >
                        <div class="flex flex-wrap items-center gap-2 sm:flex-col sm:items-start">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="tono(requisito.naturalezaTono).badge"
                            >
                                <IconoTipo :nombre="requisito.naturalezaIcono" />
                                {{ requisito.naturalezaEtiqueta }}
                            </span>
                            <span v-if="requisito.referencia" class="cifra text-xs text-muted-foreground">
                                {{ requisito.referencia }}
                            </span>
                        </div>

                        <div class="min-w-0 space-y-3">
                            <p class="text-sm font-medium">{{ requisito.descripcion }}</p>

                            <div class="space-y-0.5">
                                <p class="text-xs text-muted-foreground">Cómo se atiende</p>
                                <p
                                    class="text-sm"
                                    :class="requisito.como_se_atiende ? 'text-secondary-foreground' : 'text-muted-foreground'"
                                >
                                    {{ requisito.como_se_atiende ?? 'Sin anotar todavía.' }}
                                </p>
                            </div>

                            <p v-if="requisito.es_climatico" class="text-xs text-muted-foreground">
                                Relacionado con el cambio climático (enmienda 1:2024).
                            </p>

                            <div v-if="requisito.implantaciones.length > 0" class="space-y-1.5">
                                <p class="text-xs text-muted-foreground">
                                    {{ requisito.implantaciones.length === 1 ? 'La medida que lo cubre' : 'Las medidas que lo cubren' }}
                                </p>
                                <ul class="space-y-1.5">
                                    <li
                                        v-for="implantacion in requisito.implantaciones"
                                        :key="implantacion.id"
                                        class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md bg-superficie py-1.5 pr-1.5 pl-3"
                                    >
                                        <Link
                                            :href="`/implantaciones/${implantacion.id}`"
                                            class="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-2 text-sm hover:underline"
                                        >
                                            <span class="cifra text-xs text-muted-foreground">{{ implantacion.codigo }}</span>
                                            <span class="font-medium">{{ implantacion.titulo }}</span>
                                            <span class="cifra text-xs text-muted-foreground">{{ implantacion.sistema }}</span>
                                        </Link>
                                        <span
                                            class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="tono(implantacion.estadoTono).badge"
                                        >
                                            <IconoTipo :nombre="implantacion.estadoIcono" />
                                            {{ implantacion.estado }}
                                        </span>
                                        <Button
                                            v-if="puedeGestionar"
                                            variant="ghost"
                                            size="icon-sm"
                                            :aria-label="`Quitar ${implantacion.codigo ?? 'la medida'} de este requisito`"
                                            @click="desvincular(requisito.id, implantacion.id)"
                                        >
                                            <XIcon class="size-4" />
                                        </Button>
                                    </li>
                                </ul>
                                <Button
                                    v-if="puedeGestionar"
                                    variant="ghost"
                                    size="sm"
                                    class="text-primary"
                                    @click="abrirVinculo(requisito.id)"
                                >
                                    <PlusIcon class="size-4" />
                                    Atar otra medida
                                </Button>
                            </div>

                            <!-- El hueco: neutro, no rojo. Una obligación sin medida es una
                                 pregunta pendiente, no un incumplimiento (contexto.md). -->
                            <div
                                v-else-if="requisito.sinCubrir"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-dashed border-input px-3.5 py-3"
                            >
                                <div class="flex min-w-0 items-start gap-2.5">
                                    <CircleOffIcon class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                    <div class="space-y-0.5">
                                        <p class="text-sm font-medium text-secondary-foreground">Sin ninguna medida detrás</p>
                                        <p class="text-xs text-muted-foreground">
                                            Obliga y nada lo cubre: cuenta en el indicador de obligaciones sin cubrir.
                                        </p>
                                    </div>
                                </div>
                                <Button v-if="puedeGestionar" size="sm" @click="abrirVinculo(requisito.id)">
                                    <PlusIcon class="size-4" />
                                    Atar una medida
                                </Button>
                            </div>

                            <Button
                                v-else-if="puedeGestionar"
                                variant="ghost"
                                size="sm"
                                class="text-primary"
                                @click="abrirVinculo(requisito.id)"
                            >
                                <PlusIcon class="size-4" />
                                Atar una medida
                            </Button>
                        </div>
                    </article>
                </CardContent>
            </Card>

            <div class="h-fit space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <span
                            v-if="parte.vigente"
                            class="inline-flex items-center gap-1.5 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-secondary-foreground"
                        >
                            <CircleDotIcon class="size-3.5" aria-hidden="true" />
                            Vigente
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="tono('no_aplica').badge"
                        >
                            <IconoTipo nombre="Archive" />
                            Retirada
                        </span>

                        <div v-if="!parte.vigente" class="space-y-1 text-[13px]">
                            <p class="text-muted-foreground">Retirada en {{ parte.bajaEn ?? '—' }}.</p>
                            <p v-if="parte.motivoBaja">{{ parte.motivoBaja }}</p>
                        </div>

                        <Button
                            v-if="puedeGestionar && parte.vigente"
                            variant="outline"
                            size="sm"
                            @click="retirando = true"
                        >
                            <ArchiveIcon class="size-4" />
                            Retirar con su motivo
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid grid-cols-[8rem_minmax(0,1fr)] gap-x-3 gap-y-2.5 text-sm">
                            <dt class="text-[13px] text-muted-foreground">Código</dt>
                            <dd class="cifra text-[13px]">{{ parte.codigo }}</dd>
                            <dt class="text-[13px] text-muted-foreground">Tipo</dt>
                            <dd>{{ parte.tipo }}</dd>
                            <dt class="text-[13px] text-muted-foreground">Ámbito</dt>
                            <dd>{{ parte.ambito }}</dd>
                            <dt class="text-[13px] text-muted-foreground">Quién la atiende</dt>
                            <dd :class="{ 'text-muted-foreground': !parte.responsable }">
                                {{ parte.responsable ?? 'Sin asignar' }}
                            </dd>
                            <dt class="text-[13px] text-muted-foreground">Dada de alta</dt>
                            <dd>
                                <Link
                                    v-if="parte.altaAnalisisId"
                                    :href="`/contexto/analisis/${parte.altaAnalisisId}`"
                                    class="text-primary hover:underline"
                                >
                                    {{ parte.altaEn }}
                                </Link>
                                <span v-else>—</span>
                                <span v-if="parte.altaFecha" class="text-muted-foreground">
                                    · {{ fechaLegible(parte.altaFecha) }}
                                </span>
                            </dd>
                        </dl>
                    </CardContent>
                </Card>

                <Card v-if="cobertura.obligan > 0">
                    <CardHeader>
                        <CardTitle>Dónde cuenta</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-3">
                            <li class="flex items-start gap-2.5">
                                <FileTextIcon class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                                <div class="space-y-0.5">
                                    <Link href="/documentos" class="text-sm font-medium text-primary hover:underline">
                                        Declaración de Aplicabilidad
                                    </Link>
                                    <p class="text-[13px] text-muted-foreground">
                                        <template v-if="cobertura.citadasEnSoa.length > 0">
                                            <span class="cifra">{{ cobertura.citadasEnSoa.join(', ') }}</span>
                                            {{ cobertura.citadasEnSoa.length === 1 ? 'imprime' : 'imprimen' }}
                                            «exigido por {{ parte.nombre }}» como justificación de inclusión.
                                        </template>
                                        <template v-else>
                                            No la cita: ningún control de ISO está atado a lo que obliga.
                                        </template>
                                        <template v-if="cobertura.medidasEnsAtadas.length > 0">
                                            <span class="cifra">{{ cobertura.medidasEnsAtadas.join(', ') }}</span>
                                            {{ cobertura.medidasEnsAtadas.length === 1 ? 'es una medida' : 'son medidas' }}
                                            del ENS, y la DdA justifica desde la categoría del sistema.
                                        </template>
                                    </p>
                                </div>
                            </li>
                            <li v-if="parte.vigente" class="flex items-start gap-2.5">
                                <ChartLineIcon class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                                <div class="space-y-0.5">
                                    <Link
                                        href="/partes-interesadas?filter[obligacion_sin_cubrir]=1"
                                        class="text-sm font-medium text-primary hover:underline"
                                    >
                                        Partes con obligaciones sin cubrir
                                    </Link>
                                    <p class="text-[13px] text-muted-foreground">
                                        <template v-if="cobertura.cuentaEnElIndicador && cobertura.partesConObligacionSinCubrir === 1">
                                            Es la única que cuenta hoy el indicador.
                                        </template>
                                        <template v-else-if="cobertura.cuentaEnElIndicador">
                                            Es una de las
                                            <span class="cifra">{{ cobertura.partesConObligacionSinCubrir }}</span>
                                            que cuenta hoy el indicador.
                                        </template>
                                        <template v-else>
                                            No cuenta: todo lo que obliga tiene una medida atada. Hoy son
                                            <span class="cifra">{{ cobertura.partesConObligacionSinCubrir }}</span>
                                            en la organización.
                                        </template>
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
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
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                            />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label :for="`naturaleza-${indice}`">Naturaleza</Label>
                                <select
                                    :id="`naturaleza-${indice}`"
                                    v-model="linea.naturaleza"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
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
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                                />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label :for="`atiende-${indice}`">Cómo se atiende</Label>
                            <textarea
                                :id="`atiende-${indice}`"
                                v-model="linea.como_se_atiende"
                                rows="2"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
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
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
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
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
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
