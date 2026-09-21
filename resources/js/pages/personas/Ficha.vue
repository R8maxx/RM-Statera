<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

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
}

interface Paso {
    id: number | null;
    titulo: string;
    hecho: boolean;
    hechoEn: string | null;
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
    puedeGestionar: boolean;
    puedeDesignar: boolean;
    roles: OpcionRol[];
    sistemas: Opcion[];
    cuentas: Opcion[];
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

const vigentes = computed(() => props.designaciones.filter((item) => item.vigente));
const historicas = computed(() => props.designaciones.filter((item) => !item.vigente));

/* --- El acuerdo de confidencialidad: mp.per.2 --- */

const firmando = ref(false);

const acuerdo = useForm({ fecha_firma: '', vigente_hasta: '', nota: '' });

function abrirAcuerdo(): void {
    acuerdo.reset();
    acuerdo.clearErrors();
    acuerdo.fecha_firma = new Date().toISOString().slice(0, 10);
    firmando.value = true;
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
 * Se edita en local y se guarda la lista entera, como las subtareas de una
 * tarea: añadir, renombrar, marcar, reordenar y borrar son el mismo gesto en una
 * lista de comprobación, y el orden va implícito en la posición del array.
 */
const listas = ref<Record<string, Paso[]>>({});

watch(
    () => props.pasos,
    (valor) => {
        listas.value = Object.fromEntries(
            valor.map((lista) => [lista.tipo, lista.pasos.map((paso) => ({ ...paso }))]),
        );
    },
    { immediate: true, deep: true },
);

function anadirPaso(tipo: string): void {
    listas.value[tipo] = [...(listas.value[tipo] ?? []), { id: null, titulo: '', hecho: false, hechoEn: null }];
}

function quitarPaso(tipo: string, indice: number): void {
    listas.value[tipo] = (listas.value[tipo] ?? []).filter((_, i) => i !== indice);
}

function guardarLista(tipo: string): void {
    router.put(
        `/personas/${props.persona.id}/pasos`,
        {
            tipo,
            pasos: (listas.value[tipo] ?? []).map((paso) => ({
                id: paso.id,
                titulo: paso.titulo,
                hecho: paso.hecho,
            })),
        },
        { preserveScroll: true },
    );
}

function pendientesDe(tipo: string): number {
    return (listas.value[tipo] ?? []).filter((paso) => !paso.hecho && paso.titulo.trim() !== '').length;
}

/**
 * Lo que está escrito y todavía no se ha mandado.
 *
 * La lista se edita entera y se guarda entera, así que entre el primer clic y el
 * botón hay un rato en el que lo marcado sólo vive en el navegador. Sin decirlo,
 * salir de la pantalla lo tira y nada avisa. Aquí no se autoguarda como en la
 * lista de comprobación de una tarea —marcar un paso de la baja de alguien no es
 * un gesto suelto, es una salida que se cierra de una vez—, así que al menos se
 * dice.
 */
function huella(pasos: Paso[]): string {
    return JSON.stringify(pasos.map((paso) => [paso.id, paso.titulo, paso.hecho]));
}

function estaSucia(tipo: string): boolean {
    const original = props.pasos.find((lista) => lista.tipo === tipo)?.pasos ?? [];

    return huella(listas.value[tipo] ?? []) !== huella(original);
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
                            titulo="Sin nombramientos vigentes"
                            descripcion="Los roles ENS se designan por sistema, porque es donde la incompatibilidad significa algo."
                        />
                        <ul v-else class="divide-y divide-border">
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
                                <span class="cifra">{{ item.sistema }}</span>
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
                                    class="ml-auto"
                                    @click="revocar(item.id)"
                                >
                                    Revocar
                                </Button>
                            </li>
                        </ul>

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
                            <ul
                                v-show="revocadasALaVista"
                                id="nombramientos-revocados"
                                class="mt-2 divide-y divide-border"
                            >
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
                            titulo="Sin formación registrada"
                            descripcion="Se convoca desde la sesión, en Formación."
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
                        <ul class="space-y-2">
                            <li
                                v-for="(paso, indice) in listas[lista.tipo] ?? []"
                                :key="paso.id ?? `nuevo-${indice}`"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :model-value="paso.hecho"
                                    :disabled="!puedeGestionar"
                                    :aria-label="`Marcar «${paso.titulo}» como hecho`"
                                    @update:model-value="(valor) => (paso.hecho = valor === true)"
                                />
                                <Input
                                    v-model="paso.titulo"
                                    :disabled="!puedeGestionar"
                                    :aria-label="`Paso ${indice + 1}`"
                                    class="flex-1"
                                />
                                <span
                                    v-if="paso.hechoEn"
                                    class="shrink-0 text-xs text-muted-foreground"
                                >
                                    {{ paso.hechoEn }}
                                </span>
                                <Button
                                    v-if="puedeGestionar"
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="`Quitar el paso ${indice + 1}`"
                                    @click="quitarPaso(lista.tipo, indice)"
                                >
                                    Quitar
                                </Button>
                            </li>
                        </ul>

                        <p v-if="(listas[lista.tipo] ?? []).length === 0" class="text-sm text-muted-foreground">
                            Sin pasos. Se escriben una vez y valen para quien venga detrás.
                        </p>
                        <p v-else class="text-xs text-muted-foreground">
                            {{ pendientesDe(lista.tipo) }} sin marcar de
                            {{ (listas[lista.tipo] ?? []).length }}.
                        </p>

                        <div v-if="puedeGestionar" class="flex flex-wrap items-center gap-2">
                            <Button variant="outline" size="sm" @click="anadirPaso(lista.tipo)">
                                Añadir paso
                            </Button>
                            <Button
                                size="sm"
                                :variant="estaSucia(lista.tipo) ? 'default' : 'outline'"
                                @click="guardarLista(lista.tipo)"
                            >
                                Guardar
                            </Button>
                            <span
                                v-if="estaSucia(lista.tipo)"
                                class="text-xs font-medium text-estado-en-progreso"
                            >
                                Sin guardar
                            </span>
                        </div>
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
                            titulo="Sin acuerdo firmado"
                            descripcion="Los deberes tienen que constar por escrito: sin papel, la medida está declarada y no probada."
                        />
                        <ul v-else class="divide-y divide-border">
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
                                    class="ml-auto"
                                    @click="borrarAcuerdo(item.id)"
                                >
                                    Borrar
                                </Button>
                            </li>
                        </ul>

                        <Button v-if="puedeGestionar" variant="outline" size="sm" @click="abrirAcuerdo">
                            Registrar acuerdo
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Cuenta de Statera</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <p v-if="persona.usuario">
                            Vinculada a <span class="font-medium">{{ persona.usuario }}</span>.
                        </p>
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
