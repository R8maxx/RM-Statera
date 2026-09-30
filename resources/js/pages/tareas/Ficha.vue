<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import AvatarUsuario from '@/components/AvatarUsuario.vue';
import HistoricoTransiciones, { type Transicion } from '@/components/HistoricoTransiciones.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import BotonEstado from '@/components/BotonEstado.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import ListaComprobacion, { type Paso } from '@/components/tarea/ListaComprobacion.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatoFecha, formatoFechaLarga } from '@/lib/celdas';
import { Link, router } from '@inertiajs/vue3';
import { ChevronRightIcon, ClockIcon, ListTodoIcon, PencilIcon, UserIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, ref } from 'vue';

interface ValorEstado {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
}

interface Vinculo {
    implantacionId: number;
    codigo: string;
    titulo: string;
    marco: string | null;
    sistema: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
}

/** Un registro del que la tarea es el trabajo: lo arma `Domain\Tarea\Procedencia`. */
interface Procedencia {
    tipo: string;
    tipoEtiqueta: string;
    id: number;
    codigo: string;
    titulo: string;
    estado: ValorEstado;
    contexto: string | null;
    fecha: string | null;
    detalles: { etiqueta: string; texto: string }[];
    /** Nulo cuando quien mira no puede abrirlo: mejor sin enlace que un 403. */
    href: string | null;
}

interface OrigenCerrado {
    id: number;
    codigo: string;
    estado: string;
    fecha: string | null;
}

interface Destino extends ValorEstado {
    pista: string;
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
        estadoIcono: string;
        prioridad: string;
        prioridadEtiqueta: string;
        prioridadPeso: number;
        responsable: string | null;
        fecha_limite: string | null;
        fecha_cierre: string | null;
        haVencido: boolean;
        diasHastaElPlazo: number | null;
        creada: string | null;
        coste_estimado: string | null;
        coste: string | null;
        notas: string | null;
    };
    vinculos: Vinculo[];
    procedencias: Procedencia[];
    origenCerrado: OrigenCerrado[];
    historico: Transicion[];
    transiciones: Destino[];
    subtareas: Subtarea[];
    maximoSubtareas: number;
    puedeGestionar: boolean;
    aCargoDeOtro: string | null;
}>();

/**
 * Lo que manda el servidor de tareas, que habla en femenino.
 *
 * La lista de comprobación es compartida y habla `hecho`; la traducción vive
 * aquí y no en el componente, porque dos vocabularios dentro de la pieza
 * compartida es el problema del que se venía. El día que un tercer módulo la
 * use, se unifica la palabra del cable y esto desaparece.
 */
interface Subtarea {
    id: number | null;
    titulo: string;
    hecha: boolean;
    hechaEn?: string | null;
}

const { variantesEntrada } = useMovimientoReducido();

const pasos = computed<Paso[]>(() =>
    props.subtareas.map((subtarea) => ({
        id: subtarea.id,
        titulo: subtarea.titulo,
        hecho: subtarea.hecha,
        hechoEn: subtarea.hechaEn,
    })),
);

const guardandoPasos = ref(false);

function guardarSubtareas(pasos: Paso[]): void {
    guardandoPasos.value = true;

    router.put(
        `/tareas/${props.tarea.id}/subtareas`,
        {
            pasos: pasos.map((paso) => ({
                id: paso.id,
                titulo: paso.titulo,
                hecha: paso.hecho,
            })),
        },
        { preserveScroll: true, onFinish: () => (guardandoPasos.value = false) },
    );
}

/*
 * Las fechas llegan como `YYYY-MM-DD`. Leídas con `new Date()` a secas son
 * medianoche UTC, que en un navegador al oeste de Greenwich es el día anterior.
 */
const diaDe = (valor: string): Date => new Date(`${valor}T00:00:00`);
const fecha = (valor: string | null): string => (valor ? formatoFecha.format(diaDe(valor)) : '—');
const fechaLarga = (valor: string): string => formatoFechaLarga.format(diaDe(valor));
const dias = (n: number): string => (n === 1 ? '1 día' : `${n} días`);

const estadoActual = computed<ValorEstado>(() => ({
    valor: props.tarea.estado,
    etiqueta: props.tarea.estadoEtiqueta,
    tono: props.tarea.estadoTono,
    icono: props.tarea.estadoIcono,
}));

