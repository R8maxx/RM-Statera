<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import BloqueAdjuntos, { type Adjunto as Documento } from '@/components/adjunto/BloqueAdjuntos.vue';
import BarraSegmentada from '@/components/BarraSegmentada.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import GraficaBarras from '@/components/grafica/GraficaBarras.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import {
    CalendarIcon,
    CalendarPlusIcon,
    CheckCircle2Icon,
    ChevronRightIcon,
    ClockIcon,
    FileXIcon,
    SearchIcon,
    TriangleAlertIcon,
    UserXIcon,
    UsersIcon,
} from '@lucide/vue';
import { type Component, computed, ref, watch } from 'vue';

interface Accion {
    id: number;
    codigo: string;
    titulo: string;
    tipo: string;
    tipoEtiqueta: string;
    tipoTono: string;
    tipoIcono: string;
    medida: string;
    fechaEtiqueta: string;
    fechaLarga: string;
    fechaRelativa: string;
    vigenteHasta: string;
    vigenteHastaLarga: string;
    vigenteHastaRelativa: string;
    hoy: string;
    duracion_horas: string | null;
    contenido: string | null;
    evidencia_id: number | null;
    evidencia: string | null;
    evidenciaFecha: string | null;
    asistenciaRegistrada: string | null;
}

interface PersonaConvocada {
    id: number;
    codigo: string;
    nombre: string;
    puesto: string | null;
    activa: boolean;
    convocada: boolean;
    asistio: boolean;
    /** Sólo en quien faltó; nula es «sin indicar». */
    ausencia: string | null;
    motivo: string | null;
    /** Hasta cuándo estaba cubierta **sin contar esta sesión**. */
    renovacion_previa: string | null;
    ultima_sesion: string | null;
}

interface Justificacion {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
}

/**
 * La convocatoria de una sesión: quién estaba llamado, quién fue y, de quien
 * faltó, si tenía motivo.
 *
 * **Pantalla propia y marcado en bloque**, por el mismo motivo que la checklist
 * de una auditoría: son veinte o cincuenta marcas y una ruta por persona sería
 * veinte peticiones y veinte oportunidades de dejarlo a medias.
 *
 * **Convocar y asistir son dos cosas distintas.** Quien no está marcado como
 * convocado no lo estuvo; quien lo está con la casilla de asistencia vacía fue
 * convocado y no fue, y ése es el que un auditor pregunta. Sin esa diferencia,
 * «formación impartida al 100 % de los convocados» saldría siempre. La pregunta
 * siguiente es «¿y los que faltaron?», y por eso la ausencia lleva justificación.
 *
 * **Arriba lo registrado, abajo lo que se edita.** La tarjeta principal y el
 * reparto por puesto leen de lo que llegó del servidor, no de las casillas:
 * resumen lo que consta, y así las cifras pueden llegar contando sin ponerse a
 * contar en cada clic.
 */
const props = defineProps<{
    accion: Accion;
    cubre: { marco: string; codigo: string; titulo: string }[];
    personas: PersonaConvocada[];
    justificaciones: Justificacion[];
    adjuntos: Documento[];
    puedeGestionar: boolean;
}>();

const lista = ref<PersonaConvocada[]>([]);
const busqueda = ref('');

watch(
    () => props.personas,
    (valor) => {
        lista.value = valor.map((persona) => ({ ...persona }));
    },
    { immediate: true, deep: true },
);

/** `2027-03-12` → `12/03/2027`, sin pasar por `Date` y su zona horaria. */
function corta(iso: string): string {
    const [anio, mes, dia] = iso.split('-');

    return `${dia}/${mes}/${anio}`;
}

type Vigencia =
    | { tipo: 'vigente'; hasta: string; origen: string }
    | { tipo: 'caducada'; hasta: string; origen: string }
    | { tipo: 'nunca' };

/**
 * Si esta persona está cubierta, con las casillas tal como están.
 *
 * Su vigencia previa llega sin esta sesión dentro; aquí se suma, si asistió. Así
 * la columna sigue a la casilla: desmarcar la asistencia enseña a qué vuelve.
 */
function vigencia(persona: PersonaConvocada): Vigencia {
    const porEsta = persona.convocada && persona.asistio;
    const previa = persona.renovacion_previa;

    if (porEsta && (previa === null || props.accion.vigenteHasta >= previa)) {
        return { tipo: 'vigente', hasta: props.accion.vigenteHasta, origen: 'Por esta sesión' };
    }

    if (previa === null) {
        return { tipo: 'nunca' };
    }

    const origen = persona.ultima_sesion ? `Por ${persona.ultima_sesion}` : 'Por otra sesión';

    return previa >= props.accion.hoy
        ? { tipo: 'vigente', hasta: previa, origen }
        : { tipo: 'caducada', hasta: previa, origen };
}

