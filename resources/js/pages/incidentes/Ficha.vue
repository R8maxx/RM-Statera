<script setup lang="ts">
import BotonEstado from '@/components/BotonEstado.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import AvisoNotificacion, { type Notificacion } from '@/components/incidente/AvisoNotificacion.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import { CircleCheckIcon, InfoIcon, ServerIcon } from '@lucide/vue';
import { tono } from '@/lib/tonos';
import HistoricoTransiciones, {
    type Transicion,
} from '@/components/HistoricoTransiciones.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Destino {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
    exigeMotivo: boolean;
    exigeLeccion: boolean;
    permiso: string;
}

interface ActivoAfectado {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
    tipoTono: string;
    tipoIcono: string;
}

interface Dimension {
    clave: string;
    etiqueta: string;
    afectada: boolean;
}

interface PasoCiclo {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
}

interface Incidente {
    id: number;
    codigo: string;
    titulo: string;
    descripcion: string;
    sistema: string | null;
    clasificacionEtiqueta: string;
    clasificacionTono: string;
    clasificacionIcono: string;
    peligrosidad: string;
    peligrosidadEtiqueta: string;
    peligrosidadTono: string;
    peligrosidadIcono: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    fechaDeteccionEtiqueta: string;
    fechaInicioEtiqueta: string | null;
    fechaCierre: string | null;
    dimensiones: string[];
    impacto: string | null;
    acciones_contencion: string | null;
    leccion_aprendida: string | null;
    responsable: string | null;
}

/**
 * La ficha de un incidente: § 4.10 y `op.exp.7`.
 *
 * **Lo primero que se ve es la tarjeta de plazos, a lo ancho.** El reloj de la
 * AEPD es el único plazo legal del producto que se mide en horas, y es el único
 * rojo del módulo: ni el estado ni la peligrosidad lo gastan. Va dibujado como
 * barra y con su botón al lado, que es el primario de la pantalla mientras corre.
 * El CCN-CERT comparte tarjeta y no tiene barra, a propósito.
 *
 * **La columna lateral empieza por «Estado»**, como el resto de fichas (§ 9):
 * el ciclo entero a la vista y los pasos que avanzan o retroceden. **Cerrar no
 * está ahí**: va en la tarjeta de la lección aprendida, que es el paso que la
 * norma pide y que todo el mundo se salta el día que el servicio vuelve, así que
 * el formulario y el gesto siguen juntos.
 */
const props = defineProps<{
    incidente: Incidente;
    activos: ActivoAfectado[];
    dimensiones: Dimension[];
    duracion: { etiqueta: string; valor: string };
    ciclo: PasoCiclo[];
    estadoDesde: string | null;
    notificaciones: { aepd: Notificacion; ccnCert: Notificacion };
    noConformidad: { id: number; codigo: string; estado: string; tono: string; icono: string } | null;
    transiciones: Destino[];
    historial: Transicion[];
    puedeGestionar: boolean;
    puedeTratar: boolean;
    puedeMejorar: boolean;
}>();

/* --- El ciclo --- */

const destino = ref<Destino | null>(null);
const nota = ref('');
const enviando = ref(false);

const disponibles = computed(() => (props.puedeGestionar ? props.transiciones : []));

const sinLeccion = computed(() => (props.incidente.leccion_aprendida ?? '').trim() === '');

/** Los pasos de la tarjeta «Estado»: todos menos cerrar, que va con la lección. */
const pasos = computed(() => disponibles.value.filter((paso) => !paso.exigeLeccion));

/** Cerrar, cuando el estado lo admite. */
const cierre = computed(() => disponibles.value.find((paso) => paso.exigeLeccion) ?? null);

const cerrado = computed(() => props.incidente.estado === 'cerrado');

/**
 * Lo que falta para poder cerrar, dicho en positivo y con su marca: pasar a
 * resuelto y escribir la lección. Sustituye a la frase suelta de antes, que sólo
 * decía la mitad.
 */
