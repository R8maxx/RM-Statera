<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import FilaCampos from '@/components/formulario/FilaCampos.vue';
import CampoBase from '@/components/formulario/CampoBase.vue';
import CampoOpciones from '@/components/formulario/CampoOpciones.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoSwitch from '@/components/formulario/CampoSwitch.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import MensajeError from '@/components/formulario/MensajeError.vue';
import HistoricoTransiciones, { type Transicion } from '@/components/HistoricoTransiciones.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import BloqueEvidencias, { type EvidenciaVinculada } from '@/components/implantacion/BloqueEvidencias.vue';
import MapeoCruzado from '@/components/implantacion/MapeoCruzado.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFecha } from '@/lib/celdas';
import { conOpcionVacia, SIN_VALOR, type Opcion } from '@/lib/formularios';
import { tono } from '@/lib/tonos';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon, ChevronRightIcon, InfoIcon, PlusIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import { computed } from 'vue';

type Correspondencia = App.Http.Resources.Implantacion.Correspondencia;

interface Destino extends Opcion {
    tono: string;
    icono: string;
}

const props = defineProps<{
    implantacion: {
        id: number;
        aplica: boolean;
        justificacion: string | null;
        estado: string;
        estadoEtiqueta: string;
        nivel_madurez: string | null;
        responsable_id: number | null;
        fecha_objetivo: string | null;
        notas: string | null;
    };
    requisito: {
        codigo: string;
        titulo: string;
        descripcion: string | null;
        marco: string | null;
        atributos: Record<string, unknown> | null;
        ruta: { codigo: string; titulo: string }[];
    };
    sistema: { id: number; codigo: string; nombre: string; categoria: string | null };
    exigencia: {
        valor: string | null;
        etiqueta: string | null;
        origen: string | null;
        dimension: string | null;
        excluibleAMano: boolean;
    };
    transicionesPermitidas: Destino[];
    historico: Transicion[];
    evidencias: EvidenciaVinculada[];
    tareas: TareaVinculada[];
    /** Prop opcional: sólo llega cuando el diálogo de adjuntar la pide. */
    evidenciasDisponibles?: Opcion[];
    correspondencias: Correspondencia[];
    responsables: Opcion[];
    niveles: Opcion[];
    puedeEscribir: boolean;
    aCargoDeOtro: string | null;
}>();

interface TareaVinculada {
    id: number;
    titulo: string;
    estado: string;
    estadoEtiqueta: string;
    tono: string;
    prioridad: string;
    responsable: string | null;
    fecha_limite: string | null;
    haVencido: boolean;
    abierta: boolean;
}

const { variantesEntrada } = useMovimientoReducido();

const responsables = computed<Opcion[]>(() => conOpcionVacia(props.responsables, 'Sin responsable'));

/*
 * La madurez se elige de un clic (DESIGN.md §9: una escala corta que ordena).
 * Seis etiquetas enteras —«L2 — Reproducible pero intuitivo»— no caben en una
 * fila, así que el botón lleva la forma corta y la ayuda dice la larga del
 * nivel elegido.
 */
const corta = (etiqueta: string): string => etiqueta.split(' — ')[0];
const niveles = computed<Opcion[]>(() =>
    conOpcionVacia(
        props.niveles.map((nivel) => ({ valor: nivel.valor, etiqueta: corta(nivel.etiqueta) })),
        'Sin evaluar',
    ),
);

const gestion = useForm({
    aplica: props.implantacion.aplica,
    justificacion: props.implantacion.justificacion ?? '',
    responsable_id: props.implantacion.responsable_id
        ? String(props.implantacion.responsable_id)
        : SIN_VALOR,
    fecha_objetivo: props.implantacion.fecha_objetivo ?? '',
    nivel_madurez: props.implantacion.nivel_madurez ?? SIN_VALOR,
    notas: props.implantacion.notas ?? '',
});

const ayudaMadurez = computed(
    () =>
        props.niveles.find((nivel) => nivel.valor === gestion.nivel_madurez)?.etiqueta ??
        'Escala L0–L5 del CCN, la que pide el informe INES. No es lo mismo que el estado.',
);