const faltoLa = (persona: PersonaConvocada): boolean => persona.convocada && !persona.asistio;

/*
 * Los filtros de la lista. Pestañas y no un desplegable porque las cifras son
 * la mitad de su trabajo: «Faltaron 2» se lee sin abrir nada.
 */
type Filtro = 'todas' | 'convocadas' | 'asistieron' | 'faltaron' | 'sin_convocar' | 'sin_cobertura';

const filtros: { clave: Filtro; etiqueta: string; encaja: (persona: PersonaConvocada) => boolean }[] = [
    { clave: 'todas', etiqueta: 'Toda la plantilla', encaja: () => true },
    { clave: 'convocadas', etiqueta: 'Convocadas', encaja: (persona) => persona.convocada },
    { clave: 'asistieron', etiqueta: 'Asistieron', encaja: (persona) => persona.convocada && persona.asistio },
    { clave: 'faltaron', etiqueta: 'Faltaron', encaja: faltoLa },
    { clave: 'sin_convocar', etiqueta: 'Sin convocar', encaja: (persona) => !persona.convocada },
    {
        clave: 'sin_cobertura',
        etiqueta: 'Sin formación vigente',
        encaja: (persona) => persona.activa && vigencia(persona).tipo !== 'vigente',
    },
];

const filtro = ref<Filtro>('todas');

const pestanas = computed(() =>
    filtros.map((opcion) => ({ ...opcion, cuantas: lista.value.filter(opcion.encaja).length })),
);

const visibles = computed(() => {
    const termino = busqueda.value.trim().toLowerCase();
    const encaja = filtros.find((opcion) => opcion.clave === filtro.value)!.encaja;

    return lista.value.filter(encaja).filter(
        (persona) =>
            termino === '' ||
            persona.nombre.toLowerCase().includes(termino) ||
            persona.codigo.toLowerCase().includes(termino) ||
            (persona.puesto ?? '').toLowerCase().includes(termino),
    );
});

const convocadas = computed(() => lista.value.filter((persona) => persona.convocada));

/*
 * Lo registrado: la tarjeta principal y el reparto por puesto. Ver el docblock
 * de arriba —lee de `props`, no de `lista`—.
 */
const registro = computed(() => {
    const activas = props.personas.filter((persona) => persona.activa);
    const conv = props.personas.filter((persona) => persona.convocada);
    const asist = conv.filter((persona) => persona.asistio);
    const faltaron = conv.filter((persona) => !persona.asistio);
    const horas = props.accion.duracion_horas === null ? null : asist.length * Number(props.accion.duracion_horas);

    return {
        activas: activas.length,
        convocadas: conv.length,
        asistieron: asist.length,
        faltaron: faltaron.length,
        justificadas: faltaron.filter((persona) => persona.ausencia === 'justificada').length,
        sinConvocar: activas.filter((persona) => !persona.convocada).length,
        porcentaje: conv.length === 0 ? 0 : Math.round((asist.length / conv.length) * 100),
        horas,
    };
});

const segmentos = computed(() => [
    { clave: 'asistieron', etiqueta: 'Asistieron', valor: registro.value.asistieron, tono: 'implantado' },
    { clave: 'faltaron', etiqueta: 'Convocadas que faltaron', valor: registro.value.faltaron, tono: 'en_progreso' },
    { clave: 'sin_convocar', etiqueta: 'Activas sin convocar', valor: registro.value.sinConvocar, tono: 'no_iniciado' },
]);

/**
 * Asistentes sobre activas de cada puesto. Un puesto entero a cero suele ser un
 * olvido de la convocatoria, y en la lista de personas no se ve.
 */
const porPuesto = computed(() => {
    const grupos = new Map<string, { valor: number; de: number }>();

    for (const persona of props.personas.filter((fila) => fila.activa)) {
        const clave = persona.puesto ?? 'Sin puesto';
        const grupo = grupos.get(clave) ?? { valor: 0, de: 0 };
        grupo.de += 1;
        grupo.valor += persona.convocada && persona.asistio ? 1 : 0;
        grupos.set(clave, grupo);
    }

    return [...grupos.entries()]
        .sort(([a], [b]) => a.localeCompare(b, 'es'))
        .map(([etiqueta, grupo]) => ({ clave: etiqueta, etiqueta, ...grupo }));
});

