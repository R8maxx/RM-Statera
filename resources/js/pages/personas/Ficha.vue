<script setup lang="ts">
import { distanciaLegible, fechaLegible } from '@/lib/celdas';
import Aviso from '@/components/Aviso.vue';
import BloqueAdjuntos, { type Adjunto as Documento } from '@/components/adjunto/BloqueAdjuntos.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import ListaComprobacion, { type Paso } from '@/components/tarea/ListaComprobacion.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useDesplegable } from '@/composables/useDesplegable';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR } from '@/lib/formularios';
import { tono } from '@/lib/tonos';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    BriefcaseIcon,
    ChevronRightIcon,
    DownloadIcon,
    FileSignatureIcon,
    FileTextIcon,
    GraduationCapIcon,
    LogInIcon,
    LogOutIcon,
    PencilLineIcon,
    PlusIcon,
    UserCheckIcon,
    UserRoundIcon,
} from '@lucide/vue';
import { computed, ref } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface OpcionRol extends Opcion {
    unico: boolean;
    incompatibles: string[];
}

interface Asignacion {
    id: number;
    puesto_id: number;
    puesto: string | null;
    codigo: string | null;
    desde: string;
    hasta: string | null;
    vigente: boolean;
    asignadaPor: string | null;
    nota: string | null;
}

interface Persona {
    id: number;
    codigo: string;
    nombre: string;
    nombre_pila: string;
    apellido1: string | null;
    apellido2: string | null;
    nif: string | null;
    telefono: string | null;
    telefono_fijo: string | null;
    direccion: string | null;
    fecha_nacimiento: string | null;
    puesto: string | null;
    email: string | null;
    usuario: string | null;
    fecha_alta: string;
    fecha_baja: string | null;
    seudonimizada_en: string | null;
    activa: boolean;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    esperaCierreDeBaja: boolean;
}

interface Designacion {
    id: number;
    sistema_id: number;
    rol: string;
    rolEtiqueta: string;
    rolTono: string;
    rolIcono: string;
    sistema: string | null;
    sistemaNombre: string | null;
    desde: string;
    hasta: string | null;
    vigente: boolean;
    designadaPor: string | null;
    nota: string | null;
}

interface Formacion {
    id: number;
    accion_formativa_id: number;
    codigo: string | null;
    titulo: string | null;
    tipo: string | null;
    tipoTono: string | null;
    tipoIcono: string | null;
    medida: string | null;
    fecha: string | null;
    asistio: boolean;
    /** De quien faltó: «Justificada» o «Sin justificar»; nula es sin indicar. */
    ausencia: string | null;
    /** Los adjuntos que cuelgan a la vez de esta persona y de la sesión. */
    diplomas: { id: number; nombre_fichero: string }[];
}

interface Acuerdo {
    id: number;
    fecha_firma: string;
    vigente_hasta: string | null;
    vigente: boolean;
    nota: string | null;
    evidencia_id: number | null;
    evidencia: string | null;
}

interface Checklist {
    tipo: string;
    etiqueta: string;
    icono: string;
    tono: string;
    pasos: Paso[];
}

/**
 * La ficha de una persona: § 4.8.
 *
 * Cuatro bloques, y cada uno es una medida distinta: los nombramientos son la
 * cláusula 5.3, la formación `mp.per.3` y `mp.per.4`, el acuerdo `mp.per.2` y las
 * dos checklists son el alta y la baja.
 *
 * **La asistencia no se apunta desde aquí**, y es deliberado: lo que se registra
 * es una sesión con veinte convocados, y marcar veinte asistencias de una sesión
 * es un gesto mientras que apuntar veinte sesiones de una persona no lo es. Este
 * bloque enseña y enlaza.
 */
const props = defineProps<{
    persona: Persona;
    designaciones: Designacion[];
    formacion: Formacion[];
    acuerdos: Acuerdo[];
    adjuntos: Documento[];
    asignaciones: Asignacion[];
    puestos: Opcion[];
    pasos: Checklist[];
    maximoPasos: number;
    puedeGestionar: boolean;
    puedeDesignar: boolean;
    roles: OpcionRol[];
    sistemas: Opcion[];
    /** Llega sólo cuando se pide, al abrir el diálogo del acuerdo. */
    evidenciasDisponibles?: Opcion[];
}>();

/* --- Los nombramientos: cláusula 5.3 --- */

const designando = ref(false);

const designacion = useForm({ sistema_id: '', rol: '', desde: '', nota: '' });

function abrirDesignacion(): void {
    designacion.reset();
    designacion.clearErrors();
    designando.value = true;
}

function designar(): void {
    designacion.post(`/personas/${props.persona.id}/designaciones`, {
        preserveScroll: true,
        onSuccess: () => {
            designando.value = false;
            designacion.reset();
        },
    });
}

function revocar(id: number): void {
    router.delete(`/personas/${props.persona.id}/designaciones/${id}`, { preserveScroll: true });
}

/**
 * El aviso de incompatibilidad, **antes** de enviar.
 *
 * El dominio lo rechaza igual —`DesignarRol` es quien manda—, pero decir «esta
 * persona ya es responsable del sistema aquí» al elegir el rol se explica mucho
 * mejor que aceptar el formulario y devolver un error. Mismo criterio que la
 * columna prohibida del tablero de tareas, que se marca durante el arrastre.
 */
const avisoIncompatible = computed(() => {
    const rol = props.roles.find((item) => item.valor === designacion.rol);

    if (!rol || rol.incompatibles.length === 0 || designacion.sistema_id === '') {
        return null;
    }

    const choque = props.designaciones.find(
        (item) =>
            item.vigente &&
            String(item.sistema_id) === designacion.sistema_id &&
            rol.incompatibles.includes(item.rol),
    );

    return choque
        ? `${props.persona.nombre} ya es ${choque.rolEtiqueta} de ese sistema, y los dos roles son incompatibles.`
        : null;
});

const revocadasALaVista = ref(false);

const { asentada: revocadasAsentadas, alTerminarTransicion: alTerminarRevocadas } =
    useDesplegable(revocadasALaVista);

/*
 * La tarjeta de identificación sólo se pinta si hay algo que enseñar. Una
 * tarjeta con cinco rótulos y ningún valor ocupa sitio para decir lo mismo que
 * su ausencia, que es el criterio del resto del producto con los estados vacíos.
 */