/**
 * El plazo en una frase, con el vencimiento a la vista (DESIGN.md § 13).
 *
 * El rojo sólo cuando ya ha vencido: es de lo que va mal y de nada más.
 */
const plazo = computed<{ texto: string; vencida: boolean }>(() => {
    const { fecha_cierre, fecha_limite, diasHastaElPlazo } = props.tarea;

    if (fecha_cierre) {
        return { texto: `Cerrada el ${fechaLarga(fecha_cierre)}`, vencida: false };
    }

    if (!fecha_limite || diasHastaElPlazo === null) {
        return { texto: 'Sin plazo', vencida: false };
    }

    if (diasHastaElPlazo < 0) {
        return {
            texto: `Venció el ${fechaLarga(fecha_limite)} (hace ${dias(-diasHastaElPlazo)})`,
            vencida: true,
        };
    }

    if (diasHastaElPlazo === 0) {
        return { texto: 'Vence hoy', vencida: false };
    }

    return { texto: `Vence el ${fechaLarga(fecha_limite)} (en ${dias(diasHastaElPlazo)})`, vencida: false };
});

const enlaceDe = (id: number): string | null => props.procedencias.find((p) => p.id === id)?.href ?? null;

/*
 * Descartar va aparte y exige motivo; el resto no. Pedirlo siempre convertiría
 * en un trámite el único sitio donde se dice por qué no se va a hacer algo.
 */