interface Pendiente {
    clave: string;
    etiqueta: string;
    detalle: string;
    icono: Component;
    filtro?: Filtro;
    href?: string;
}

function nombres(personas: PersonaConvocada[]): string {
    const primeras = personas.slice(0, 3).map((persona) => persona.nombre);

    return personas.length > 3 ? `${primeras.join(', ')} y ${personas.length - 3} más` : primeras.join(', ');
}

/**
 * Lo que pide esta sesión, con cada fila llevando a la lista exacta que cuenta.
 * Lo que está a cero no se pinta, como en `ListaAcciones`.
 */
const pendientes = computed<Pendiente[]>(() => {
    const filas: Pendiente[] = [];
    const registradas = props.personas;

    if (props.accion.evidencia_id === null) {
        filas.push({
            clave: 'sin_prueba',
            etiqueta: 'Sin hoja de firmas: declarada, no demostrada',
            detalle: 'Es lo que un auditor separa. Se enlaza al editar la sesión.',
            icono: FileXIcon,
            href: props.puedeGestionar ? `/formacion/${props.accion.id}/editar` : undefined,
        });
    }

    const descubiertas = registradas.filter((persona) => faltoLa(persona) && vigencia(persona).tipo !== 'vigente');
    if (descubiertas.length > 0) {
        filas.push({
            clave: 'descubiertas',
            etiqueta: `${descubiertas.length} ${descubiertas.length === 1 ? 'faltó y sigue' : 'faltaron y siguen'} sin formación vigente`,
            detalle: `${nombres(descubiertas)}. A convocar en la siguiente.`,
            icono: TriangleAlertIcon,
            filtro: 'faltaron',
        });
    }

    const sinIndicar = registradas.filter((persona) => faltoLa(persona) && persona.ausencia === null);
    if (sinIndicar.length > 0) {
        filas.push({
            clave: 'sin_indicar',
            etiqueta: `${sinIndicar.length} ${sinIndicar.length === 1 ? 'ausencia' : 'ausencias'} sin indicar si estaba justificada`,
            detalle: nombres(sinIndicar),
            icono: UserXIcon,
            filtro: 'faltaron',
        });
    }

    const nunca = registradas.filter((persona) => persona.activa && vigencia(persona).tipo === 'nunca');
    if (nunca.length > 0) {
        filas.push({
            clave: 'nunca',
            etiqueta: `${nunca.length} ${nunca.length === 1 ? 'persona activa nunca ha recibido' : 'personas activas nunca han recibido'} formación`,
            detalle: nombres(nunca),
            icono: TriangleAlertIcon,
            filtro: 'sin_cobertura',
        });
    }

    return filas;
});