const hayDatosDeContacto = computed(
    () =>
        props.persona.nif !== null ||
        props.persona.fecha_nacimiento !== null ||
        props.persona.telefono !== null ||
        props.persona.telefono_fijo !== null ||
        props.persona.direccion !== null,
);

const vigentes = computed(() => props.designaciones.filter((item) => item.vigente));
const historicas = computed(() => props.designaciones.filter((item) => !item.vigente));

/* --- El puesto que ocupa --- */

const asignacionVigente = computed(() => props.asignaciones.find((una) => una.vigente) ?? null);
const asignacionesPasadas = computed(() => props.asignaciones.filter((una) => !una.vigente));

const asignandoPuesto = ref(false);

const puestoForm = useForm({ puesto_id: '', desde: '', nota: '' });

function abrirPuesto(): void {
    puestoForm.reset();
    puestoForm.clearErrors();
    puestoForm.desde = new Date().toISOString().slice(0, 10);
    asignandoPuesto.value = true;
}

function asignarPuesto(): void {
    puestoForm.post(`/personas/${props.persona.id}/puesto`, {
        preserveScroll: true,
        onSuccess: () => {
            asignandoPuesto.value = false;
            puestoForm.reset();
        },
    });
}

/** Cierra el puesto sin poner otro: **no borra la fila**, le pone fecha de fin. */
function cerrarPuesto(id: number): void {
    router.delete(`/personas/${props.persona.id}/asignaciones/${id}`, { preserveScroll: true });
}

/* --- El acuerdo de confidencialidad: mp.per.2 --- */

const firmando = ref(false);

const acuerdo = useForm({ fecha_firma: '', vigente_hasta: '', nota: '', evidencia_id: SIN_VALOR });

/** Las candidatas no viajan con la ficha: se piden al abrir. */
const cargandoEvidencias = ref(false);

const evidencias = computed<Opcion[]>(() =>
    conOpcionVacia(props.evidenciasDisponibles ?? [], 'Sin documento adjunto'),
);

function abrirAcuerdo(): void {
    acuerdo.reset();
    acuerdo.clearErrors();
    acuerdo.fecha_firma = new Date().toISOString().slice(0, 10);
    firmando.value = true;

    cargandoEvidencias.value = true;
    router.reload({
        only: ['evidenciasDisponibles'],
        onFinish: () => (cargandoEvidencias.value = false),
    });
}

function guardarAcuerdo(): void {
    acuerdo.post(`/personas/${props.persona.id}/acuerdos`, {
        preserveScroll: true,
        onSuccess: () => {
            firmando.value = false;
            acuerdo.reset();
        },
    });
}

function borrarAcuerdo(id: number): void {
    router.delete(`/personas/${props.persona.id}/acuerdos/${id}`, { preserveScroll: true });
}

/*
 * --- El derecho de supresión (punto 36) ---
 *
 * Sólo para quien ya no está en plantilla, y con su propia confirmación porque
 * no se deshace. El servidor vuelve a comprobar las dos condiciones —baja y sin
 * nombramientos vigentes— y el motivo, si falla, llega en `supresion`.
 */
const suprimiendo = ref(false);
const supresion = useForm({});
// No es un campo del formulario, así que se lee de los errores de la página,
// como `errorCuenta` en la ficha de una cuenta.
const pagina = usePage();
const errorSupresion = computed(() => (pagina.props.errors as Record<string, string | undefined>).supresion);

function suprimir(): void {
    supresion.post(`/personas/${props.persona.id}/seudonimizar`, {
        preserveScroll: true,
        onSuccess: () => (suprimiendo.value = false),
    });
}

/* --- Las dos checklists --- */

/**
 * Se autoguarda en cada gesto, como la de una tarea, y no con un botón.
 *
 * Tuvo botón y «Sin guardar» durante un rato, con el argumento de que marcar un
 * paso de la baja de alguien no es un gesto suelto. No se sostiene: la raya que
 * tacha el paso **es** el acuse —`DESIGN.md` §10— y con guardado diferido
 * dibujaría el acuse de algo que todavía no ha salido del navegador. Y la
 * asimetría de riesgo va al revés: un paso marcado por error se desmarca y
 * `GuardarPasos` limpia su fecha, mientras que un paso marcado y perdido al
 * navegar deja la salida sin cerrar **y a alguien convencido de que constaba**,
 * que es justo el único rojo de este módulo.
 *
 * **El indicador de envío es por lista y no global**: son dos rutas a la misma
 * URL con `tipo` distinto, y con un solo flag marcar en la de alta bloquearía
 * la de baja.
 */
const guardando = ref<Record<string, boolean>>({});

/**
 * Lo que el servidor rechace, dicho aquí.
 *
 * El guardado de la checklist no pasa por un formulario con sus campos, así
 * que su 422 —el del tope de pasos, por ejemplo— no tenía dónde pintarse: se
 * pulsaba Guardar, no pasaba nada visible y el paso se perdía.
 */
const errorPasos = ref<Record<string, string | null>>({});

function guardarLista(tipo: string, pasos: Paso[]): void {
    guardando.value = { ...guardando.value, [tipo]: true };
    errorPasos.value = { ...errorPasos.value, [tipo]: null };

    router.put(
        `/personas/${props.persona.id}/pasos`,
        {
            tipo,
            pasos: pasos.map((paso) => ({ id: paso.id, titulo: paso.titulo, hecho: paso.hecho })),
        },
        {
            preserveScroll: true,
            onError: (errores) => {
                /*
                 * Por lista y no uno para las dos: con un solo mensaje, un error
                 * al guardar la de alta salía también en la de baja.
                 */
                errorPasos.value = {
                    ...errorPasos.value,
                    [tipo]:
                        Object.values(errores)[0] ??
                        'No se ha podido guardar la checklist. Revisa los pasos e inténtalo otra vez.',
                };
            },
            onFinish: () => (guardando.value = { ...guardando.value, [tipo]: false }),
        },
    );
}

/** Con quien ya se fue, la de salida delante: es la que queda por cerrar. */
const listasOrdenadas = computed(() =>
    props.persona.fecha_baja === null
        ? props.pasos
        : [...props.pasos].sort((a, b) => Number(b.tipo === 'baja') - Number(a.tipo === 'baja')),
);

