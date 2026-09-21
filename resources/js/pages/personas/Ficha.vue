<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import ListaComprobacion, { type Paso } from '@/components/tarea/ListaComprobacion.vue';
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
import { useDesplegable } from '@/composables/useDesplegable';
import AppLayout from '@/layouts/AppLayout.vue';
import { conOpcionVacia, SIN_VALOR } from '@/lib/formularios';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    ChevronRightIcon,
    FileSignatureIcon,
    GraduationCapIcon,
    UserCheckIcon,
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
const errorPasos = ref<string | null>(null);

function guardarLista(tipo: string, pasos: Paso[]): void {
    guardando.value = { ...guardando.value, [tipo]: true };
    errorPasos.value = null;

    router.put(
        `/personas/${props.persona.id}/pasos`,
        {
            tipo,
            pasos: pasos.map((paso) => ({ id: paso.id, titulo: paso.titulo, hecho: paso.hecho })),
        },
        {
            preserveScroll: true,
            onError: (errores) => {
                errorPasos.value =
                    Object.values(errores)[0] ??
                    'No se ha podido guardar la checklist. Revisa los pasos e inténtalo otra vez.';
            },
            onFinish: () => (guardando.value = { ...guardando.value, [tipo]: false }),
        },
    );
}

</script>

<template>
    <AppLayout :titulo="persona.nombre">
        <CabeceraPagina :titulo="persona.nombre" :descripcion="persona.puesto ?? persona.codigo">
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/personas/${persona.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: persona.activa ? 'activa' : 'baja',
                    etiqueta: persona.estadoEtiqueta,
                    tono: persona.estadoTono,
                    icono: persona.estadoIcono,
                }"
            />
            <span class="cifra text-sm text-muted-foreground">{{ persona.codigo }}</span>
            <span class="text-sm text-muted-foreground">
                Desde el {{ persona.fecha_alta }}
                <template v-if="persona.fecha_baja"> hasta el {{ persona.fecha_baja }}</template>
            </span>
            <span v-if="persona.email" class="text-sm text-muted-foreground">{{ persona.email }}</span>
        </div>

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

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <!-- Cláusula 5.3 -->
                <Card>
                    <CardHeader>
                        <CardTitle>Nombramientos ENS</CardTitle>
                        <CardDescription>
                            La cláusula 5.3. El responsable de seguridad y el responsable del
                            sistema no pueden ser la misma persona en el mismo sistema: quien
                            decide qué protección hace falta no puede ser quien responde de
                            haberla puesto.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <EstadoVacio
                            v-if="vigentes.length === 0"
                            :icono="UserCheckIcon"
                            titulo="Sin nombramientos vigentes"
                            descripcion="Los roles ENS se designan por sistema, porque es donde la incompatibilidad significa algo."
                        />
                        <TransitionGroup v-else tag="ul" name="paso" class="divide-y divide-border">
                            <li
                                v-for="item in vigentes"
                                :key="item.id"
                                class="flex flex-wrap items-center gap-2 py-2 text-sm"
                            >
                                <CeldaBadge
                                    :valor="{
                                        valor: item.rol,
                                        etiqueta: item.rolEtiqueta,
                                        tono: item.rolTono,
                                        icono: item.rolIcono,
                                    }"
                                />
                                <span class="cifra" :title="item.sistemaNombre ?? undefined">
                                    {{ item.sistema }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    desde el {{ item.desde }}
                                    <template v-if="item.designadaPor">
                                        · designada por {{ item.designadaPor }}
                                    </template>
                                </span>
                                <Button
                                    v-if="puedeDesignar"
                                    variant="ghost"
                                    size="sm"
                                    @click="revocar(item.id)"
                                >
                                    Revocar
                                </Button>

                                <!--
                                    Dónde consta el nombramiento —«acta del
                                    comité del 3 de marzo»—. El diálogo lo pide
                                    con esas palabras y no se pintaba en ningún
                                    sitio, que es lo mismo que no haberlo
                                    escrito.
                                -->
                                <span v-if="item.nota" class="w-full text-xs text-muted-foreground">
                                    {{ item.nota }}
                                </span>
                            </li>
                        </TransitionGroup>

                        <Button v-if="puedeDesignar" variant="outline" @click="abrirDesignacion">
                            Designar en un rol
                        </Button>

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
                                            <span class="text-xs">{{ item.desde }} – {{ item.hasta }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- mp.per.3 y mp.per.4 -->
                <Card>
                    <CardHeader>
                        <CardTitle>Formación y concienciación</CardTitle>
                        <CardDescription>
                            <span class="cifra">mp.per.4</span> y
                            <span class="cifra">mp.per.3</span>. Se registra desde la sesión, no
                            desde aquí: lo que se apunta es una convocatoria con sus asistentes.
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
                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="item in formacion"
                                :key="item.id"
                                class="flex flex-wrap items-center gap-2 py-2 text-sm"
                            >
                                <CeldaBadge
                                    :valor="{
                                        valor: item.asistio ? 'asistio' : 'falto',
                                        etiqueta: item.asistio ? 'Asistió' : 'No asistió',
                                        tono: item.asistio ? 'implantado' : 'no_iniciado',
                                        icono: item.asistio ? 'Check' : 'Minus',
                                    }"
                                />
                                <Link
                                    :href="`/formacion/${item.accion_formativa_id}`"
                                    class="underline underline-offset-4"
                                >
                                    {{ item.titulo }}
                                </Link>
                                <!--
                                    Concienciar y formar son dos medidas
                                    distintas —`mp.per.3` y `mp.per.4`—, y el
                                    servidor ya mandaba su tono y su icono: en
                                    texto plano se leían como una coletilla de
                                    la fecha.
                                -->
                                <CeldaBadge
                                    v-if="item.tipo"
                                    :valor="{
                                        valor: item.tipo,
                                        etiqueta: item.tipo,
                                        tono: item.tipoTono,
                                        icono: item.tipoIcono,
                                    }"
                                />
                                <span class="text-xs text-muted-foreground">
                                    {{ item.fecha }} ·
                                    <span class="cifra">{{ item.medida }}</span>
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Las dos checklists -->
                <Card v-for="lista in pasos" :key="lista.tipo">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <IconoTipo :nombre="lista.icono" />
                            Checklist de {{ lista.etiqueta.toLowerCase() }}
                        </CardTitle>
                        <CardDescription v-if="lista.tipo === 'baja'">
                            La que el auditor mira: un acceso que nadie revocó es el hallazgo
                            clásico. Marcar todos los pasos no da de baja a nadie — eso es una
                            fecha, y se pone al editar la persona.
                        </CardDescription>
                        <CardDescription v-else>
                            Lo que hay que hacer al incorporarse: entregar el equipo, firmar el
                            acuerdo, dar de alta las cuentas.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Aviso v-if="errorPasos" tono="error">{{ errorPasos }}</Aviso>

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
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <!-- mp.per.2 -->
                <Card>
                    <CardHeader>
                        <CardTitle>Acuerdos de confidencialidad</CardTitle>
                        <CardDescription>
                            <span class="cifra">mp.per.2</span>. Sin fecha de caducidad es lo
                            normal: el deber sobrevive a la relación laboral.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <EstadoVacio
                            v-if="acuerdos.length === 0"
                            :icono="FileSignatureIcon"
                            titulo="Sin acuerdo firmado"
                            descripcion="Los deberes tienen que constar por escrito: sin papel, la medida está declarada y no probada."
                        />
                        <TransitionGroup v-else tag="ul" name="paso" class="divide-y divide-border">
                            <li
                                v-for="item in acuerdos"
                                :key="item.id"
                                class="flex flex-wrap items-center gap-2 py-2 text-sm"
                            >
                                <CeldaBadge
                                    :valor="{
                                        valor: item.vigente ? 'vigente' : 'caducado',
                                        etiqueta: item.vigente ? 'Vigente' : 'Caducado',
                                        tono: item.vigente ? 'implantado' : 'no_iniciado',
                                        icono: item.vigente ? 'FileCheck' : 'FileX',
                                    }"
                                />
                                <span>Firmado el {{ item.fecha_firma }}</span>
                                <span v-if="item.vigente_hasta" class="text-xs text-muted-foreground">
                                    hasta el {{ item.vigente_hasta }}
                                </span>
                                <Button
                                    v-if="puedeGestionar"
                                    variant="ghost"
                                    size="sm"
                                    @click="borrarAcuerdo(item.id)"
                                >
                                    Borrar
                                </Button>

                                <!--
                                    El documento y la nota se escribían y no se
                                    pintaban en ninguna parte. Son las dos cosas
                                    que un auditor pide al lado de una firma:
                                    dónde consta y qué papel lo prueba.
                                -->
                                <Link
                                    v-if="item.evidencia_id"
                                    :href="`/evidencias/${item.evidencia_id}`"
                                    class="w-full text-xs underline underline-offset-4"
                                >
                                    {{ item.evidencia }}
                                </Link>
                                <span v-if="item.nota" class="w-full text-xs text-muted-foreground">
                                    {{ item.nota }}
                                </span>
                            </li>
                        </TransitionGroup>

                        <Button v-if="puedeGestionar" variant="outline" size="sm" @click="abrirAcuerdo">
                            Registrar acuerdo
                        </Button>
                    </CardContent>
                </Card>

                <!--
                    Los datos personales van en la columna lateral y no en la
                    cabecera: identifican y localizan a quien figura en un
                    nombramiento, pero no son lo que se viene a mirar a esta
                    ficha. Si no hay ninguno, la tarjeta no se pinta — un bloque
                    con cinco guiones no dice nada que su ausencia no diga.
                -->
                <Card v-if="hayDatosDeContacto">
                    <CardHeader>
                        <CardTitle>Identificación y contacto</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <dl class="grid gap-2">
                            <div v-if="persona.nif" class="grid gap-1">
                                <dt class="text-muted-foreground">NIF o documento</dt>
                                <dd class="cifra font-medium">{{ persona.nif }}</dd>
                            </div>
                            <div v-if="persona.fecha_nacimiento" class="grid gap-1">
                                <dt class="text-muted-foreground">Fecha de nacimiento</dt>
                                <dd class="font-medium">{{ persona.fecha_nacimiento }}</dd>
                            </div>
                            <div v-if="persona.telefono" class="grid gap-1">
                                <dt class="text-muted-foreground">Teléfono</dt>
                                <dd class="font-medium">{{ persona.telefono }}</dd>
                            </div>
                            <div v-if="persona.telefono_fijo" class="grid gap-1">
                                <dt class="text-muted-foreground">Teléfono fijo</dt>
                                <dd class="font-medium">{{ persona.telefono_fijo }}</dd>
                            </div>
                            <div v-if="persona.direccion" class="grid gap-1">
                                <dt class="text-muted-foreground">Dirección</dt>
                                <dd class="font-medium whitespace-pre-line">{{ persona.direccion }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Cuenta de Statera</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <!--
                            Par dato/valor en `<dl>`, como las fichas de activo,
                            riesgo, indicador y objetivo. Un rótulo en negrita
                            dentro de un `<p>` se lee igual y no es un rótulo.
                        -->
                        <dl v-if="persona.usuario" class="grid gap-1">
                            <dt class="text-muted-foreground">Cuenta vinculada</dt>
                            <dd class="font-medium">{{ persona.usuario }}</dd>
                        </dl>
                        <p v-else class="text-muted-foreground">
                            Sin cuenta, que es lo normal: la mayoría de una plantilla no entra
                            nunca en la herramienta. Sin cuenta no se le pueden asignar tareas ni
                            firmar el acuse de lectura de un documento.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>

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