function irA(destino: Filtro): void {
    filtro.value = destino;
    busqueda.value = '';
    document.getElementById('convocatoria')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/** Marcar en bloque **lo visible**, que es lo que hace útil el buscador. */
function marcarVisibles(asistio: boolean): void {
    for (const persona of visibles.value) {
        persona.convocada = true;
        persona.asistio = asistio;

        if (asistio) {
            persona.ausencia = null;
            persona.motivo = null;
        }
    }
}

function alternarConvocada(persona: PersonaConvocada, valor: boolean): void {
    persona.convocada = valor;

    if (!valor) {
        persona.asistio = false;
        persona.ausencia = null;
        persona.motivo = null;
    }
}

function alternarAsistio(persona: PersonaConvocada, valor: boolean): void {
    persona.asistio = valor;

    // Quien asistió no lleva justificación: la base lo rechazaría, y con razón.
    if (valor) {
        persona.ausencia = null;
        persona.motivo = null;
    }
}

function elegirAusencia(persona: PersonaConvocada, valor: string | null): void {
    persona.ausencia = valor;

    if (valor !== 'justificada') {
        persona.motivo = null;
    }
}

/**
 * Lo marcado y todavía no mandado.
 *
 * Toda la convocatoria se edita en local y viaja de una vez —cincuenta marcas
 * en una petición, que es el motivo de que esto sea pantalla propia—, así que
 * entre la primera casilla y el botón hay un rato en el que salir de aquí lo
 * tira. Decirlo es lo mínimo.
 */
function huella(personas: PersonaConvocada[]): string {
    return JSON.stringify(
        personas
            .filter((persona) => persona.convocada)
            .map((persona) => [persona.id, persona.asistio, persona.ausencia, (persona.motivo ?? '').trim()])
            .sort(),
    );
}

const sinGuardar = computed(() => huella(lista.value) !== huella(props.personas));

const guardando = ref(false);

/** Ver el mismo comentario en la ficha de una persona. */
const error = ref<string | null>(null);

/** Qué persona tiene el motivo en error, para marcar su campo. */
const conError = ref<Set<number>>(new Set());

function guardar(): void {
    guardando.value = true;
    error.value = null;
    conError.value = new Set();

    const enviadas = convocadas.value;

    router.put(
        `/formacion/${props.accion.id}/asistencia`,
        {
            convocadas: enviadas.map((persona) => ({
                persona_id: persona.id,
                asistio: persona.asistio,
                ausencia: persona.asistio ? null : persona.ausencia,
                motivo: persona.asistio ? null : persona.motivo,
            })),
        },
        {
            preserveScroll: true,
            onError: (errores) => {
                // `convocadas.3.motivo` → la cuarta enviada.
                for (const clave of Object.keys(errores)) {
                    const indice = /^convocadas\.(\d+)\./.exec(clave)?.[1];
                    const persona = indice === undefined ? undefined : enviadas[Number(indice)];

                    if (persona) {
                        conError.value.add(persona.id);
                    }
                }

                error.value =
                    Object.values(errores)[0] ??
                    'No se ha podido guardar la asistencia. Inténtalo otra vez.';
            },
            onFinish: () => (guardando.value = false),
        },
    );
}

const tonoVigencia: Record<Vigencia['tipo'], { etiqueta: (v: Vigencia) => string; tono: string; icono: string }> = {
    vigente: { etiqueta: (v) => (v.tipo === 'nunca' ? '' : `Hasta ${corta(v.hasta)}`), tono: 'implantado', icono: 'CircleCheck' },
    caducada: { etiqueta: (v) => (v.tipo === 'nunca' ? '' : `Caducó ${corta(v.hasta)}`), tono: 'en_progreso', icono: 'TriangleAlert' },
    nunca: { etiqueta: () => 'Nunca', tono: 'no_iniciado', icono: 'CircleDotDashed' },
};

function badgeVigencia(persona: PersonaConvocada) {
    const v = vigencia(persona);
    const estilo = tonoVigencia[v.tipo];

    return {
        valor: { valor: v.tipo, etiqueta: estilo.etiqueta(v), tono: estilo.tono, icono: estilo.icono },
        origen: v.tipo === 'nunca' ? 'Sin ninguna asistencia registrada' : v.origen,
    };
}
</script>

<template>
    <AppLayout :titulo="accion.codigo">
        <CabeceraPagina :titulo="accion.titulo" :codigo="accion.codigo">
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
                <CeldaBadge
                    :valor="{
                        valor: accion.tipo,
                        etiqueta: accion.tipoEtiqueta,
                        tono: accion.tipoTono,
                        icono: accion.tipoIcono,
                    }"
                />
                <span class="inline-flex items-center gap-1.5">
                    <CalendarIcon class="size-3.5" aria-hidden="true" />
                    {{ accion.fechaLarga }} · {{ accion.fechaRelativa }}
                </span>
                <span v-if="accion.duracion_horas" class="inline-flex items-center gap-1.5">
                    <ClockIcon class="size-3.5" aria-hidden="true" />
                    <span class="cifra">{{ accion.duracion_horas }} h</span>
                </span>
            </div>

            <template #acciones>
                <!--
                    La concienciación es anual y se repite casi tal cual: la
                    siguiente sale con lo de ésta y la fecha en que vence.
                -->
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/formacion/crear?desde=${accion.id}`">
                        <CalendarPlusIcon aria-hidden="true" />
                        Programar la siguiente
                    </Link>
                </Button>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/formacion/${accion.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <!--
            La tarjeta principal partida en dos, como el panel: la cifra que
            contesta la pregunta de la vista a la izquierda y lo que pide acción
            a la derecha.
        -->
        <Card>
            <CardContent class="grid gap-8 lg:grid-cols-2 lg:gap-0 lg:divide-x lg:divide-border">
                <section aria-labelledby="t-asistencia" class="flex flex-col gap-4 lg:pr-8">
                    <h2 id="t-asistencia" class="text-base font-semibold tracking-[-0.01em]">Asistencia</h2>

                    <EstadoVacio
                        v-if="registro.convocadas === 0"
                        :icono="UsersIcon"
                        titulo="Todavía no hay nadie convocado"
                        :descripcion="`La sesión consta, pero sin lista no prueba a quién se formó: ${accion.medida} se demuestra con los asistentes.`"
                    />

                    <template v-else>
                        <!-- Ningún porcentaje sin su denominador (DESIGN.md § 9). -->
                        <p class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <Cifra
                                class="cifra text-4xl font-semibold tracking-[-0.02em]"
                                :valor="registro.porcentaje"
                                :sufijo="' %'"
                            />
                            <span class="text-sm text-secondary-foreground">
                                <span class="cifra font-semibold">{{ registro.asistieron }}</span>
                                de <span class="cifra">{{ registro.convocadas }}</span>
                                {{ registro.convocadas === 1 ? 'convocada asistió' : 'convocadas asistieron' }}
                            </span>
                        </p>

                        <BarraSegmentada :segmentos="segmentos" leyenda />

                        <dl class="grid grid-cols-2 gap-4 border-t pt-4 sm:grid-cols-3">
                            <div>
                                <dd class="cifra text-lg font-medium">
                                    {{ registro.convocadas }} de {{ registro.activas }}
                                </dd>
                                <dt class="text-xs text-muted-foreground">activas convocadas</dt>
                            </div>
                            <div v-if="registro.faltaron > 0">
                                <dd class="cifra text-lg font-medium">
                                    {{ registro.justificadas }} de {{ registro.faltaron }}
                                </dd>
                                <dt class="text-xs text-muted-foreground">ausencias justificadas</dt>
                            </div>
                            <div v-if="registro.horas !== null">
                                <dd class="cifra text-lg font-medium">
                                    {{ registro.horas.toLocaleString('es-ES') }} h
                                </dd>
                                <dt class="text-xs text-muted-foreground">horas·persona impartidas</dt>
                            </div>
                        </dl>
                    </template>
                </section>

                <section aria-labelledby="t-pendiente" class="flex flex-col gap-3 lg:pl-8">
                    <h2 id="t-pendiente" class="text-base font-semibold tracking-[-0.01em]">Qué pide esta sesión</h2>

                    <ul v-if="pendientes.length > 0">
                        <li v-for="pendiente in pendientes" :key="pendiente.clave" class="border-b last:border-b-0">
                            <component
                                :is="pendiente.href ? Link : pendiente.filtro ? 'button' : 'div'"
                                :href="pendiente.href"
                                :type="pendiente.filtro && !pendiente.href ? 'button' : undefined"
                                class="group -mx-2 flex min-h-14 w-[calc(100%+1rem)] items-center gap-3 rounded-md px-2 py-2 text-left transition-colors"
                                :class="(pendiente.href || pendiente.filtro) && 'hover:bg-fila-hover'"
                                @click="pendiente.filtro && !pendiente.href ? irA(pendiente.filtro) : undefined"
                            >
                                <component
                                    :is="pendiente.icono"
                                    class="size-4.5 shrink-0 text-estado-en-progreso"
                                    aria-hidden="true"
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium">{{ pendiente.etiqueta }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ pendiente.detalle }}</span>
                                </span>
                                <ChevronRightIcon
                                    v-if="pendiente.href || pendiente.filtro"
                                    class="size-4 shrink-0 text-muted-foreground/60 transition-transform group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </component>
                        </li>
                    </ul>

                    <p v-else class="flex items-center gap-2 text-sm text-muted-foreground">
                        <CheckCircle2Icon class="size-4 shrink-0 text-estado-implantado" aria-hidden="true" />
                        Nada pendiente: la convocatoria consta y está demostrada.
                    </p>

                    <p v-if="registro.asistieron > 0" class="mt-auto pt-2 text-xs text-muted-foreground">
                        Quien asistió queda cubierto hasta el {{ accion.vigenteHastaLarga }}
                        ({{ accion.vigenteHastaRelativa }}). El vencimiento entra en el calendario persona a
                        persona.
                    </p>
                </section>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="flex min-w-0 flex-col gap-6">
                <Card id="convocatoria" class="scroll-mt-20">
                    <CardHeader>
                        <CardTitle>Convocatoria y asistencia</CardTitle>
                        <CardDescription class="max-w-2xl">
                            Quien sale de la lista deja de estar convocado, que no es lo mismo que haber
                            faltado. De quien faltó se apunta si la ausencia estaba justificada. Las personas
                            dadas de baja sólo aparecen si ya estaban convocadas: asistieron de verdad y
                            borrarlas reescribiría el registro.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Aviso v-if="error" tono="error">{{ error }}</Aviso>

                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative w-full max-w-xs">
                                <SearchIcon
                                    class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    v-model="busqueda"
                                    type="search"
                                    placeholder="Buscar por nombre, código o puesto"
                                    aria-label="Buscar en la plantilla"
                                    class="pl-8"
                                />
                            </div>
                            <!--
                                Con el buscador vacío, «lo visible» es la
                                pestaña entera. El número va en el botón porque
                                marcar a doscientas personas de un clic y darse
                                cuenta después no tiene vuelta atrás más que no
                                guardando.
                            -->
                            <template v-if="puedeGestionar && visibles.length > 0">
                                <Button variant="outline" size="sm" @click="marcarVisibles(true)">
                                    Convocar y dar por asistidas ({{ visibles.length }})
                                </Button>
                                <Button variant="outline" size="sm" @click="marcarVisibles(false)">
                                    Convocar sin asistencia ({{ visibles.length }})
                                </Button>
                            </template>
                        </div>

                        <!--
                            Sin `Cifra` en los recuentos: cambian con cada
                            casilla, y el contador es para lo que resume.
                        -->
                        <div class="-mx-6 flex flex-wrap gap-1 border-b px-6" role="group" aria-label="Filtrar la plantilla">
                            <button
                                v-for="pestana in pestanas"
                                :key="pestana.clave"
                                type="button"
                                :aria-pressed="filtro === pestana.clave"
                                class="inline-flex h-10 items-center gap-1.5 px-2.5 text-sm font-medium transition-colors"
                                :class="
                                    filtro === pestana.clave
                                        ? 'text-foreground shadow-[inset_0_-2px_0_var(--primary)]'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="filtro = pestana.clave"
                            >
                                {{ pestana.etiqueta }}
                                <span
                                    class="cifra rounded-full px-1.5 text-xs"
                                    :class="filtro === pestana.clave ? 'bg-accent text-accent-foreground' : 'bg-muted'"
                                >
                                    {{ pestana.cuantas }}
                                </span>
                            </button>
                        </div>

                        <EstadoVacio
                            v-if="visibles.length === 0 && busqueda.trim() !== ''"
                            :icono="SearchIcon"
                            titulo="Nadie encaja con lo buscado"
                            descripcion="Prueba con parte del nombre, con el código o con el puesto."
                        />
                        <EstadoVacio
                            v-else-if="lista.length === 0"
                            :icono="UsersIcon"
                            titulo="Todavía no hay plantilla que convocar"
                            descripcion="Una sesión sin nadie apuntado no prueba que se impartiera: mp.per.3 y mp.per.4 se demuestran con la lista de asistentes."
                            :accion="{ etiqueta: 'Dar de alta a alguien', href: '/personas/crear' }"
                        />
                        <p v-else-if="visibles.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                            Nadie en esta pestaña.
                        </p>
                        <!--
                            La lista mengua al teclear o al cambiar de pestaña,
                            así que lleva salida: `DESIGN.md` §14, «si puede
                            menguar, lleva salida».
                        -->
                        <TransitionGroup v-else tag="ul" name="paso" class="divide-y divide-border">
                            <li v-for="persona in visibles" :key="persona.id" class="py-2.5 text-sm">
                                <div class="flex flex-wrap items-center gap-3">
                                    <Checkbox
                                        :model-value="persona.convocada"
                                        :disabled="!puedeGestionar"
                                        :aria-label="`Convocar a ${persona.nombre}`"
                                        @update:model-value="(valor) => alternarConvocada(persona, valor === true)"
                                    />
                                    <!--
                                        Nombre y metadatos en un solo bloque
                                        `min-w-0 flex-1`: con el enlace suelto
                                        en `flex-1` el código se iba al otro
                                        extremo de la fila, y con un ancho
                                        mínimo fijo la fila desbordaba a 375 px.
                                    -->
                                    <div class="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-2">
                                        <Link :href="`/personas/${persona.id}`" class="underline underline-offset-4">
                                            {{ persona.nombre }}
                                        </Link>
                                        <span class="text-xs text-muted-foreground">
                                            <span class="cifra">{{ persona.codigo }}</span>
                                            <template v-if="persona.puesto"> · {{ persona.puesto }}</template>
                                            <template v-if="!persona.activa"> · dada de baja</template>
                                        </span>
                                    </div>

                                    <!-- Si está cubierta pase lo que pase aquí. -->
                                    <div class="flex shrink-0 flex-col items-start gap-0.5 sm:w-44">
                                        <CeldaBadge :valor="badgeVigencia(persona).valor" />
                                        <span class="text-xs text-muted-foreground">{{ badgeVigencia(persona).origen }}</span>
                                    </div>

                                    <!--
                                        `ml-auto` no: al envolver a 375 px
                                        dejaba esta casilla sola en su línea y
                                        pegada al borde derecho.
                                    -->
                                    <label class="flex shrink-0 items-center gap-2">
                                        <Checkbox
                                            :model-value="persona.asistio"
                                            :disabled="!puedeGestionar || !persona.convocada"
                                            :aria-label="`${persona.nombre} asistió`"
                                            @update:model-value="(valor) => alternarAsistio(persona, valor === true)"
                                        />
                                        <span class="text-xs text-muted-foreground">Asistió</span>
                                    </label>
                                </div>

                                <!--
                                    Por qué faltó. Tres opciones y no dos:
                                    «sin indicar» es que nadie lo ha dicho
                                    todavía, y pintarlo como injustificada
                                    afirmaría algo que no consta. Radios de
                                    verdad, así que las flechas mueven entre
                                    ellas sin código.
                                -->
                                <div
                                    v-if="faltoLa(persona)"
                                    class="mt-2 ml-7 flex flex-col gap-2 border-l-2 border-border pl-3 sm:flex-row sm:flex-wrap sm:items-center"
                                >
                                    <fieldset class="flex flex-wrap items-center gap-1" :disabled="!puedeGestionar">
                                        <legend class="sr-only">Ausencia de {{ persona.nombre }}</legend>
                                        <label
                                            v-for="opcion in [{ valor: null, etiqueta: 'Sin indicar' }, ...justificaciones]"
                                            :key="opcion.valor ?? 'sin_indicar'"
                                            class="inline-flex h-7 cursor-pointer items-center gap-1.5 rounded-full border px-2.5 text-xs font-medium transition-colors has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring/50 has-[:disabled]:cursor-not-allowed"
                                            :class="
                                                persona.ausencia === opcion.valor
                                                    ? 'border-primary bg-accent text-accent-foreground'
                                                    : 'border-border text-muted-foreground hover:text-foreground'
                                            "
                                        >
                                            <input
                                                type="radio"
                                                class="sr-only"
                                                :name="`ausencia-${persona.id}`"
                                                :checked="persona.ausencia === opcion.valor"
                                                @change="elegirAusencia(persona, opcion.valor)"
                                            />
                                            <IconoTipo v-if="'icono' in opcion" :nombre="opcion.icono" class="size-3.5" />
                                            {{ opcion.etiqueta }}
                                        </label>
                                    </fieldset>

                                    <Input
                                        v-if="persona.ausencia === 'justificada'"
                                        :model-value="persona.motivo ?? ''"
                                        :disabled="!puedeGestionar"
                                        maxlength="500"
                                        placeholder="Motivo: baja médica, vacaciones, turno…"
                                        :aria-label="`Motivo de la ausencia de ${persona.nombre}`"
                                        :aria-invalid="conError.has(persona.id) || undefined"
                                        class="h-8 sm:max-w-sm sm:flex-1"
                                        @update:model-value="(valor) => (persona.motivo = String(valor))"
                                    />
                                </div>
                            </li>
                        </TransitionGroup>

                        <div v-if="puedeGestionar" class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <Button
                                :variant="sinGuardar ? 'default' : 'outline'"
                                :disabled="guardando"
                                @click="guardar"
                            >
                                {{ guardando ? 'Guardando…' : 'Guardar asistencia' }}
                            </Button>
                            <!--
                                `aria-live`: aparece y desaparece solo, así que
                                sin esto quien usa lector de pantalla no se
                                entera de que hay algo pendiente de mandar.
                            -->
                            <span role="status" aria-live="polite" class="text-xs font-medium text-estado-en-progreso">
                                <template v-if="sinGuardar">Sin guardar</template>
                            </span>
                            <span v-if="!sinGuardar && accion.asistenciaRegistrada" class="text-xs text-muted-foreground">
                                Guardada por última vez el <span class="cifra">{{ accion.asistenciaRegistrada }}</span>
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="porPuesto.length > 0 && registro.convocadas > 0">
                    <CardHeader>
                        <CardTitle>Por puesto</CardTitle>
                        <CardDescription>
                            Asistentes sobre personas activas en cada puesto. Un puesto entero a cero suele
                            ser un olvido de la convocatoria, no una decisión.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <GraficaBarras :barras="porPuesto" />
                    </CardContent>
                </Card>

                <Card v-if="accion.contenido">
                    <CardHeader>
                        <CardTitle>Contenido</CardTitle>
                    </CardHeader>
                    <CardContent class="max-w-prose text-sm whitespace-pre-line">{{ accion.contenido }}</CardContent>
                </Card>
            </div>

            <div class="flex min-w-0 flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-3 text-sm">
                            <dt class="text-muted-foreground">Tipo</dt>
                            <dd>{{ accion.tipoEtiqueta }}</dd>

                            <!--
                                Lo que cubre sale del mapeo del catálogo, no de
                                aquí: una misma sesión cuenta para los dos
                                marcos (invariante 6).
                            -->
                            <dt class="text-muted-foreground">Cubre</dt>
                            <dd class="flex flex-col gap-1.5">
                                <template v-if="cubre.length > 0">
                                    <span v-for="requisito in cubre" :key="`${requisito.marco}-${requisito.codigo}`">
                                        <span class="cifra">{{ requisito.codigo }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ requisito.marco }}</span>
                                    </span>
                                </template>
                                <span v-else class="cifra">{{ accion.medida }}</span>
                            </dd>

                            <dt class="text-muted-foreground">Fecha</dt>
                            <dd>{{ accion.fechaLarga }}</dd>

                            <template v-if="accion.duracion_horas">
                                <dt class="text-muted-foreground">Duración</dt>
                                <dd class="cifra">{{ accion.duracion_horas }} h</dd>
                            </template>

                            <dt class="text-muted-foreground">Vale hasta</dt>
                            <dd>
                                {{ accion.vigenteHastaLarga }}
                                <span class="text-muted-foreground">({{ accion.vigenteHastaRelativa }})</span>
                            </dd>
                        </dl>
                        <p class="mt-4 border-l-2 border-border pl-3 text-xs text-muted-foreground">
                            Los doce meses son la cadencia del producto: el ENS dice «periódicamente» y no pone
                            número.
                        </p>
                    </CardContent>
                </Card>

                <!--
                    La prueba, que es la mitad de la medida: `mp.per.3` y
                    `mp.per.4` no piden que se imparta la sesión, piden poder
                    demostrarlo. Se adjunta al editar la sesión.
                -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between gap-2">
                        <CardTitle>Hoja de firmas</CardTitle>
                        <CeldaBadge
                            :valor="
                                accion.evidencia_id
                                    ? { valor: 'demostrada', etiqueta: 'Demostrada', tono: 'implantado', icono: 'CircleCheck' }
                                    : { valor: 'declarada', etiqueta: 'Sin prueba', tono: 'en_progreso', icono: 'TriangleAlert' }
                            "
                        />
                    </CardHeader>
                    <CardContent class="text-sm">
                        <Link
                            v-if="accion.evidencia_id"
                            :href="`/evidencias/${accion.evidencia_id}`"
                            class="flex items-start gap-3 rounded-lg bg-superficie p-3 transition-colors hover:bg-fila-hover"
                        >
                            <IconoTipo nombre="FileCheck" class="mt-0.5 size-5 shrink-0 text-primary" />
                            <span class="min-w-0">
                                <span class="block font-medium underline underline-offset-4">{{ accion.evidencia }}</span>
                                <span v-if="accion.evidenciaFecha" class="block text-xs text-muted-foreground">
                                    Obtenida el <span class="cifra">{{ accion.evidenciaFecha }}</span>
                                </span>
                            </span>
                        </Link>
                        <p v-else class="text-muted-foreground">
                            Sin evidencia adjunta. Una sesión registrada y sin prueba está declarada y no
                            demostrada, que es lo que un auditor separa.
                            <Link
                                v-if="puedeGestionar"
                                :href="`/formacion/${accion.id}/editar`"
                                class="font-medium underline underline-offset-4"
                            >
                                Adjuntarla
                            </Link>
                        </p>
                    </CardContent>
                </Card>

                <!--
                    El material de la sesión, y NO la prueba: la hoja de firmas
                    es una evidencia porque prueba `mp.per.3`/`mp.per.4`, y esto
                    es lo demás —el temario, las diapositivas, el certificado del
                    proveedor—.
                -->
                <Card id="adjuntos">
                    <CardHeader>
                        <CardTitle>Material</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <BloqueAdjuntos
                            :adjuntos="adjuntos"
                            :base="`/formacion/${accion.id}/adjuntos`"
                            :puede-gestionar="puedeGestionar"
                            vacio="El temario, las diapositivas o el certificado del proveedor. La hoja de firmas va aparte, como evidencia."
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
