<script setup lang="ts">
import BotonEstado from '@/components/BotonEstado.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import AvisoNotificacion, { type Notificacion } from '@/components/incidente/AvisoNotificacion.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
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

interface Transicion {
    id: number;
    anterior: string | null;
    nuevo: string;
    tono: string;
    icono: string;
    usuario: string | null;
    fecha: string;
    nota: string | null;
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
 * **Lo primero que se ve, cuando aplica, es el reloj de la AEPD.** Es el único
 * plazo legal del producto que se mide en horas, y es el único rojo del módulo:
 * ni el estado ni la peligrosidad lo gastan.
 *
 * **Y la lección aprendida está en la misma tarjeta que el botón de cerrar**, a
 * propósito: es el paso que la norma pide y que todo el mundo se salta el día que
 * el servicio vuelve, así que el formulario y el gesto van juntos.
 */
const props = defineProps<{
    incidente: Incidente;
    activos: ActivoAfectado[];
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

function guardarLeccion(): void {
    router.put(
        `/incidentes/${props.incidente.id}/leccion`,
        { leccion_aprendida: leccion.leccion_aprendida },
        { preserveScroll: true },
    );
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
        <CabeceraPagina :titulo="incidente.codigo" :descripcion="incidente.titulo">
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
                    etiqueta: incidente.peligrosidadEtiqueta,
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
            <span class="text-sm text-muted-foreground">
                Detectado el {{ incidente.fechaDeteccionEtiqueta }}
                <template v-if="incidente.fechaInicioEtiqueta">
                    · empezó el {{ incidente.fechaInicioEtiqueta }}
                </template>
            </span>
        </div>

        <!--
            El único rojo del módulo, y arriba del todo cuando aplica: 72 h desde
            la detección, artículo 33.1 del RGPD.
        -->
        <div
            v-if="notificaciones.aepd.vencido && !notificaciones.aepd.notificado"
            class="rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-sm"
        >
            <p class="font-medium">Plazo de la AEPD vencido sin notificar.</p>
            <p class="text-muted-foreground">
                Pasaron las 72 horas que fija el artículo 33.1 del RGPD desde que se tuvo
                constancia. Notificar tarde sigue siendo mejor que no notificar, y la fecha real
                queda anotada tal cual.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Qué ha pasado</CardTitle>
                        <CardDescription v-if="incidente.dimensiones.length > 0">
                            Dimensiones afectadas: {{ incidente.dimensiones.join(', ') }}.
                        </CardDescription>
                        <CardDescription v-else>
                            Todavía no se ha declarado ninguna dimensión afectada.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <p class="whitespace-pre-line">{{ incidente.descripcion }}</p>

                        <div v-if="incidente.impacto">
                            <p class="font-medium">Impacto</p>
                            <p class="whitespace-pre-line text-muted-foreground">
                                {{ incidente.impacto }}
                            </p>
                        </div>

                        <div v-if="incidente.acciones_contencion">
                            <p class="font-medium">Acciones de contención</p>
                            <p class="whitespace-pre-line text-muted-foreground">
                                {{ incidente.acciones_contencion }}
                            </p>
                        </div>
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
                            <span class="cifra">op.exp.7</span> pide aprender del incidente, y sin
                            esto el mismo incidente se repite el año que viene. Es obligatorio para
                            cerrarlo.
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
                        />

                        <Button
                            v-if="puedeGestionar"
                            variant="outline"
                            size="sm"
                            @click="guardarLeccion"
                        >
                            Guardar
                        </Button>

                        <div v-if="disponibles.length > 0" class="flex flex-wrap gap-2 border-t pt-4">
                            <BotonEstado
                                v-for="paso in disponibles"
                                :key="paso.valor"
                                :destino="paso"
                                :deshabilitado="enviando || (paso.exigeLeccion && sinLeccion)"
                                @click="mover(paso)"
                            />
                        </div>

                        <p v-if="sinLeccion" class="text-xs text-muted-foreground">
                            Para cerrar el incidente hace falta escribir arriba qué se aprendió.
                        </p>

                        <div v-if="destino" class="space-y-2 border-t pt-4">
                            <CampoTexto
                                v-model="nota"
                                nombre="nota"
                                :etiqueta="`Por qué se vuelve a «${destino.etiqueta}»`"
                                ayuda="Reabrir algo que alguien dio por hecho necesita explicación: es lo único que explica el ir y venir."
                            />
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="destino = null">
                                    Cancelar
                                </Button>
                                <Button
                                    size="sm"
                                    :disabled="enviando || nota.trim() === ''"
                                    @click="mover(destino)"
                                >
                                    Confirmar
                                </Button>
                            </div>
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
                        <ul class="divide-y divide-border">
                            <li
                                v-for="paso in historial"
                                :key="paso.id"
                                class="flex flex-wrap items-center gap-2 py-2 text-sm"
                            >
                                <CeldaBadge
                                    :valor="{
                                        valor: paso.nuevo,
                                        etiqueta: paso.nuevo,
                                        tono: paso.tono,
                                        icono: paso.icono,
                                    }"
                                />
                                <span class="text-xs text-muted-foreground">
                                    {{ paso.fecha }}
                                    <template v-if="paso.usuario"> · {{ paso.usuario }}</template>
                                </span>
                                <span v-if="paso.nota" class="w-full text-muted-foreground">
                                    {{ paso.nota }}
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Notificación a supervisores</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <AvisoNotificacion
                            :notificacion="notificaciones.aepd"
                            :puede-gestionar="puedeGestionar"
                            @anotar="abrirNotificacion"
                        />
                        <AvisoNotificacion
                            :notificacion="notificaciones.ccnCert"
                            :puede-gestionar="puedeGestionar"
                            @anotar="abrirNotificacion"
                        />
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
                                <Button v-if="puedeMejorar" as-child variant="outline" size="sm">
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
                            titulo="Sin activos vinculados"
                            descripcion="Se vinculan al editar el incidente."
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
                                <Link
                                    :href="`/activos/${activo.id}`"
                                    class="underline underline-offset-4"
                                >
                                    {{ activo.nombre }}
                                </Link>
                                <span class="cifra text-xs text-muted-foreground">
                                    {{ activo.codigo }}
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card v-if="incidente.responsable || incidente.sistema || incidente.fechaCierre">
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-1 text-sm text-muted-foreground">
                        <p v-if="incidente.responsable">
                            Responsable: <span class="text-foreground">{{ incidente.responsable }}</span>
                        </p>
                        <p v-if="incidente.sistema">
                            Sistema: <span class="cifra text-foreground">{{ incidente.sistema }}</span>
                        </p>
                        <p v-if="incidente.fechaCierre">
                            Cerrado el <span class="text-foreground">{{ incidente.fechaCierre }}</span>
                        </p>
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
                    <Button :disabled="notificacion.processing" @click="anotarNotificacion">
                        Anotar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