const transicion = useForm({ estado: undefined as string | undefined, nota: '' });

function guardar(): void {
    // Los centinelas de «ninguno» los traduce el `FormRequest`
    // (`NormalizaSeleccionVacia`), que es el único sitio donde ese valor se
    // interpreta. Aquí sólo quedan las cadenas vacías de los campos de texto.
    gestion
        .transform((datos) => ({
            ...datos,
            fecha_objetivo: datos.fecha_objetivo === '' ? null : datos.fecha_objetivo,
            justificacion: datos.justificacion === '' ? null : datos.justificacion,
        }))
        .put(`/implantaciones/${props.implantacion.id}`, { preserveScroll: true });
}

function cambiarEstado(): void {
    transicion.post(`/implantaciones/${props.implantacion.id}/estado`, {
        preserveScroll: true,
        onSuccess: () => transicion.reset(),
    });
}

const fecha = (valor: string): string => formatoFecha.format(new Date(valor));

/*
 * «¿Desde cuándo?», que es lo que pregunta el auditor (invariante 7), junto al
 * estado y no al fondo del histórico. Es la última transición: el histórico
 * llega del más antiguo al más reciente.
 */
const ultimoCambio = computed<Transicion | null>(() => props.historico.at(-1) ?? null);

/*
 * La madurez como pasos: lo que se pregunta es si L4 es más que L2, y eso lo
 * contesta la longitud antes que el texto (DESIGN.md §9, valor ordinal).
 */
const madurez = computed(() => {
    const indice = props.niveles.findIndex((nivel) => nivel.valor === props.implantacion.nivel_madurez);

    return indice === -1 ? null : { paso: indice, etiqueta: props.niveles[indice].etiqueta };
});

const tareasAbiertas = computed(() => props.tareas.filter((tarea) => tarea.abierta).length);
const tareasVencidas = computed(() => props.tareas.filter((tarea) => tarea.abierta && tarea.haVencido).length);
const evidenciasCaducadas = computed(() => props.evidencias.filter((evidencia) => evidencia.haCaducado).length);

/*
 * Lo que un auditor va a pedir y falta (DESIGN.md §9, «Lo que falta»). Sólo
 * sobre lo exigible: un requisito excluido no debe responsable ni prueba, y
 * su exclusión ya dice por qué.
 */
const pendientes = computed<{ etiqueta: string; ancla: string }[]>(() => {
    if (!props.implantacion.aplica) {
        return [];
    }

    return [
        props.implantacion.responsable_id === null ? { etiqueta: 'Responsable sin asignar', ancla: '#gestion' } : null,
        props.evidencias.length === 0 ? { etiqueta: 'Sin evidencia', ancla: '#evidencias' } : null,
        props.implantacion.nivel_madurez === null ? { etiqueta: 'Madurez sin evaluar', ancla: '#gestion' } : null,
    ].filter((uno): uno is { etiqueta: string; ancla: string } => uno !== null);
});

/* La rama del catálogo sin el propio requisito, que ya es el título. */
const rama = computed(() => props.requisito.ruta.slice(0, -1));
</script>