const destinos = computed(() => props.transiciones.filter((paso) => paso.valor !== 'descartada'));
const descarte = computed(() => props.transiciones.find((paso) => paso.valor === 'descartada') ?? null);

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
            <!-- Cómo va, sin bajar a la columna lateral: estado, quién y para cuándo. -->
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
                <CeldaBadge anunciar :valor="estadoActual" />
                <span class="inline-flex items-center gap-1.5">
                    <UserIcon class="size-4" aria-hidden="true" />
                    {{ tarea.responsable ?? 'Sin responsable' }}
                </span>
                <span
                    class="inline-flex items-center gap-1.5"
                    :class="plazo.vencida && 'font-medium text-destructive'"
                >
                    <ClockIcon class="size-4" aria-hidden="true" />
                    {{ plazo.texto }}
                </span>
            </div>

            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/tareas/${tarea.id}/editar`">
                        <PencilIcon aria-hidden="true" />
                        Editar
                    </Link>
                </Button>
            </template>
        </CabeceraPagina>

        <Aviso v-if="aCargoDeOtro" titulo="A cargo de otra persona">
            La tiene {{ aCargoDeOtro }}. Puedes verla entera; la mueve quien la tiene asignada o el
            responsable de seguridad.
        </Aviso>

        <!--
            Lo que un auditor encuentra al cruzar las dos listas: la no
            conformidad está cerrada y su acción correctiva no. Neutro y no
            rojo: puede que sobre, no que vaya mal.
        -->
        <Aviso
            v-if="origenCerrado.length > 0"
            :titulo="
                origenCerrado.length === 1
                    ? 'La no conformidad de la que sale ya no está abierta'
                    : 'Las no conformidades de las que sale ya no están abiertas'
            "
        >
            <p class="max-w-3xl text-pretty">
                <template v-for="(nc, indice) in origenCerrado" :key="nc.id">
                    <template v-if="indice > 0">{{ indice === origenCerrado.length - 1 ? ' y ' : ', ' }}</template>
                    <Link
                        v-if="enlaceDe(nc.id)"
                        :href="enlaceDe(nc.id)!"
                        class="cifra text-primary underline-offset-4 hover:underline"
                    >
                        {{ nc.codigo }}
                    </Link>
                    <span v-else class="cifra">{{ nc.codigo }}</span>
                    está en «{{ nc.estado }}»<template v-if="nc.fecha"> desde el {{ fechaLarga(nc.fecha) }}</template>
                </template>
                y esta acción correctiva sigue {{ tarea.estadoEtiqueta.toLowerCase() }}. Si se hizo por otra vía,
                márcala como hecha; si ya no hace falta, descártala y deja escrito por qué.
            </p>
        </Aviso>

        <motion.div
            :variants="variantesEntrada"
            initial="oculto"
            animate="visible"
            class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
        >
            <div class="space-y-6">
                <!--
                    Por qué existe va antes que lo que hace avanzar: sin el
                    porqué, una acción correctiva es una línea más del plan.
                -->
                <Card v-if="procedencias.length > 0">
                    <CardHeader>
                        <CardTitle>Por qué existe</CardTitle>
                        <CardDescription>El registro del que esta tarea es el trabajo.</CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-6">
                        <!-- Se cita con la regla de 2 px, no con una caja dentro de la tarjeta (DESIGN.md § 9). -->
                        <div
                            v-for="procedencia in procedencias"
                            :key="`${procedencia.tipo}-${procedencia.id}`"
                            class="space-y-2.5 border-l-2 border-border py-0.5 pl-4"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <component
                                    :is="procedencia.href ? Link : 'span'"
                                    :href="procedencia.href ?? undefined"
                                    class="cifra rounded-full bg-muted px-2 py-0.5 text-[13px] font-medium text-foreground"
                                    :class="procedencia.href && 'underline-offset-4 hover:underline'"
                                >
                                    {{ procedencia.codigo }}
                                </component>
                                <CeldaBadge :valor="procedencia.estado" />
                                <span class="text-xs text-muted-foreground">
                                    {{ procedencia.tipoEtiqueta
                                    }}<template v-if="procedencia.contexto"> · {{ procedencia.contexto }}</template
                                    ><template v-if="procedencia.fecha"> · {{ fecha(procedencia.fecha) }}</template>
                                </span>
                            </div>

                            <p class="text-sm font-medium text-pretty">{{ procedencia.titulo }}</p>

                            <dl
                                v-if="procedencia.detalles.length > 0"
                                class="grid gap-x-4 gap-y-1.5 text-sm sm:grid-cols-[7rem_minmax(0,1fr)]"
                            >
                                <template v-for="detalle in procedencia.detalles" :key="detalle.etiqueta">
                                    <dt class="text-muted-foreground">{{ detalle.etiqueta }}</dt>
                                    <dd class="text-pretty text-secondary-foreground max-sm:mb-1.5">{{ detalle.texto }}</dd>
                                </template>
                            </dl>
                        </div>
                    </CardContent>
                </Card>

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

                        <ul v-else class="-mx-3 divide-y divide-border">
                            <li v-for="vinculo in vinculos" :key="vinculo.implantacionId">
                                <Link
                                    :href="`/implantaciones/${vinculo.implantacionId}`"
                                    class="group flex items-start gap-4 rounded-md px-3 py-3 transition-colors hover:bg-muted/60"
                                >
                                    <div class="min-w-0 flex-1 space-y-1.5">
                                        <div class="flex flex-wrap items-center gap-2">
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
                                                    tono: vinculo.estadoTono,
                                                    icono: vinculo.estadoIcono,
                                                }"
                                            />
                                        </div>
                                        <p class="text-sm text-secondary-foreground">{{ vinculo.titulo }}</p>
                                        <p class="cifra text-xs text-muted-foreground">{{ vinculo.sistema }}</p>
                                    </div>
                                    <ChevronRightIcon class="mt-1 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                </Link>
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
                            Para trocear la tarea sin inflar el plan. Los pasos no tienen responsable ni plazo y no
                            cuentan en el panel.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <ListaComprobacion
                            :pasos="pasos"
                            :maximo="maximoSubtareas"
                            :editable="puedeGestionar"
                            :ocupado="guardandoPasos"
                            vacio="Sin pasos todavía."
                            cierre="Ciérrala desde «Estado» cuando toque."
                            @guardar="guardarSubtareas"
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
                        <HistoricoTransiciones :transiciones="historico" />
                    </CardContent>
                </Card>
            </div>

            <!-- En móvil la columna lateral sube: cambiar el estado es a lo que se entra. -->
            <div class="space-y-6 max-lg:order-first">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between gap-3">
                        <CardTitle>Estado</CardTitle>
                        <CeldaBadge anunciar :valor="estadoActual" />
                    </CardHeader>

                    <CardContent v-if="puedeGestionar" class="space-y-4">
                        <template v-if="!exigeNota">
                            <!--
                                Cada botón se parece al badge que vas a obtener al
                                pulsarlo, y dice en tres palabras qué significa.
                            -->
                            <div class="space-y-2">
                                <p class="text-[13px] font-medium text-muted-foreground">Pasar a</p>
                                <BotonEstado
                                    v-for="paso in destinos"
                                    :key="paso.valor"
                                    :destino="paso"
                                    :deshabilitado="enviando"
                                    class="h-11 w-full justify-start px-3"
                                    @click="mover(paso.valor)"
                                />
                            </div>

                            <div v-if="descarte" class="flex items-center justify-between gap-3 border-t pt-3">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-estado-no-aplica"
                                    :disabled="enviando"
                                    @click="mover('descartada')"
                                >
                                    <IconoTipo :nombre="descarte.icono" clase="size-4" />
                                    Descartar…
                                </Button>
                                <span class="text-xs text-muted-foreground">{{ descarte.pista }}</span>
                            </div>
                        </template>

                        <div v-else class="space-y-2">
                            <label for="nota-descarte" class="text-[13px] font-medium">Por qué se descarta</label>
                            <Textarea
                                id="nota-descarte"
                                v-model="nota"
                                rows="4"
                                aria-describedby="nota-descarte-ayuda"
                                placeholder="El servicio se retira en octubre y la medida deja de aplicar."
                            />
                            <p id="nota-descarte-ayuda" class="text-xs text-muted-foreground">
                                Queda en el histórico. Un auditor puede preguntar qué se hizo con este requisito, y
                                sin motivo no hay rastro.
                            </p>
                            <div class="flex gap-2 pt-1">
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    :disabled="enviando || nota.trim() === ''"
                                    @click="mover('descartada')"
                                >
                                    Descartar tarea
                                </Button>
                                <Button size="sm" variant="ghost" @click="destino = null">Cancelar</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>

                    <CardContent>
                        <dl class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-3.5 text-sm">
                            <dt class="text-muted-foreground">Origen</dt>
                            <dd class="space-y-0.5">
                                <p>{{ tarea.origenEtiqueta }}</p>
                                <template v-for="procedencia in procedencias" :key="`${procedencia.tipo}-${procedencia.id}`">
                                    <Link
                                        v-if="procedencia.href"
                                        :href="procedencia.href"
                                        class="cifra block text-[13px] text-primary underline-offset-4 hover:underline"
                                    >
                                        {{ procedencia.codigo }}
                                    </Link>
                                    <p v-else class="cifra text-[13px]">{{ procedencia.codigo }}</p>
                                </template>
                            </dd>

                            <!-- La prioridad ordena: la escala de pasos lo dice antes que la palabra (DESIGN.md § 9). -->
                            <dt class="text-muted-foreground">Prioridad</dt>
                            <dd class="flex items-center gap-2.5">
                                <span class="inline-flex gap-0.5" aria-hidden="true">
                                    <span
                                        v-for="paso in 4"
                                        :key="paso"
                                        class="h-1.5 w-2.5 rounded-sm"
                                        :class="paso <= tarea.prioridadPeso ? 'bg-primary' : 'bg-border'"
                                    />
                                </span>
                                <span>
                                    {{ tarea.prioridadEtiqueta }}
                                    <span class="text-muted-foreground">· {{ tarea.prioridadPeso }} de 4</span>
                                </span>
                            </dd>

                            <dt class="text-muted-foreground">Responsable</dt>
                            <dd class="flex min-w-0 items-center gap-2">
                                <template v-if="tarea.responsable">
                                    <AvatarUsuario :nombre="tarea.responsable" clase="size-6 text-xs" />
                                    <span class="truncate">{{ tarea.responsable }}</span>
                                </template>
                                <span v-else class="text-muted-foreground">Sin asignar</span>
                            </dd>

                            <dt class="text-muted-foreground">Fecha límite</dt>
                            <dd class="space-y-0.5">
                                <p class="cifra text-[13px]">{{ fecha(tarea.fecha_limite) }}</p>
                                <p
                                    v-if="tarea.haVencido && tarea.diasHastaElPlazo !== null"
                                    class="text-xs font-medium text-destructive"
                                >
                                    Vencida hace {{ dias(-tarea.diasHastaElPlazo) }}
                                </p>
                            </dd>

                            <template v-if="tarea.fecha_cierre">
                                <dt class="text-muted-foreground">Cerrada</dt>
                                <dd class="cifra text-[13px]">{{ fecha(tarea.fecha_cierre) }}</dd>
                            </template>

                            <template v-if="tarea.coste">
                                <dt class="text-muted-foreground">Coste estimado</dt>
                                <dd class="cifra text-[13px]">{{ tarea.coste }}</dd>
                            </template>

                            <template v-if="tarea.creada">
                                <dt class="text-muted-foreground">Creada</dt>
                                <dd class="cifra text-[13px]">{{ fecha(tarea.creada) }}</dd>
                            </template>
                        </dl>
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