/*
 * --- El resumen de arriba ---
 *
 * Una fila con lo que un auditor mira de una persona —puesto, cargos ENS,
 * `mp.per.2`, `mp.per.3`/`mp.per.4` y la checklist que toca— antes del detalle.
 * Es el elemento fuerte de la ficha (DESIGN.md §1): sin él eran seis tarjetas
 * del mismo peso y había que leerlas todas para saber si faltaba algo. Todo se
 * cuenta desde las props que ya llegan; no pide nada al servidor.
 */
const sistemasVigentes = computed(() => [
    ...new Set(vigentes.value.map((item) => item.sistema).filter((sistema) => sistema !== null)),
]);

const acuerdoVigente = computed(() => props.acuerdos.find((item) => item.vigente) ?? null);

/**
 * Firmado y con su papel, firmado sin papel o sin firmar. El del medio no es
 * rojo: la medida está declarada y no probada, que es un «a medias» y no algo
 * que vaya mal.
 */
const situacionAcuerdo = computed(() => {
    const vigente = acuerdoVigente.value;

    if (vigente === null) {
        return { valor: 'sin_acuerdo', etiqueta: 'Sin acuerdo vigente', tono: 'no_iniciado', icono: 'FileX' };
    }

    return vigente.evidencia_id === null
        ? { valor: 'sin_documento', etiqueta: 'Firmado, sin documento', tono: 'en_progreso', icono: 'PenLine' }
        : { valor: 'probado', etiqueta: 'Firmado y probado', tono: 'implantado', icono: 'FileCheck' };
});

/** La formación llega ordenada de la más reciente a la más antigua. */
const asistidas = computed(() => props.formacion.filter((item) => item.asistio));

/** Mientras está en plantilla cuenta la de alta; tras la baja, la de salida. */
const listaDelResumen = computed(
    () => props.pasos.find((lista) => lista.tipo === (props.persona.fecha_baja === null ? 'alta' : 'baja')) ?? null,
);

const pasosHechos = computed(() => listaDelResumen.value?.pasos.filter((paso) => paso.hecho).length ?? 0);
const pasosTotales = computed(() => listaDelResumen.value?.pasos.length ?? 0);

/**
 * Los tramos de una barra de avance: lo hecho en verde y lo que falta en el
 * hueco gris, o en rojo si es la salida de alguien que ya se fue.
 */
function tramos(hechos: number, total: number, alarma = false): { clase: string; peso: number }[] {
    return [
        { clase: tono('implantado').tramo, peso: hechos },
        { clase: tono(alarma ? 'caducada' : 'no_iniciado').tramo, peso: total - hechos },
    ].filter((tramo) => tramo.peso > 0);
}

/** Una checklist de salida sin estrenar en alguien que sigue: se pinta en reposo. */
function enReposo(lista: Checklist): boolean {
    return lista.tipo === 'baja' && props.persona.fecha_baja === null && lista.pasos.every((paso) => !paso.hecho);
}
</script>