const requisitosCierre = computed(() => [
    { clave: 'resuelto', etiqueta: 'Pasar a resuelto', hecho: props.incidente.estado === 'resuelto' },
    { clave: 'leccion', etiqueta: 'Escribir la lección', hecho: !sinLeccion.value },
]);

const faltanParaCerrar = computed(() => requisitosCierre.value.filter((uno) => !uno.hecho).length);

/** El ciclo pintado como pasos: hechos, el actual y los que quedan. */
const posicion = computed(() => props.ciclo.findIndex((paso) => paso.valor === props.incidente.estado));

const tonoActual = computed(() => tono(props.incidente.estadoTono));

const formatoDesde = new Intl.DateTimeFormat('es-ES', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
});

const desde = computed(() => (props.estadoDesde ? formatoDesde.format(new Date(props.estadoDesde)) : null));

/**
 * Lo que un auditor va a pedir y no está. Neutro y sólo cuando falta, como la
 * tira de la ficha de activo (§ 9): que falte un dato no es que algo vaya mal.
 */
const pendientes = computed<string[]>(() =>
    [
        props.incidente.responsable === null ? 'Responsable sin asignar' : null,
        props.incidente.fechaInicioEtiqueta === null ? 'Cuándo empezó' : null,
        props.incidente.dimensiones.length === 0 ? 'Dimensiones afectadas' : null,
        props.activos.length === 0 ? 'Activos afectados' : null,
    ].filter((uno): uno is string => uno !== null),
);

/** La AEPD vencida sin notificar tiñe el borde de la tarjeta: el único rojo. */
const aepdVencida = computed(() => props.notificaciones.aepd.vencido && !props.notificaciones.aepd.notificado);

/** El primario de la pantalla es anotar la AEPD mientras su reloj corre. */
const aepdPrimaria = computed(() => props.notificaciones.aepd.notificable && !props.notificaciones.aepd.notificado);

function mover(paso: Destino): void {
    // Cerrar sin lección aprendida lo rechaza el dominio; decirlo aquí antes de
    // enviar se explica mucho mejor que un error después.
    if (paso.exigeLeccion && sinLeccion.value) {
        return;
    }

    if (paso.exigeMotivo && destino.value?.valor !== paso.valor) {
        destino.value = paso;
        nota.value = '';

        return;
    }

    enviando.value = true;

    router.post(
        `/incidentes/${props.incidente.id}/estado`,
        { estado: paso.valor, nota: nota.value },
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
                destino.value = null;
            },
        },
    );
}

/* --- La lección aprendida --- */

const leccion = useForm({ leccion_aprendida: props.incidente.leccion_aprendida ?? '' });

/**
 * Con el propio formulario y no con `router.put`: el `:error` del campo lee
 * `leccion.errors`, y mandándolo por el router esa rama no se rellenaba nunca
 * —un fallo de validación se perdía en silencio— ni había estado de envío con
 * el que impedir el doble clic.
 */
function guardarLeccion(): void {
    leccion.put(`/incidentes/${props.incidente.id}/leccion`, { preserveScroll: true });
}

/* --- Las notificaciones --- */

const anotando = ref<string | null>(null);

const notificacion = useForm({ destinatario: '', notificado_en: '', nota: '' });

function abrirNotificacion(destinatario: string): void {
    notificacion.reset();
    notificacion.clearErrors();
    notificacion.destinatario = destinatario;
    anotando.value = destinatario;
}

function anotarNotificacion(): void {
    notificacion.post(`/incidentes/${props.incidente.id}/notificaciones`, {
        preserveScroll: true,
        onSuccess: () => {
            anotando.value = null;
            notificacion.reset();
        },
    });
}
</script>