<template>
    <AppLayout :titulo="requisito.codigo">
        <CabeceraPagina :titulo="requisito.titulo" :codigo="requisito.codigo">
            <nav
                v-if="rama.length > 0"
                aria-label="Rama del catálogo"
                class="mt-2 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[13px] text-muted-foreground"
            >
                <template v-for="(paso, indice) in rama" :key="paso.codigo">
                    <ChevronRightIcon v-if="indice > 0" class="size-3.5 shrink-0" aria-hidden="true" />
                    <span><span class="cifra">{{ paso.codigo }}</span> {{ paso.titulo }}</span>
                </template>
            </nav>

            <p class="mt-1 text-[13px] text-muted-foreground">
                Sobre
                <Link :href="`/sistemas/${sistema.id}/valoracion`" class="cifra text-primary underline-offset-4 hover:underline">
                    {{ sistema.codigo }}</Link>
                {{ sistema.nombre }}
                <template v-if="requisito.marco"> · {{ requisito.marco }}</template>
            </p>

            <template #acciones>
                <Button as-child variant="ghost">
                    <Link href="/implantaciones"><ArrowLeftIcon />Volver a la tabla</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <Aviso v-if="aCargoDeOtro" titulo="A cargo de otra persona">
            La tiene {{ aCargoDeOtro }}. Puedes verla entera; la mueve quien la tiene asignada o el
            responsable de seguridad.
        </Aviso>

        <motion.div :variants="variantesEntrada" initial="oculto" animate="visible" class="space-y-6">
            <!--
                Las cuatro preguntas de una auditoría, a la vista y juntas: si
                está, dónde está la prueba, qué falta y con qué madurez. Estaban
                repartidas en cinco tarjetas, y la fecha del estado, al fondo del
                histórico.
            -->
            <section
                aria-label="Resumen"
                class="grid rounded-xl bg-card ring-1 ring-foreground/10 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div class="flex flex-col gap-2 border-b px-6 py-5 sm:border-r lg:border-b-0">
                    <h2 class="text-[13px] font-medium text-muted-foreground">¿Está implantado?</h2>
                    <CeldaBadge
                        anunciar
                        class="self-start"
                        :valor="{
                            valor: implantacion.estado,
                            etiqueta: implantacion.estadoEtiqueta,
                            tono: implantacion.estado,
                        }"
                    />
                    <p v-if="ultimoCambio?.fecha" class="text-xs text-muted-foreground">
                        Desde el <span class="cifra">{{ fecha(ultimoCambio.fecha) }}</span>,
                        {{ ultimoCambio.usuario ? `por ${ultimoCambio.usuario}` : 'por el recálculo del sistema' }}
                    </p>
                </div>

                <div class="flex flex-col gap-2 border-b px-6 py-5 lg:border-r lg:border-b-0">
                    <h2 class="text-[13px] font-medium text-muted-foreground">¿Dónde está la prueba?</h2>
                    <a href="#evidencias" class="flex items-baseline gap-1.5 hover:underline underline-offset-4">
                        <Cifra :valor="evidencias.length" class="text-2xl leading-7 font-bold" />
                        <span class="text-sm font-medium text-muted-foreground">
                            {{ evidencias.length === 1 ? 'evidencia' : 'evidencias' }}
                        </span>
                    </a>
                    <p v-if="evidenciasCaducadas > 0" class="text-xs font-medium text-destructive">
                        {{ evidenciasCaducadas }} {{ evidenciasCaducadas === 1 ? 'caducada' : 'caducadas' }}
                    </p>
                    <p v-else-if="evidencias.length === 0 && implantacion.estado === 'implantado'" class="text-xs text-muted-foreground">
                        Implantado sin prueba: ante un auditor no se puede demostrar.
                    </p>
                </div>

                <div class="flex flex-col gap-2 border-b px-6 py-5 sm:border-r sm:border-b-0">
                    <h2 class="text-[13px] font-medium text-muted-foreground">¿Qué queda por hacer?</h2>
                    <a href="#tareas" class="flex items-baseline gap-1.5 hover:underline underline-offset-4">
                        <Cifra :valor="tareasAbiertas" class="text-2xl leading-7 font-bold" />
                        <span class="text-sm font-medium text-muted-foreground">
                            {{ tareasAbiertas === 1 ? 'tarea abierta' : 'tareas abiertas' }}
                        </span>
                    </a>
                    <p v-if="tareasVencidas > 0" class="text-xs font-medium text-destructive">
                        {{ tareasVencidas }} {{ tareasVencidas === 1 ? 'vencida' : 'vencidas' }}
                    </p>
                </div>

                <div class="flex flex-col gap-2 px-6 py-5">
                    <h2 class="text-[13px] font-medium text-muted-foreground">¿Con qué madurez?</h2>
                    <span
                        class="mt-2.5 mb-0.5 flex h-2 gap-[3px]"
                        role="img"
                        :aria-label="madurez?.etiqueta ?? 'Sin evaluar'"
                    >
                        <span
                            v-for="(nivel, indice) in niveles.slice(1)"
                            :key="nivel.valor"
                            class="flex-1 rounded-full"
                            :class="madurez !== null && indice <= madurez.paso ? 'bg-primary' : 'bg-muted'"
                        />
                    </span>
                    <p class="text-xs text-muted-foreground">
                        {{ madurez?.etiqueta ?? 'Sin evaluar · escala L0–L5 del CCN' }}
                    </p>
                </div>
            </section>

            <section
                v-if="pendientes.length > 0"
                aria-labelledby="pendientes"
                class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border bg-card px-5 py-3.5"
            >
                <InfoIcon class="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                <h2 id="pendientes" class="text-sm font-semibold">
                    {{ pendientes.length }} {{ pendientes.length === 1 ? 'dato sin completar' : 'datos sin completar' }}
                </h2>
                <ul class="flex flex-1 flex-wrap gap-2">
                    <li v-for="pendiente in pendientes" :key="pendiente.etiqueta">
                        <a
                            :href="pendiente.ancla"
                            class="inline-flex h-7 items-center rounded-full bg-muted px-2.5 text-[13px] font-medium text-secondary-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            {{ pendiente.etiqueta }}
                        </a>
                    </li>
                </ul>
            </section>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start">
                <div class="space-y-6">
                    <Card id="evidencias" class="scroll-mt-24">
                        <CardHeader>
                            <CardTitle class="flex items-baseline gap-2">
                                Evidencias
                                <span class="cifra text-[13px] font-normal text-muted-foreground">{{ evidencias.length }}</span>
                            </CardTitle>
                        </CardHeader>

                        <CardContent>
                            <BloqueEvidencias
                                :implantacion-id="implantacion.id"
                                :evidencias="evidencias"
                                :disponibles="evidenciasDisponibles"
                                :editable="puedeEscribir"
                            />
                        </CardContent>
                    </Card>

                    <!--
                        El plan de acción va aquí, y no sólo en su tabla: es donde
                        alguien se pregunta qué falta para cumplir esto. Lo mismo que
                        las evidencias están donde se pregunta cómo se prueba.
                    -->
                    <Card id="tareas" class="scroll-mt-24">
                        <CardHeader>
                            <CardTitle class="flex items-baseline gap-2">
                                Qué se está haciendo
                                <span class="cifra text-[13px] font-normal text-muted-foreground">{{ tareas.length }}</span>
                            </CardTitle>
                            <CardAction>
                                <Button as-child variant="outline" size="sm">
                                    <Link :href="`/tareas/crear?implantacion=${implantacion.id}`"><PlusIcon />Nueva tarea</Link>
                                </Button>
                            </CardAction>
                        </CardHeader>

                        <CardContent>
                            <p v-if="tareas.length === 0" class="text-sm text-muted-foreground">
                                No hay ninguna tarea sobre este requisito.
                            </p>

                            <ul v-else class="divide-y divide-border">
                                <li v-for="tarea in tareas" :key="tarea.id" class="py-3 first:pt-0 last:pb-0">
                                    <Link
                                        :href="`/tareas/${tarea.id}`"
                                        class="group grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1.5 sm:grid-cols-[minmax(0,1fr)_auto_auto]"
                                    >
                                        <span class="min-w-0">
                                            <span class="block text-sm font-medium group-hover:underline underline-offset-4">
                                                {{ tarea.titulo }}
                                            </span>
                                            <span class="block text-[13px] text-muted-foreground">
                                                {{ tarea.responsable ?? 'Sin responsable' }} · prioridad {{ tarea.prioridad.toLowerCase() }}
                                            </span>
                                        </span>
                                        <CeldaBadge
                                            :valor="{
                                                valor: tarea.estado,
                                                etiqueta: tarea.estadoEtiqueta,
                                                tono: tarea.tono,
                                            }"
                                        />
                                        <span
                                            v-if="tarea.fecha_limite"
                                            class="text-[13px] whitespace-nowrap sm:text-right"
                                            :class="tarea.haVencido ? 'font-medium text-destructive' : 'text-muted-foreground'"
                                        >
                                            {{ tarea.haVencido ? 'venció el' : 'para el' }}
                                            <span class="cifra">{{ fecha(tarea.fecha_limite) }}</span>
                                        </span>
                                    </Link>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>

                    <Card id="gestion" class="scroll-mt-24">
                        <CardHeader>
                            <CardTitle>Cómo se cumple</CardTitle>
                        </CardHeader>

                        <CardContent class="space-y-5">
                            <div v-if="exigencia.excluibleAMano" class="space-y-3">
                                <CampoSwitch
                                    :deshabilitado="!puedeEscribir"
                                    v-model="gestion.aplica"
                                    nombre="aplica"
                                    etiqueta="Este requisito aplica al sistema"
                                    :error="gestion.errors.aplica"
                                    ayuda="La Declaración de Aplicabilidad es precisamente la lista de exclusiones con su motivo."
                                />

                                <CampoTextarea
                                    :deshabilitado="!puedeEscribir"
                                    v-if="!gestion.aplica"
                                    v-model="gestion.justificacion"
                                    nombre="justificacion"
                                    etiqueta="Justificación de la exclusión"
                                    :filas="3"
                                    :error="gestion.errors.justificacion"
                                    requerido
                                    ayuda="Es lo primero que un auditor pide cuando ve una exclusión."
                                />
                            </div>

                            <MensajeError v-else :mensaje="gestion.errors.aplica" />

                            <FilaCampos>
                                <CampoSelect
                                    :deshabilitado="!puedeEscribir"
                                    v-model="gestion.responsable_id"
                                    nombre="responsable_id"
                                    etiqueta="Responsable"
                                    :opciones="responsables"
                                    :error="gestion.errors.responsable_id"
                                />

                                <CampoTexto
                                    :deshabilitado="!puedeEscribir"
                                    v-model="gestion.fecha_objetivo"
                                    nombre="fecha_objetivo"
                                    etiqueta="Fecha objetivo"
                                    tipo="date"
                                    :error="gestion.errors.fecha_objetivo"
                                />
                            </FilaCampos>

                            <CampoOpciones
                                :deshabilitado="!puedeEscribir"
                                v-model="gestion.nivel_madurez"
                                nombre="nivel_madurez"
                                etiqueta="Nivel de madurez"
                                :opciones="niveles"
                                :error="gestion.errors.nivel_madurez"
                                :ayuda="ayudaMadurez"
                            />

                            <CampoTextarea
                                :deshabilitado="!puedeEscribir"
                                v-model="gestion.notas"
                                nombre="notas"
                                etiqueta="Notas"
                                :filas="4"
                                :error="gestion.errors.notas"
                                ayuda="Cómo se cumple: configuración, procedimiento aplicable, salvedades."
                            />
                        </CardContent>

                        <CardFooter v-if="puedeEscribir" class="justify-end">
                            <Button variant="outline" :disabled="gestion.processing" @click="guardar">
                                {{ gestion.processing ? 'Guardando…' : 'Guardar cambios' }}
                            </Button>
                        </CardFooter>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Histórico</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <HistoricoTransiciones :transiciones="historico" />
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Estado</CardTitle>
                        </CardHeader>

                        <CardContent class="space-y-4">
                            <template v-if="transicionesPermitidas.length > 0">
                                <!--
                                    Cada destino se pinta como el estado al que lleva
                                    (DESIGN.md §3): se elige el que se parece al badge
                                    que se quiere. Es elegir y no actuar, porque la
                                    nota va con el cambio al histórico; por eso es un
                                    grupo de radio y no `BotonEstado`.
                                -->
                                <CampoBase
                                    nombre="estado"
                                    etiqueta="Pasar a"
                                    :error="transicion.errors.estado"
                                    #default="{ atributos }"
                                >
                                    <RadioGroupRoot
                                        v-model="transicion.estado"
                                        :disabled="!puedeEscribir"
                                        class="flex flex-col gap-2"
                                        v-bind="atributos"
                                    >
                                        <RadioGroupItem
                                            v-for="destino in transicionesPermitidas"
                                            :key="destino.valor"
                                            :value="destino.valor"
                                            class="flex h-9 cursor-pointer items-center gap-2 rounded-md px-3 text-sm font-medium ring-offset-2 ring-offset-card transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50 data-[state=checked]:ring-2 data-[state=checked]:ring-current"
                                            :class="tono(destino.tono).badge"
                                        >
                                            <IconoTipo :nombre="destino.icono" clase="size-4" />
                                            {{ destino.etiqueta }}
                                        </RadioGroupItem>
                                    </RadioGroupRoot>
                                </CampoBase>

                                <CampoTextarea
                                    :deshabilitado="!puedeEscribir"
                                    v-model="transicion.nota"
                                    nombre="nota"
                                    etiqueta="Nota"
                                    :filas="2"
                                    :error="transicion.errors.nota"
                                    ayuda="Qué ha cambiado. Se guarda en el histórico con tu nombre y la fecha."
                                />

                                <Button
                                    v-if="puedeEscribir"
                                    class="w-full"
                                    :disabled="transicion.processing || !transicion.estado"
                                    @click="cambiarEstado"
                                >
                                    {{ transicion.processing ? 'Guardando…' : 'Cambiar estado' }}
                                </Button>

                                <p class="text-xs text-muted-foreground">
                                    «No aplica» no se elige aquí: lo deriva el motor, o lo pone una exclusión motivada.
                                </p>
                            </template>

                            <p v-else class="text-sm text-muted-foreground">
                                Esta medida no se le exige al sistema. Vuelve a exigirse recalculando tras cambiar la
                                valoración de sus dimensiones, o volviendo a incluirla en «Cómo se cumple».
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Ficha</CardTitle>
                        </CardHeader>

                        <CardContent class="space-y-4 text-sm">
                            <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2.5">
                                <template v-if="requisito.marco">
                                    <dt class="text-muted-foreground">Marco</dt>
                                    <dd class="text-right">{{ requisito.marco }}</dd>
                                </template>

                                <dt class="text-muted-foreground">Sistema</dt>
                                <dd class="text-right">
                                    <Link :href="`/sistemas/${sistema.id}/valoracion`" class="cifra text-primary underline-offset-4 hover:underline">
                                        {{ sistema.codigo }}
                                    </Link>
                                </dd>

                                <template v-if="sistema.categoria">
                                    <dt class="text-muted-foreground">Categoría</dt>
                                    <dd class="text-right">{{ sistema.categoria }}</dd>
                                </template>

                                <template v-if="exigencia.etiqueta">
                                    <dt class="text-muted-foreground">Exigencia</dt>
                                    <dd class="text-right">{{ exigencia.etiqueta }}</dd>
                                </template>

                                <template v-if="exigencia.dimension">
                                    <dt class="text-muted-foreground">Dimensión</dt>
                                    <dd class="text-right">{{ exigencia.dimension }}</dd>
                                </template>
                            </dl>

                            <!--
                                Lo que viene del motor se cita con la regla de 2 px
                                (DESIGN.md §9), no con una caja dentro de la tarjeta.
                            -->
                            <div v-if="!exigencia.excluibleAMano" class="border-l-2 border-border pl-3">
                                <p class="font-medium">Se deriva, no se marca</p>
                                <p class="mt-1 text-muted-foreground">
                                    <template v-if="exigencia.origen">{{ exigencia.origen }}. </template>
                                    Deja de exigirse cambiando la valoración de las dimensiones del sistema: el
                                    siguiente recálculo devolvería una exclusión hecha aquí.
                                </p>
                                <Link
                                    :href="`/sistemas/${sistema.id}/valoracion`"
                                    class="mt-1.5 inline-block text-primary underline-offset-4 hover:underline"
                                >
                                    Ir a la valoración de {{ sistema.codigo }}
                                </Link>
                            </div>

                            <p v-else-if="exigencia.origen" class="flex items-baseline justify-between gap-3">
                                <span class="text-muted-foreground">Origen</span>
                                <span class="text-right">{{ exigencia.origen }}</span>
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-baseline gap-2">
                                Cuenta también en
                                <span class="cifra text-[13px] font-normal text-muted-foreground">{{ correspondencias.length }}</span>
                            </CardTitle>
                        </CardHeader>

                        <CardContent>
                            <MapeoCruzado :correspondencias="correspondencias" />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </motion.div>
    </AppLayout>
</template>