<template>
    <AppLayout :titulo="persona.nombre">
        <!--
            El estado, las fechas y el correo van dentro de la cabecera y no en
            una fila suelta debajo: son lo que dice quién es, junto al nombre.
        -->
        <CabeceraPagina :titulo="persona.nombre" :codigo="persona.codigo">
            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                <CeldaBadge anunciar
                    :valor="{
                        valor: persona.activa ? 'activa' : 'baja',
                        etiqueta: persona.estadoEtiqueta,
                        tono: persona.estadoTono,
                        icono: persona.estadoIcono,
                    }"
                />
                <span v-if="persona.puesto">{{ persona.puesto }}</span>
                <span v-if="persona.fecha_baja">
                    Del {{ fechaLegible(persona.fecha_alta) }} al {{ fechaLegible(persona.fecha_baja) }}
                    ({{ distanciaLegible(persona.fecha_baja) }})
                </span>
                <span v-else>Alta el {{ fechaLegible(persona.fecha_alta) }}</span>
                <a
                    v-if="persona.email"
                    :href="`mailto:${persona.email}`"
                    class="text-primary underline-offset-4 hover:underline"
                >{{ persona.email }}</a>
            </div>

            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/personas/${persona.id}/editar`">
                        <PencilLineIcon />
                        Editar
                    </Link>
                </Button>
            </template>
        </CabeceraPagina>

        <!--
            El único rojo del módulo, y es el hermano exacto del equipo retirado
            sin constancia de borrado: la herramienta no corrige el dato, lo pone
            delante.
        -->
        <Aviso
            v-if="persona.esperaCierreDeBaja"
            tono="error"
            titulo="Se fue con la checklist de salida a medias"
        >
            Quedan pasos sin marcar. Un acceso que nadie revocó es el hallazgo clásico de
            <span class="cifra">mp.per.*</span>, y es lo que un auditor comprueba primero.
        </Aviso>

        <!--
            Lo que el auditor mira, antes que el detalle. `gap-px` sobre el fondo
            del borde dibuja los divisores en cualquier número de columnas: con
            `divide-x` se perdían al partirse la fila en móvil.
        -->
        <section
            aria-label="Medidas de personal"
            class="grid gap-px overflow-hidden rounded-xl bg-border ring-1 ring-foreground/10 sm:grid-cols-2 lg:grid-cols-5"
        >
            <div class="flex flex-col gap-2 bg-card px-6 py-5">
                <p class="flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground">
                    <BriefcaseIcon class="size-4" />
                    Puesto
                </p>
                <p class="text-lg leading-6 font-semibold" :class="asignacionVigente === null && 'text-muted-foreground'">
                    {{ asignacionVigente?.puesto ?? 'Sin puesto' }}
                </p>
                <p class="text-xs text-muted-foreground">
                    <template v-if="asignacionVigente">Desde el {{ asignacionVigente.desde }}</template>
                    <template v-else-if="asignacionesPasadas.length > 0">
                        {{ asignacionesPasadas[0].puesto }} hasta el {{ asignacionesPasadas[0].hasta }}
                    </template>
                    <template v-else>No figura en el organigrama</template>
                </p>
            </div>

            <div class="flex flex-col gap-2 bg-card px-6 py-5">
                <p class="flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground">
                    <UserCheckIcon class="size-4" />
                    Nombramientos ENS
                </p>
                <p class="text-lg leading-6 font-semibold">
                    <span class="cifra">{{ vigentes.length }}</span>
                    {{ vigentes.length === 1 ? 'vigente' : 'vigentes' }}
                </p>
                <p class="text-xs text-muted-foreground">
                    <template v-if="sistemasVigentes.length > 0">
                        En <span class="cifra">{{ sistemasVigentes.join(', ') }}</span>
                    </template>
                    <template v-else>Ningún cargo ahora</template>
                    <template v-if="historicas.length > 0">
                        · {{ historicas.length }} revocado{{ historicas.length === 1 ? '' : 's' }}
                    </template>
                </p>
            </div>

            <div class="flex flex-col gap-2 bg-card px-6 py-5">
                <p class="flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground">
                    <FileSignatureIcon class="size-4" />
                    Confidencialidad
                    <span class="cifra text-xs">mp.per.2</span>
                </p>
                <div><CeldaBadge :valor="situacionAcuerdo" /></div>
                <p class="text-xs text-muted-foreground">
                    <template v-if="acuerdoVigente">
                        Firmado el {{ acuerdoVigente.fecha_firma }} ·
                        {{ acuerdoVigente.vigente_hasta ? `hasta el ${acuerdoVigente.vigente_hasta}` : 'sin vencimiento' }}
                    </template>
                    <template v-else>Los deberes tienen que constar por escrito</template>
                </p>
            </div>

            <div class="flex flex-col gap-2 bg-card px-6 py-5">
                <p class="flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground">
                    <GraduationCapIcon class="size-4" />
                    Formación
                    <span class="cifra text-xs">mp.per.3·4</span>
                </p>
                <p class="text-lg leading-6 font-semibold">
                    <span class="cifra">{{ asistidas.length }}</span> de
                    <span class="cifra">{{ formacion.length }}</span>
                    {{ formacion.length === 1 ? 'sesión' : 'sesiones' }}
                </p>
                <div
                    v-if="formacion.length > 0"
                    class="flex h-1.5 gap-0.5"
                    role="img"
                    :aria-label="`Asistió a ${asistidas.length} de ${formacion.length} sesiones`"
                >
                    <div
                        v-for="tramo in tramos(asistidas.length, formacion.length)"
                        :key="tramo.clase"
                        class="rounded-full"
                        :class="tramo.clase"
                        :style="{ flexGrow: tramo.peso }"
                    />
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ asistidas.length > 0 ? `Última, el ${asistidas[0].fecha}` : 'Sin asistencias registradas' }}
                </p>
            </div>

            <div class="flex flex-col gap-2 bg-card px-6 py-5 sm:col-span-2 lg:col-span-1">
                <p class="flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground">
                    <component :is="persona.fecha_baja === null ? LogInIcon : LogOutIcon" class="size-4" />
                    Checklist de {{ persona.fecha_baja === null ? 'alta' : 'salida' }}
                </p>
                <p class="text-lg leading-6 font-semibold">
                    <span class="cifra">{{ pasosHechos }}</span> de
                    <span class="cifra">{{ pasosTotales }}</span> pasos
                </p>
                <div
                    v-if="pasosTotales > 0"
                    class="flex h-1.5 gap-0.5"
                    role="img"
                    :aria-label="`${pasosHechos} de ${pasosTotales} pasos hechos`"
                >
                    <div
                        v-for="tramo in tramos(pasosHechos, pasosTotales, persona.esperaCierreDeBaja)"
                        :key="tramo.clase"
                        class="rounded-full"
                        :class="tramo.clase"
                        :style="{ flexGrow: tramo.peso }"
                    />
                </div>
                <p
                    class="text-xs"
                    :class="persona.esperaCierreDeBaja ? 'font-medium text-destructive' : 'text-muted-foreground'"
                >
                    <template v-if="pasosTotales === 0">Sin pasos escritos</template>
                    <template v-else-if="pasosHechos === pasosTotales">Todos hechos</template>
                    <template v-else>{{ pasosTotales - pasosHechos }} pendientes</template>
                </p>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start">
            <div class="space-y-6">
                <!--
                    Puesto y nombramientos en una sola tarjeta: las dos cosas
                    contestan a qué papel tiene esta persona, las dos llevan
                    vigencia y las dos guardan lo que fue.

                    Y el puesto va ANTES que los nombramientos, que no es un
                    capricho de orden: el puesto es lo que esta persona hace
                    todos los días, y un nombramiento ENS es un cargo que se le
                    suma. Al revés, lo primero de la ficha estaría vacío para
                    casi toda la plantilla.
                -->
                <Card>
                    <CardHeader>
                        <CardTitle>Puesto y nombramientos</CardTitle>
                        <CardDescription>
                            Lo que hace cada día y los cargos del ENS que se le suman. Nada se borra:
                            cada fila guarda desde cuándo y hasta cuándo.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <section class="space-y-3" aria-labelledby="titulo-puesto">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 id="titulo-puesto" class="text-sm font-semibold">Puesto</h3>
                                <div v-if="puedeGestionar" class="flex flex-wrap gap-2">
                                    <Button
                                        v-if="asignacionVigente"
                                        variant="ghost"
                                        size="sm"
                                        @click="cerrarPuesto(asignacionVigente.id)"
                                    >
                                        Dejar el puesto
                                    </Button>
                                    <Button v-if="persona.activa" variant="outline" size="sm" @click="abrirPuesto">
                                        {{ asignacionVigente === null ? 'Asignar un puesto' : 'Cambiar de puesto' }}
                                    </Button>
                                </div>
                            </div>

                            <EstadoVacio
                                v-if="asignaciones.length === 0"
                                :icono="BriefcaseIcon"
                                titulo="Sin puesto asignado"
                                descripcion="Asignarle uno es lo que la coloca en el organigrama."
                            />

                            <!--
                                Una línea de tiempo: el vigente con su punto de
                                marca y los anteriores en hueco. Leída de arriba
                                abajo contesta a «qué puesto tenía el 3 de marzo».
                            -->
                            <ol v-else class="text-sm">
                                <li
                                    v-for="(asignacion, indice) in asignaciones"
                                    :key="asignacion.id"
                                    class="grid grid-cols-[1.25rem_minmax(0,1fr)_auto] gap-x-3"
                                >
                                    <div class="flex flex-col items-center" aria-hidden="true">
                                        <span
                                            class="mt-1.5 size-2.5 shrink-0 rounded-full"
                                            :class="
                                                asignacion.vigente
                                                    ? 'bg-primary ring-4 ring-primary/15'
                                                    : 'border-2 border-border bg-card'
                                            "
                                        />
                                        <span v-if="indice < asignaciones.length - 1" class="mt-1.5 w-0.5 grow bg-border" />
                                    </div>
                                    <div class="min-w-0 space-y-0.5 pb-4">
                                        <Link
                                            :href="`/puestos/${asignacion.puesto_id}`"
                                            class="underline-offset-4 hover:underline"
                                            :class="asignacion.vigente ? 'font-semibold' : 'text-muted-foreground'"
                                        >
                                            <span class="cifra font-normal text-muted-foreground">{{ asignacion.codigo }}</span>
                                            {{ asignacion.puesto }}
                                        </Link>
                                        <p v-if="asignacion.nota" class="text-xs text-muted-foreground">{{ asignacion.nota }}</p>
                                    </div>
                                    <p
                                        class="pb-4 text-right text-[13px]"
                                        :class="asignacion.vigente ? 'text-secondary-foreground' : 'text-muted-foreground'"
                                    >
                                        <template v-if="asignacion.vigente">Desde el {{ asignacion.desde }}</template>
                                        <template v-else>{{ asignacion.desde }} – {{ asignacion.hasta }}</template>
                                    </p>
                                </li>
                            </ol>
                        </section>

                        <!-- Cláusula 5.3 -->
                        <section class="space-y-3 border-t pt-6" aria-labelledby="titulo-nombramientos">
                            <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2">
                                <div>
                                    <h3 id="titulo-nombramientos" class="text-sm font-semibold">
                                        Nombramientos ENS
                                        <span class="cifra ml-1 text-xs font-normal text-muted-foreground">cláusula 5.3</span>
                                    </h3>
                                    <p class="mt-1 max-w-2xl text-[13px] text-muted-foreground">
                                        Por sistema. Quien decide qué protección hace falta no puede ser quien
                                        responde de haberla puesto.
                                    </p>
                                </div>
                                <Button v-if="puedeDesignar" variant="outline" size="sm" @click="abrirDesignacion">
                                    <PlusIcon />
                                    Designar en un rol
                                </Button>
                            </div>

                            <EstadoVacio
                                v-if="vigentes.length === 0"
                                :icono="UserCheckIcon"
                                titulo="Sin nombramientos vigentes"
                                descripcion="Los roles ENS se designan por sistema, porque es donde la incompatibilidad significa algo."
                            />
                            <Table v-else>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Rol</TableHead>
                                        <TableHead>Sistema</TableHead>
                                        <TableHead>Designación</TableHead>
                                        <TableHead class="text-right">Desde</TableHead>
                                        <TableHead v-if="puedeDesignar"><span class="sr-only">Acciones</span></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TransitionGroup tag="tbody" name="paso" data-slot="table-body" class="[&_tr:last-child]:border-0">
                                    <TableRow v-for="item in vigentes" :key="item.id">
                                        <TableCell class="align-top">
                                            <CeldaBadge
                                                :valor="{
                                                    valor: item.rol,
                                                    etiqueta: item.rolEtiqueta,
                                                    tono: item.rolTono,
                                                    icono: item.rolIcono,
                                                }"
                                            />
                                        </TableCell>
                                        <TableCell class="align-top whitespace-normal">
                                            <span class="cifra block text-[13px]">{{ item.sistema }}</span>
                                            <span v-if="item.sistemaNombre" class="block text-xs text-muted-foreground">
                                                {{ item.sistemaNombre }}
                                            </span>
                                        </TableCell>
                                        <!--
                                            Dónde consta el nombramiento —«acta del
                                            comité del 3 de marzo»—. El diálogo lo pide
                                            con esas palabras, y sin pintarlo es lo
                                            mismo que no haberlo escrito.
                                        -->
                                        <TableCell class="align-top whitespace-normal">
                                            <span v-if="item.designadaPor" class="block text-[13px]">Por {{ item.designadaPor }}</span>
                                            <span class="block text-xs text-muted-foreground">
                                                {{ item.nota ?? 'Sin nota de dónde consta' }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="cifra text-right align-top text-[13px]">{{ item.desde }}</TableCell>
                                        <TableCell v-if="puedeDesignar" class="py-1.5 text-right align-top">
                                            <Button variant="ghost" size="sm" @click="revocar(item.id)">Revocar</Button>
                                        </TableCell>
                                    </TableRow>
                                </TransitionGroup>
                            </Table>

                            <!--
                                Se pliega desde el título y con el chevron delante,
                                como una sección de formulario: era el único
                                `<details>` del producto, con el triángulo del
                                navegador y sin `aria-expanded`.
                            -->
                            <div v-if="historicas.length > 0" class="text-sm">
                                <button
                                    type="button"
                                    class="group flex items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
                                    :aria-expanded="revocadasALaVista"
                                    aria-controls="nombramientos-revocados"
                                    @click="revocadasALaVista = !revocadasALaVista"
                                >
                                    <ChevronRightIcon
                                        class="size-4 shrink-0 transition-transform group-hover:text-primary"
                                        :class="revocadasALaVista ? 'rotate-90' : undefined"
                                    />
                                    {{ historicas.length }} nombramiento{{ historicas.length === 1 ? '' : 's' }} revocado{{ historicas.length === 1 ? '' : 's' }}
                                </button>
                                <!--
                                    `.desplegable` y no `v-show`: el chevron gira y
                                    lo mandado aparecía de golpe, que es el defecto
                                    que documenta `SeccionFormulario`. El `mt-2` va
                                    en el div de dentro y nunca en el hijo directo
                                    de la rejilla —ahí dejaría dos milímetros
                                    visibles con el bloque cerrado—, y el
                                    `data-asentado` no es opcional: sin él el
                                    `overflow: hidden` recorta el anillo de foco.
                                -->
                                <div
                                    id="nombramientos-revocados"
                                    class="desplegable"
                                    :data-abierto="revocadasALaVista ? '' : undefined"
                                    :data-asentado="revocadasAsentadas ? '' : undefined"
                                    :inert="!revocadasALaVista"
                                    @transitionend="alTerminarRevocadas"
                                >
                                    <div class="mt-2">
                                        <ul class="divide-y divide-border">
                                            <li
                                                v-for="item in historicas"
                                                :key="item.id"
                                                class="flex flex-wrap items-center gap-2 py-2 text-muted-foreground"
                                            >
                                                <span>{{ item.rolEtiqueta }}</span>
                                                <span class="cifra text-xs">{{ item.sistema }}</span>
                                                <span class="ml-auto text-xs">{{ item.desde }} – {{ item.hasta }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </CardContent>
                </Card>

                <!-- mp.per.3 y mp.per.4 -->
                <Card>
                    <CardHeader>
                        <CardTitle>Formación y concienciación</CardTitle>
                        <CardDescription>
                            La asistencia se apunta desde la sesión, con todos sus convocados, y no
                            desde aquí.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <EstadoVacio
                            v-if="formacion.length === 0"
                            :icono="GraduationCapIcon"
                            titulo="Sin formación registrada"
                            descripcion="La asistencia se apunta desde la sesión y no desde aquí: lo que se registra es una convocatoria con sus asistentes."
                            :accion="{ etiqueta: 'Ir a formación', href: '/formacion' }"
                        />
                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Sesión</TableHead>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead>Asistencia</TableHead>
                                    <TableHead class="text-right">Fecha</TableHead>
                                    <TableHead class="text-right">Diploma</TableHead>
                                </TableRow>
                            </TableHeader>
                            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
                                <TableRow v-for="item in formacion" :key="item.id">
                                    <TableCell class="whitespace-normal">
                                        <Link
                                            :href="`/formacion/${item.accion_formativa_id}`"
                                            class="font-medium underline-offset-4 hover:underline"
                                        >
                                            {{ item.titulo }}
                                        </Link>
                                    </TableCell>
                                    <!--
                                        Concienciar y formar son dos medidas
                                        distintas —`mp.per.3` y `mp.per.4`—, y el
                                        servidor ya manda su tono y su icono.
                                    -->
                                    <TableCell>
                                        <span class="inline-flex items-center gap-1.5">
                                            <CeldaBadge
                                                v-if="item.tipo"
                                                :valor="{
                                                    valor: item.tipo,
                                                    etiqueta: item.tipo,
                                                    tono: item.tipoTono,
                                                    icono: item.tipoIcono,
                                                }"
                                            />
                                            <span class="cifra text-xs text-muted-foreground">{{ item.medida }}</span>
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="inline-flex items-center gap-1.5">
                                            <CeldaBadge
                                                :valor="{
                                                    valor: item.asistio ? 'asistio' : 'falto',
                                                    etiqueta: item.asistio ? 'Asistió' : 'No asistió',
                                                    tono: item.asistio ? 'implantado' : 'no_iniciado',
                                                    icono: item.asistio ? 'Check' : 'Minus',
                                                }"
                                            />
                                            <span v-if="!item.asistio && item.ausencia" class="text-xs text-muted-foreground">
                                                {{ item.ausencia }}
                                            </span>
                                        </span>
                                    </TableCell>
                                    <TableCell class="cifra text-right text-[13px] text-muted-foreground">{{ item.fecha }}</TableCell>
                                    <TableCell class="text-right">
                                        <a
                                            v-for="diploma in item.diplomas"
                                            :key="diploma.id"
                                            :href="`/personas/${persona.id}/adjuntos/${diploma.id}/descargar`"
                                            class="inline-flex items-center gap-1 text-[13px] font-medium text-primary underline-offset-4 hover:underline"
                                            :title="diploma.nombre_fichero"
                                        >
                                            <DownloadIcon class="size-3.5" />
                                            Descargar
                                        </a>
                                        <span v-if="item.diplomas.length === 0" class="text-muted-foreground">—</span>
                                    </TableCell>
                                </TableRow>
                            </tbody>
                        </Table>
                    </CardContent>
                </Card>

                <!--
                    Las dos checklists en una tarjeta: son las dos mitades del
                    mismo trámite. Con quien ya se fue, la de salida va delante,
                    porque es la que queda por cerrar. Lado a lado sólo en 2xl:
                    por debajo, con la fecha de cada paso, la columna no cabe.
                -->
                <Card>
                    <CardHeader>
                        <CardTitle>Incorporación y salida</CardTitle>
                        <CardDescription>
                            Marcar todos los pasos no da de baja a nadie: la baja es una fecha, y se pone
                            al editar la persona.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 2xl:grid-cols-2 2xl:items-start">
                        <section
                            v-for="lista in listasOrdenadas"
                            :key="lista.tipo"
                            class="space-y-3 rounded-lg border p-4"
                            :class="enReposo(lista) && 'border-dashed bg-background'"
                            :aria-labelledby="`titulo-pasos-${lista.tipo}`"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 :id="`titulo-pasos-${lista.tipo}`" class="flex items-center gap-2 text-sm font-semibold">
                                        <IconoTipo :nombre="lista.icono" class="text-muted-foreground" />
                                        Checklist de {{ lista.etiqueta.toLowerCase() }}
                                    </h3>
                                    <p class="mt-1 text-[13px] text-muted-foreground">
                                        {{
                                            lista.tipo === 'baja'
                                                ? 'La que el auditor mira: un acceso que nadie revocó es el hallazgo clásico.'
                                                : 'Entregar el equipo, firmar el acuerdo, dar de alta las cuentas.'
                                        }}
                                    </p>
                                </div>
                                <span
                                    v-if="lista.pasos.length > 0"
                                    class="cifra shrink-0 text-xs"
                                    :class="
                                        lista.pasos.every((paso) => paso.hecho)
                                            ? 'text-estado-implantado'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ lista.pasos.filter((paso) => paso.hecho).length }} de {{ lista.pasos.length }}
                                </span>
                            </div>

                            <Aviso v-if="errorPasos[lista.tipo]" tono="error">{{ errorPasos[lista.tipo] }}</Aviso>

                            <!--
                                La misma lista de comprobación que una tarea, y con
                                fecha: aquí «¿desde cuándo consta hecho este paso?»
                                es una pregunta del auditor, no un detalle.
                            -->
                            <ListaComprobacion
                                :pasos="lista.pasos"
                                :maximo="maximoPasos"
                                :editable="puedeGestionar"
                                :ocupado="guardando[lista.tipo] === true"
                                con-fecha
                                vacio="Sin pasos. Se escriben una vez y valen para quien venga detrás."
                                @guardar="(pasos) => guardarLista(lista.tipo, pasos)"
                            />
                        </section>
                    </CardContent>
                </Card>
            </div>

            <!--
                La columna lateral en el orden de DESIGN.md §9: «Ficha» primero
                —esta persona no tiene máquina de estados, su estado es una
                fecha y va en la cabecera— y el resto detrás.
            -->
            <div class="space-y-6">
                <!--
                    Los datos personales identifican y localizan a quien figura en
                    un nombramiento, pero no son lo que se viene a mirar. Los que
                    no hay no se pintan — cinco guiones no dicen nada que su
                    ausencia no diga —, y la cuenta de Statera va con ellos.
                -->
                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <dl v-if="hayDatosDeContacto" class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-3">
                            <template v-if="persona.nif">
                                <dt class="text-muted-foreground">Documento</dt>
                                <dd class="cifra text-[13px]">{{ persona.nif }}</dd>
                            </template>
                            <template v-if="persona.fecha_nacimiento">
                                <dt class="text-muted-foreground">Nacimiento</dt>
                                <dd>{{ fechaLegible(persona.fecha_nacimiento) }}</dd>
                            </template>
                            <template v-if="persona.telefono">
                                <dt class="text-muted-foreground">Teléfono</dt>
                                <dd class="cifra text-[13px]">{{ persona.telefono }}</dd>
                            </template>
                            <template v-if="persona.telefono_fijo">
                                <dt class="text-muted-foreground">Teléfono fijo</dt>
                                <dd class="cifra text-[13px]">{{ persona.telefono_fijo }}</dd>
                            </template>
                            <template v-if="persona.direccion">
                                <dt class="text-muted-foreground">Dirección</dt>
                                <dd class="whitespace-pre-line">{{ persona.direccion }}</dd>
                            </template>
                        </dl>

                        <div class="flex items-start gap-3" :class="hayDatosDeContacto && 'border-t pt-4'">
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full"
                                :class="persona.usuario ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'"
                                aria-hidden="true"
                            >
                                <UserRoundIcon class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="font-medium">{{ persona.usuario ? 'Cuenta de Statera' : 'Sin cuenta de Statera' }}</p>
                                <p v-if="persona.usuario" class="truncate text-xs text-muted-foreground">{{ persona.usuario }}</p>
                                <p v-else class="text-xs text-muted-foreground">
                                    Lo normal: la mayoría de una plantilla no entra nunca en la herramienta.
                                    Sin cuenta no se le pueden asignar tareas ni pedir el acuse de lectura.
                                </p>
                            </div>
                        </div>

                        <Aviso v-if="persona.seudonimizada_en" titulo="Datos personales suprimidos">
                            El {{ fechaLegible(persona.seudonimizada_en) }}. Sus nombramientos, su formación y
                            sus acuses siguen en el registro, sin nada que diga quién era.
                        </Aviso>
                    </CardContent>
                </Card>

                <!-- mp.per.2 -->
                <Card>
                    <CardHeader>
                        <CardTitle>Confidencialidad</CardTitle>
                        <CardAction>
                            <span class="cifra rounded-md bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">mp.per.2</span>
                        </CardAction>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <EstadoVacio
                            v-if="acuerdos.length === 0"
                            :icono="FileSignatureIcon"
                            titulo="Sin acuerdo firmado"
                            descripcion="Los deberes tienen que constar por escrito: sin papel, la medida está declarada y no probada."
                        />
                        <!--
                            Cada acuerdo se cita con la regla de 2 px a la
                            izquierda (DESIGN.md §9) y no con una caja dentro
                            de la tarjeta.
                        -->
                        <TransitionGroup v-else tag="ul" name="paso" class="space-y-4">
                            <li
                                v-for="item in acuerdos"
                                :key="item.id"
                                class="space-y-1.5 border-l-2 pl-3 text-sm"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <CeldaBadge
                                        :valor="{
                                            valor: item.vigente ? 'vigente' : 'caducado',
                                            etiqueta: item.vigente ? 'Vigente' : 'Caducado',
                                            tono: item.vigente ? 'implantado' : 'no_iniciado',
                                            icono: item.vigente ? 'FileCheck' : 'FileX',
                                        }"
                                    />
                                    <span class="text-xs text-muted-foreground">
                                        {{ item.vigente_hasta ? `Hasta el ${item.vigente_hasta}` : 'Sin vencimiento' }}
                                    </span>
                                </div>
                                <p class="font-medium">Firmado el {{ item.fecha_firma }}</p>
                                <!--
                                    El documento y la nota son las dos cosas que
                                    un auditor pide al lado de una firma: dónde
                                    consta y qué papel lo prueba.
                                -->
                                <Link
                                    v-if="item.evidencia_id"
                                    :href="`/evidencias/${item.evidencia_id}`"
                                    class="inline-flex items-center gap-1.5 text-[13px] text-primary underline-offset-4 hover:underline"
                                >
                                    <FileTextIcon class="size-3.5 shrink-0" />
                                    {{ item.evidencia }}
                                </Link>
                                <p v-else class="text-xs text-muted-foreground">Sin documento: declarada y no probada.</p>
                                <p v-if="item.nota" class="text-xs text-muted-foreground">{{ item.nota }}</p>
                                <Button
                                    v-if="puedeGestionar"
                                    variant="ghost"
                                    size="sm"
                                    class="-ml-2.5"
                                    @click="borrarAcuerdo(item.id)"
                                >
                                    Borrar
                                </Button>
                            </li>
                        </TransitionGroup>

                        <Button v-if="puedeGestionar" variant="outline" size="sm" @click="abrirAcuerdo">
                            Registrar acuerdo
                        </Button>
                    </CardContent>
                </Card>

                <!--
                    Los documentos de la persona: el título de un curso, el
                    contrato firmado, el DNI escaneado. NO son evidencias —una
                    evidencia prueba un requisito y lleva caducidad y
                    responsable—, y el diálogo lo dice para que nadie suba aquí
                    lo que va allí.
                -->
                <Card id="adjuntos">
                    <CardHeader>
                        <CardTitle>Documentos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <BloqueAdjuntos
                            :adjuntos="adjuntos"
                            :base="`/personas/${persona.id}/adjuntos`"
                            :puede-gestionar="puedeGestionar"
                            vacio="Aquí van sus papeles: el título de un curso, el contrato firmado, lo que haya que conservar de esta persona."
                        />
                    </CardContent>
                </Card>

                <!--
                    Lo que no se deshace va aparte y al final, sin tarjeta: la
                    regla de Protección (DESIGN.md §1) pide separar lo
                    destructivo del resto.
                -->
                <section
                    v-if="!persona.seudonimizada_en && puedeGestionar && !persona.activa"
                    class="space-y-3 rounded-xl border px-6 py-5"
                    aria-labelledby="titulo-supresion"
                >
                    <h2 id="titulo-supresion" class="text-sm font-semibold">Derecho de supresión</h2>
                    <p class="text-[13px] text-muted-foreground">
                        Borra sus datos personales y sus adjuntos. Queda como «Persona
                        <span class="cifra">{{ persona.codigo }}</span>», con sus nombramientos, su formación y
                        sus acuses. No se deshace.
                    </p>
                    <Button variant="destructive" size="sm" @click="suprimiendo = true">
                        Suprimir datos personales
                    </Button>
                </section>
            </div>
        </div>

        <Dialog v-model:open="suprimiendo">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>¿Suprimir los datos de {{ persona.nombre }}?</DialogTitle>
                    <DialogDescription>
                        No se puede deshacer. Se borran su nombre, su documento, sus teléfonos, su
                        domicilio, su fecha de nacimiento, su correo, las notas, el vínculo con su cuenta y
                        sus documentos adjuntos, también de la traza. En el registro queda como «Persona
                        {{ persona.codigo }}», con sus nombramientos, su formación y sus acuses.
                    </DialogDescription>
                </DialogHeader>

                <Aviso v-if="errorSupresion" tono="error">{{ errorSupresion }}</Aviso>

                <DialogFooter>
                    <Button variant="outline" @click="suprimiendo = false">Cancelar</Button>
                    <Button variant="destructive" :disabled="supresion.processing" @click="suprimir">
                        Suprimir
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Designar en un rol -->
        <Dialog v-model:open="designando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Designar a {{ persona.nombre }}</DialogTitle>
                    <DialogDescription>
                        Un nombramiento del ENS, por sistema y con vigencia. No se borra: al
                        revocarlo se le pone fecha de fin, porque el auditor pregunta «¿desde
                        cuándo?» y también «¿hasta cuándo?».
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoSelect
                        v-model="designacion.sistema_id"
                        nombre="sistema_id"
                        etiqueta="Sistema"
                        :opciones="sistemas"
                        :error="designacion.errors.sistema_id"
                        requerido
                    />

                    <CampoSelect
                        v-model="designacion.rol"
                        nombre="rol"
                        etiqueta="Rol"
                        :opciones="roles"
                        :error="designacion.errors.rol"
                        requerido
                    />

                    <Aviso v-if="avisoIncompatible" tono="error">
                        {{ avisoIncompatible }}
                    </Aviso>

                    <CampoTexto
                        v-model="designacion.desde"
                        nombre="desde"
                        etiqueta="Desde"
                        tipo="date"
                        :error="designacion.errors.desde"
                        ayuda="La fecha del nombramiento, que suele ser anterior al día que se apunta aquí. En blanco, hoy."
                    />

                    <CampoTextarea
                        v-model="designacion.nota"
                        nombre="nota"
                        etiqueta="Nota"
                        :filas="2"
                        :error="designacion.errors.nota"
                        ayuda="Dónde consta: «acta del comité del 3 de marzo»."
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="designando = false">Cancelar</Button>
                    <Button :disabled="designacion.processing" @click="designar">Designar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Registrar acuerdo -->
        <!--
            Cambiar de puesto CIERRA el anterior, no lo sustituye: eso lo hace
            `AsignarPuesto` en una transacción, porque entre las dos escrituras
            la persona estaría sin puesto.
        -->
        <Dialog v-model:open="asignandoPuesto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Asignar un puesto</DialogTitle>
                    <DialogDescription>
                        Si ya ocupaba otro, se cierra el día antes de empezar éste: dos
                        asignaciones que se solapan harían que «qué puesto tenía el 3 de marzo»
                        tuviera dos respuestas.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoSelect
                        nombre="puesto_id"
                        etiqueta="Puesto"
                        :opciones="puestos"
                        v-model="puestoForm.puesto_id"
                        :error="puestoForm.errors.puesto_id"
                        requerido
                    />

                    <CampoTexto
                        nombre="desde"
                        etiqueta="Desde"
                        tipo="date"
                        v-model="puestoForm.desde"
                        :error="puestoForm.errors.desde"
                        ayuda="Puede ser pasada: al meter el histórico, lo normal es registrar algo que empezó hace años."
                    />

                    <CampoTextarea
                        nombre="nota"
                        etiqueta="Nota"
                        :filas="2"
                        v-model="puestoForm.nota"
                        :error="puestoForm.errors.nota"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="asignandoPuesto = false">Cancelar</Button>
                    <Button :disabled="puestoForm.processing" @click="asignarPuesto">Asignar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="firmando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Acuerdo de confidencialidad</DialogTitle>
                    <DialogDescription>
                        <span class="cifra">mp.per.2</span>: los deberes y obligaciones de cada
                        persona tienen que constar por escrito.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoTexto
                        v-model="acuerdo.fecha_firma"
                        nombre="fecha_firma"
                        etiqueta="Fecha de firma"
                        tipo="date"
                        :error="acuerdo.errors.fecha_firma"
                        requerido
                    />

                    <CampoTexto
                        v-model="acuerdo.vigente_hasta"
                        nombre="vigente_hasta"
                        etiqueta="Vigente hasta"
                        tipo="date"
                        :error="acuerdo.errors.vigente_hasta"
                        ayuda="En blanco casi siempre: un acuerdo de confidencialidad no suele vencer."
                    />

                    <CampoSelect
                        v-model="acuerdo.evidencia_id"
                        nombre="evidencia_id"
                        etiqueta="Documento firmado"
                        :opciones="evidencias"
                        :error="acuerdo.errors.evidencia_id"
                        :placeholder="cargandoEvidencias ? 'Buscando evidencias…' : undefined"
                        ayuda="Una evidencia que ya esté en el repositorio. Sin ella, la medida está declarada y no probada — que es lo que un auditor separa."
                    />

                    <CampoTextarea
                        v-model="acuerdo.nota"
                        nombre="nota"
                        etiqueta="Nota"
                        :filas="2"
                        :error="acuerdo.errors.nota"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="firmando = false">Cancelar</Button>
                    <Button :disabled="acuerdo.processing" @click="guardarAcuerdo">Registrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