<template>
    <AppLayout :titulo="incidente.codigo">
        <CabeceraPagina :titulo="incidente.titulo" :codigo="incidente.codigo">
            <template #acciones>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/incidentes/${incidente.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2">
            <CeldaBadge
                :valor="{
                    valor: incidente.estado,
                    etiqueta: incidente.estadoEtiqueta,
                    tono: incidente.estadoTono,
                    icono: incidente.estadoIcono,
                }"
            />
            <CeldaBadge
                :valor="{
                    valor: incidente.peligrosidad,
                    etiqueta: `Peligrosidad ${incidente.peligrosidadEtiqueta.toLowerCase()}`,
                    tono: incidente.peligrosidadTono,
                    icono: incidente.peligrosidadIcono,
                }"
            />
            <CeldaBadge
                :valor="{
                    valor: 'clasificacion',
                    etiqueta: incidente.clasificacionEtiqueta,
                    tono: incidente.clasificacionTono,
                    icono: incidente.clasificacionIcono,
                }"
            />
        </div>

        <!-- Lo que falta: neutro y sólo cuando falta (§ 9, «Fichas»). -->
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
                <li v-for="pendiente in pendientes" :key="pendiente">
                    <Link
                        v-if="puedeGestionar"
                        :href="`/incidentes/${incidente.id}/editar`"
                        class="inline-flex h-7 items-center rounded-full bg-muted px-2.5 text-[13px] font-medium text-secondary-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                    >
                        {{ pendiente }}
                    </Link>
                    <span
                        v-else
                        class="inline-flex h-7 items-center rounded-full bg-muted px-2.5 text-[13px] font-medium text-secondary-foreground"
                    >
                        {{ pendiente }}
                    </span>
                </li>
            </ul>
        </section>

        <!--
            Los plazos, a lo ancho y antes que nada. El borde se tiñe sólo con la
            AEPD vencida sin notificar: 72 h desde la detección, artículo 33.1 del
            RGPD, y el único rojo del módulo.
        -->
        <Card :class="aepdVencida ? 'ring-destructive/40' : undefined">
            <CardHeader>
                <CardTitle>Notificación a supervisores</CardTitle>
                <CardDescription>Sólo hay cuenta atrás donde la ley pone un número.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-5">
                <AvisoNotificacion
                    :notificacion="notificaciones.aepd"
                    :puede-gestionar="puedeGestionar"
                    :inicio-conocido="incidente.fechaInicioEtiqueta !== null"
                    :primaria="aepdPrimaria"
                    @anotar="abrirNotificacion"
                />
                <div class="border-t" />
                <AvisoNotificacion
                    :notificacion="notificaciones.ccnCert"
                    :puede-gestionar="puedeGestionar"
                    :inicio-conocido="incidente.fechaInicioEtiqueta !== null"
                    @anotar="abrirNotificacion"
                />
            </CardContent>
        </Card>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Qué ha pasado</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <p class="max-w-prose text-base text-pretty whitespace-pre-line">
                            {{ incidente.descripcion }}
                        </p>

                        <!--
                            Las cinco dimensiones, afectadas o no: «qué se vio
                            afectado» se contesta viendo también lo que no. La
                            afectada lleva relleno, icono y lo dice en texto para
                            el lector de pantalla; el color no va solo.
                        -->
                        <div class="space-y-2">
                            <h3 class="text-[13px] font-medium text-muted-foreground">Dimensiones afectadas</h3>
                            <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5">
                                <li
                                    v-for="dimension in dimensiones"
                                    :key="dimension.clave"
                                    class="flex h-10 items-center gap-2 rounded-md px-3 text-[13px]"
                                    :class="
                                        dimension.afectada
                                            ? 'bg-foreground font-medium text-background'
                                            : 'text-muted-foreground ring-1 ring-border ring-inset'
                                    "
                                >
                                    <CircleCheckIcon v-if="dimension.afectada" class="size-4 shrink-0" aria-hidden="true" />
                                    <span class="truncate">{{ dimension.etiqueta }}</span>
                                    <span class="sr-only">{{ dimension.afectada ? ': afectada' : ': no afectada' }}</span>
                                </li>
                            </ul>
                        </div>

                        <!--
                            Pares dato/valor en `<dl>`, como las fichas de
                            activo, riesgo y no conformidad. Un rótulo en
                            negrita dentro de un `<p>` se lee igual y no es un
                            rótulo: para un lector de pantalla no hay relación
                            entre la etiqueta y lo que describe.
                        -->
                        <dl
                            v-if="incidente.impacto || incidente.acciones_contencion"
                            class="grid gap-4 border-t pt-5 text-sm sm:grid-cols-2"
                        >
                            <div v-if="incidente.impacto" class="space-y-1">
                                <dt class="text-[13px] font-medium text-muted-foreground">Impacto</dt>
                                <dd class="whitespace-pre-line">{{ incidente.impacto }}</dd>
                            </div>

                            <div v-if="incidente.acciones_contencion" class="space-y-1">
                                <dt class="text-[13px] font-medium text-muted-foreground">Acciones de contención</dt>
                                <dd class="whitespace-pre-line">{{ incidente.acciones_contencion }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <!--
                    La lección aprendida y el botón de cerrar, juntos: es el paso
                    que op.exp.7 pide y el que todo el mundo se salta.
                -->
                <Card>
                    <CardHeader>
                        <CardTitle>Qué se aprendió</CardTitle>
                        <CardDescription>
                            <span class="cifra">op.exp.7</span> pide aprender del incidente, y sin esto el mismo
                            incidente se repite el año que viene. Se escribe mientras se resuelve, a trozos.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <CampoTextarea
                            v-model="leccion.leccion_aprendida"
                            nombre="leccion_aprendida"
                            etiqueta="Lección aprendida"
                            :filas="4"
                            :deshabilitado="!puedeGestionar"
                            :error="leccion.errors.leccion_aprendida"
                            ayuda="Obligatoria para cerrar el incidente."
                        />

                        <Button
                            v-if="puedeGestionar"
                            variant="outline"
                            size="sm"
                            :disabled="leccion.processing"
                            @click="guardarLeccion"
                        >
                            Guardar la lección
                        </Button>

                        <!--
                            Lo que falta para cerrar, con su marca, y el botón al
                            lado: deshabilitado mientras falte algo, y diciendo
                            qué. No se pinta en un incidente ya cerrado ni a quien
                            no puede gestionarlo.
                        -->
                        <div
                            v-if="puedeGestionar && !cerrado"
                            class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-superficie px-4 py-3.5"
                        >
                            <div class="space-y-1.5">
                                <p class="text-[13px] font-semibold">
                                    <template v-if="faltanParaCerrar === 0">Listo para cerrar</template>
                                    <template v-else-if="faltanParaCerrar === 1">Para cerrar falta una cosa</template>
                                    <template v-else>Para cerrar faltan dos cosas</template>
                                </p>
                                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-[13px] text-muted-foreground">
                                    <li
                                        v-for="requisito in requisitosCierre"
                                        :key="requisito.clave"
                                        class="flex items-center gap-1.5"
                                        :class="{ 'text-foreground': requisito.hecho }"
                                    >
                                        <CircleCheckIcon
                                            v-if="requisito.hecho"
                                            class="size-3.5 text-estado-implantado"
                                            aria-hidden="true"
                                        />
                                        <span
                                            v-else
                                            class="size-3.5 rounded-full ring-2 ring-border ring-inset"
                                            aria-hidden="true"
                                        />
                                        {{ requisito.etiqueta }}
                                        <span class="sr-only">{{ requisito.hecho ? '(hecho)' : '(pendiente)' }}</span>
                                    </li>
                                </ul>
                            </div>

                            <BotonEstado
                                v-if="cierre"
                                :destino="cierre"
                                :deshabilitado="enviando || sinLeccion"
                                @click="mover(cierre)"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Histórico</CardTitle>
                        <CardDescription>
                            La pregunta del auditor no es «¿está cerrado?», es «¿cuánto se tardó en
                            contenerlo?».
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <HistoricoTransiciones :transiciones="historial" />
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <!--
                    «Estado» arriba, como en el resto de fichas (§ 9): el ciclo
                    entero a la vista y los pasos que se pueden dar. Cerrar vive
                    con la lección.
                -->
                <Card>
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <ol class="space-y-0">
                            <li
                                v-for="(paso, indice) in ciclo"
                                :key="paso.valor"
                                class="flex gap-3"
                                :aria-current="indice === posicion ? 'step' : undefined"
                            >
                                <div class="flex flex-col items-center">
                                    <CircleCheckIcon
                                        v-if="indice < posicion || (indice === posicion && cerrado)"
                                        class="size-5 shrink-0 text-estado-implantado"
                                        aria-hidden="true"
                                    />
                                    <span
                                        v-else-if="indice === posicion"
                                        class="flex size-5 shrink-0 items-center justify-center rounded-full"
                                        :class="tonoActual.badge"
                                        aria-hidden="true"
                                    >
                                        <IconoTipo :nombre="paso.icono" clase="size-3" />
                                    </span>
                                    <span
                                        v-else
                                        class="size-5 shrink-0 rounded-full ring-2 ring-border ring-inset"
                                        aria-hidden="true"
                                    />
                                    <span
                                        v-if="indice < ciclo.length - 1"
                                        class="my-1 min-h-3 w-0.5 flex-1 rounded-full"
                                        :class="indice < posicion ? 'bg-estado-implantado/40' : 'bg-border'"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div class="pb-3 text-sm">
                                    <p
                                        :class="
                                            indice === posicion
                                                ? ['font-semibold', tonoActual.texto]
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ paso.etiqueta }}
                                    </p>
                                    <p v-if="indice === posicion && desde" class="text-xs text-muted-foreground">
                                        desde el <span class="cifra">{{ desde }}</span>
                                    </p>
                                </div>
                            </li>
                        </ol>

                        <div v-if="pasos.length > 0" class="flex flex-wrap gap-2 border-t pt-4">
                            <BotonEstado
                                v-for="paso in pasos"
                                :key="paso.valor"
                                :destino="paso"
                                :deshabilitado="enviando"
                                @click="mover(paso)"
                            />
                        </div>

                        <div v-if="destino" class="space-y-2 border-t pt-4">
                            <CampoTexto
                                v-model="nota"
                                nombre="nota"
                                :etiqueta="`Por qué se vuelve a «${destino.etiqueta}»`"
                                ayuda="Reabrir algo que alguien dio por hecho necesita explicación: es lo único que explica el ir y venir."
                            />
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="destino = null">Cancelar</Button>
                                <Button size="sm" :disabled="enviando || nota.trim() === ''" @click="mover(destino)">
                                    Confirmar
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <dl class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-2.5">
                            <dt class="text-muted-foreground">Responsable</dt>
                            <dd :class="{ 'text-muted-foreground': !incidente.responsable }">
                                {{ incidente.responsable ?? 'Sin asignar' }}
                            </dd>
                            <template v-if="incidente.sistema">
                                <dt class="text-muted-foreground">Sistema</dt>
                                <dd class="cifra">{{ incidente.sistema }}</dd>
                            </template>
                            <dt class="text-muted-foreground">Empezó</dt>
                            <dd :class="incidente.fechaInicioEtiqueta ? 'cifra' : 'text-muted-foreground'">
                                {{ incidente.fechaInicioEtiqueta ?? 'Sin determinar' }}
                            </dd>
                            <dt class="text-muted-foreground">Detectado</dt>
                            <dd class="cifra">{{ incidente.fechaDeteccionEtiqueta }}</dd>
                            <template v-if="incidente.fechaCierre">
                                <dt class="text-muted-foreground">Cerrado el</dt>
                                <dd class="cifra">{{ incidente.fechaCierre }}</dd>
                            </template>
                            <dt class="text-muted-foreground">{{ duracion.etiqueta }}</dt>
                            <dd class="cifra">{{ duracion.valor }}</dd>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Tratamiento</CardTitle>
                        <CardDescription>
                            No todo incidente abre una no conformidad: sólo el que incumple algo.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div v-if="noConformidad" class="flex flex-wrap items-center gap-2">
                            <CeldaBadge
                                :valor="{
                                    valor: noConformidad.codigo,
                                    etiqueta: noConformidad.estado,
                                    tono: noConformidad.tono,
                                    icono: noConformidad.icono,
                                }"
                            />
                            <Link
                                :href="`/no-conformidades/${noConformidad.id}`"
                                class="cifra underline underline-offset-4"
                            >
                                {{ noConformidad.codigo }}
                            </Link>
                        </div>
                        <template v-else>
                            <p class="text-muted-foreground">
                                Sin no conformidad detrás. Si el incidente destapó un incumplimiento,
                                ábrela; si sólo deja algo que se puede hacer mejor, apúntalo como
                                oportunidad de mejora.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <Button v-if="puedeTratar" as-child variant="outline" size="sm">
                                    <Link :href="`/no-conformidades/crear?incidente=${incidente.id}`">
                                        Abrir no conformidad
                                    </Link>
                                </Button>
                                <Button v-if="puedeMejorar" as-child variant="ghost" size="sm">
                                    <Link :href="`/mejoras/crear?incidente=${incidente.id}`">
                                        Apuntar una mejora
                                    </Link>
                                </Button>
                            </div>
                        </template>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Activos afectados</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <EstadoVacio
                            v-if="activos.length === 0"
                            :icono="ServerIcon"
                            titulo="Sin activos vinculados"
                            descripcion="Qué se vio afectado es la primera pregunta de un informe de incidente."
                            :accion="
                                puedeGestionar
                                    ? { etiqueta: 'Vincularlos', href: `/incidentes/${incidente.id}/editar` }
                                    : undefined
                            "
                        />
                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="activo in activos"
                                :key="activo.id"
                                class="flex flex-wrap items-center gap-2 py-2 text-sm"
                            >
                                <CeldaBadge
                                    :valor="{
                                        valor: activo.tipo,
                                        etiqueta: activo.tipo,
                                        tono: activo.tipoTono,
                                        icono: activo.tipoIcono,
                                    }"
                                />
                                <Link :href="`/activos/${activo.id}`" class="underline underline-offset-4">
                                    {{ activo.nombre }}
                                </Link>
                                <span class="cifra text-xs text-muted-foreground">{{ activo.codigo }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog :open="anotando !== null" @update:open="(abierto) => (anotando = abierto ? anotando : null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Anotar la notificación
                        {{ anotando === 'aepd' ? 'a la AEPD' : 'al CCN-CERT' }}
                    </DialogTitle>
                    <DialogDescription>
                        La fecha se escribe, no se impone: la notificación se hace en la sede del
                        supervisor y se apunta aquí después. En un incidente fuera de plazo, la
                        fecha real es lo que decide si hubo incumplimiento.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <input type="hidden" name="destinatario" :value="notificacion.destinatario" />

                    <CampoTexto
                        v-model="notificacion.notificado_en"
                        nombre="notificado_en"
                        etiqueta="Notificado el"
                        tipo="datetime-local"
                        :error="notificacion.errors.notificado_en"
                        ayuda="En blanco, ahora mismo."
                    />

                    <CampoTextarea
                        v-model="notificacion.nota"
                        nombre="nota"
                        etiqueta="Nota"
                        :filas="2"
                        :error="notificacion.errors.nota"
                        ayuda="Número de registro del justificante, por ejemplo."
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="anotando = null">Cancelar</Button>
                    <Button :disabled="notificacion.processing" @click="anotarNotificacion">Anotar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
