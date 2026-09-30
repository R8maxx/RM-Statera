<script setup lang="ts">
import BotonEstado from '@/components/BotonEstado.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import Cifra from '@/components/Cifra.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTexto from '@/components/formulario/CampoTexto.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import EntradasRevision from '@/components/revision/EntradasRevision.vue';
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
import { tono } from '@/lib/tonos';
import { BadgeCheckIcon, FileTextIcon, InfoIcon, LockIcon, PencilLineIcon, PlusIcon } from '@lucide/vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Opcion {
    valor: string;
    etiqueta: string;
}

interface Destino {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
}

interface Decision {
    id: number;
    titulo: string;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    responsable: string | null;
    plazoEtiqueta: string;
    plazoTono: string;
    fecha: string | null;
    coste: string | null;
}

interface Revision {
    id: number;
    codigo: string;
    fecha: string;
    fechaLarga: string;
    periodo: string;
    asistentes: string | null;
    conclusiones: string | null;
    estado: string;
    estadoEtiqueta: string;
    estadoTono: string;
    estadoIcono: string;
    admiteCambios: boolean;
    aprobadaPor: string | null;
    aprobadaEn: string | null;
}

/**
 * La ficha de una revisión por la dirección: § 4.15 y la cláusula 9.3.
 *
 * **Las entradas se enseñan en vivo mientras la revisión está abierta, y
 * congeladas cuando el acta está firmada.** Es la distinción que hace el módulo:
 * antes de firmar, lo que se mira es cómo está la cosa hoy —que es para lo que se
 * convoca la reunión—; después, lo que se mira es lo que se revisó aquel día.
 * Mezclar las dos haría que el acta cambiara sola. Por eso la distinción va en una
 * tira bajo la cabecera y no en la letra pequeña de una tarjeta: es lo primero que
 * hay que saber al leer cualquier cifra de la pantalla.
 *
 * **Aprobar pide confirmación.** Es la única acción del módulo que no se deshace
 * del todo —reabrir devuelve la revisión a «en curso», pero la firma y la
 * instantánea se quedan hasta la siguiente aprobación—, y un clic suelto en el
 * carril no puede sellar un acta (DESIGN.md §1, Protección).
 */
const props = defineProps<{
    revision: Revision;
    entradas: Record<string, Record<string, unknown> | undefined>;
    congeladas: boolean;
    decisiones: Decision[];
    coste: { total: string; sinEstimar: number };
    transiciones: Destino[];
    puedeAprobar: boolean;
    puedeGestionar: boolean;
    /** El documento del acta, una vez preparado: uno por revisión. */
    acta: { id: number; codigo: string } | null;
    puedePrepararActa: boolean;
    prioridades: Opcion[];
    responsables: Opcion[];
}>();

const enviando = ref(false);

function mover(paso: Destino): void {
    enviando.value = true;

    router.post(
        `/revision-direccion/${props.revision.id}/estado`,
        { estado: paso.valor },
        { preserveScroll: true, onFinish: () => (enviando.value = false) },
    );
}

/* --- La firma --- */

const confirmando = ref(false);

function aprobar(): void {
    enviando.value = true;

    router.post(
        `/revision-direccion/${props.revision.id}/aprobacion`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => (confirmando.value = false),
            onFinish: () => (enviando.value = false),
        },
    );
}

/* --- El acta --- */

/*
 * Se prepara una vez, con la revisión aprobada, y lleva al documento: generar,
 * mandar a revisión y firmar sus versiones sigue siendo trabajo de `/documentos`.
 * Reabierta la revisión, el acta se sigue enlazando —existe y tiene su
 * histórico—, pero no se ofrece prepararla.
 */
const preparando = ref(false);

function prepararActa(): void {
    preparando.value = true;

    router.post(
        `/revision-direccion/${props.revision.id}/acta`,
        {},
        { preserveScroll: true, onFinish: () => (preparando.value = false) },
    );
}

const ofrecerActa = computed(
    () => props.acta === null && props.puedePrepararActa && props.revision.estado === 'aprobada',
);

const editable = computed(() => props.puedeGestionar && props.revision.admiteCambios);

/** Los tres pasos de la revisión, y dónde está ésta. */
const pasos = computed(() => {
    const orden = ['planificada', 'en_curso', 'aprobada'];
    const actual = orden.indexOf(props.revision.estado);

    return [
        { valor: 'planificada', etiqueta: 'Planificada', tono: 'planificado' },
        { valor: 'en_curso', etiqueta: 'En curso', tono: 'en_revision' },
        { valor: 'aprobada', etiqueta: 'Aprobada', tono: 'implantado' },
    ].map((paso, i) => ({
        ...paso,
        actual: i === actual,
        // Lo ya recorrido en el teal de marca; el paso actual, en el tono de su estado.
        relleno: i === actual ? tono(paso.tono).relleno : i < actual ? 'bg-primary' : 'bg-border',
    }));
});

/* --- Lo que falta --- */

/*
 * Sólo mientras el acta admite cambios: de un acta firmada no falta nada que se
 * pueda completar, y pedirlo sería invitar a reabrirla.
 */
const pendientes = computed(() => {
    if (!props.revision.admiteCambios) {
        return [];
    }

    return [
        props.revision.asistentes ? null : 'Asistentes',
        props.revision.conclusiones ? null : 'Conclusiones',
    ].filter((uno): uno is string => uno !== null);
});

/** «los asistentes y las conclusiones», para el aviso del diálogo de firma. */
const faltan = computed(() =>
    pendientes.value.map((uno) => (uno === 'Asistentes' ? 'los asistentes' : 'las conclusiones')).join(' y '),
);

const anterior = computed(() => {
    const previas = props.entradas.accionesPrevias as { revision?: { codigo: string; fecha: string } | null } | undefined;

    return previas?.revision ?? null;
});

/* --- Las salidas (9.3.3) --- */

const abierto = ref(false);

const decision = useForm({
    titulo: '',
    descripcion: '',
    prioridad: 'media',
    responsable_id: '',
    fecha_limite: '',
    coste_estimado: '',
});

function abrirDecision(): void {
    decision.reset();
    decision.clearErrors();
    abierto.value = true;
}

function crearDecision(): void {
    decision.post(`/revision-direccion/${props.revision.id}/decisiones`, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
            decision.reset();
        },
    });
}

function desvincular(id: number): void {
    router.delete(`/revision-direccion/${props.revision.id}/decisiones/${id}`, {
        preserveScroll: true,
    });
}

const abiertas = computed(
    () =>
        props.decisiones.filter((item) => item.estado !== 'hecha' && item.estado !== 'descartada')
            .length,
);
</script>

<template>
    <AppLayout :titulo="revision.codigo">
        <CabeceraPagina
            :titulo="revision.codigo"
            :descripcion="`Celebrada el ${revision.fechaLarga} · periodo revisado ${revision.periodo} · cláusula 9.3`"
        >
            <div class="mt-3">
                <CeldaBadge
                    anunciar
                    :valor="{
                        valor: revision.estado,
                        etiqueta: revision.estadoEtiqueta,
                        tono: revision.estadoTono,
                        icono: revision.estadoIcono,
                    }"
                />
            </div>

            <template #acciones>
                <Button v-if="acta" as-child variant="outline">
                    <Link :href="`/documentos/${acta.id}`">
                        <FileTextIcon aria-hidden="true" />
                        Ver el acta
                    </Link>
                </Button>
                <Button v-else-if="ofrecerActa" :disabled="preparando" @click="prepararActa">
                    <FileTextIcon aria-hidden="true" />
                    Preparar el acta
                </Button>
                <Button v-if="editable" as-child variant="outline">
                    <Link :href="`/revision-direccion/${revision.id}/editar`">
                        <PencilLineIcon aria-hidden="true" />
                        Editar
                    </Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="grid gap-4" :class="pendientes.length > 0 ? 'lg:grid-cols-2' : ''">
            <!-- En vivo o congeladas: lo primero que hay que saber de cada cifra. -->
            <section
                aria-labelledby="lectura"
                class="flex items-start gap-3 rounded-xl border bg-card px-5 py-3.5"
            >
                <LockIcon
                    v-if="congeladas"
                    class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <span
                    v-else
                    class="mt-1.5 size-2.5 shrink-0 rounded-full bg-acento ring-4 ring-acento-suave"
                    aria-hidden="true"
                />
                <div class="space-y-0.5">
                    <h2 id="lectura" class="text-sm font-semibold">
                        <template v-if="congeladas">
                            Entradas congeladas el {{ revision.aprobadaEn }}
                        </template>
                        <template v-else>Las entradas se leen en vivo</template>
                    </h2>
                    <p class="text-[13px] leading-[18px] text-muted-foreground">
                        <template v-if="congeladas">
                            Es lo que la dirección tuvo delante. No cambia aunque los registros sigan vivos;
                            las decisiones sí, porque se ejecutan después.
                        </template>
                        <template v-else>
                            Es la situación de hoy y no la del día de la reunión. Se congelan al aprobar el
                            acta.
                        </template>
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
                    {{ pendientes.length }}
                    {{ pendientes.length === 1 ? 'dato sin completar' : 'datos sin completar' }}
                </h2>
                <ul class="flex flex-1 flex-wrap gap-2">
                    <li v-for="pendiente in pendientes" :key="pendiente">
                        <Link
                            v-if="editable"
                            :href="`/revision-direccion/${revision.id}/editar`"
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
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="min-w-0 space-y-6">
                <EntradasRevision :entradas="entradas" :congeladas="congeladas" />

                <Card id="decisiones" class="scroll-mt-24">
                    <CardHeader class="flex flex-wrap items-start justify-between gap-4">
                        <div class="space-y-1.5">
                            <CardTitle>Decisiones y acciones</CardTitle>
                            <CardDescription class="max-w-xl">
                                Las salidas de la revisión (9.3.3). Nacen como tareas del plan de acción y su
                                estado será la entrada a) del acta siguiente: es lo que permite comprobar un año
                                después si lo que se decidió se hizo.
                            </CardDescription>
                        </div>
                        <!--
                            Se pueden registrar decisiones sobre un acta ya firmada:
                            el trigger blinda el acta, no lo que cuelga de ella, y una
                            decisión se ejecuta en las semanas siguientes.
                        -->
                        <Button v-if="puedeGestionar" variant="outline" @click="abrirDecision">
                            <PlusIcon aria-hidden="true" />
                            Registrar decisión
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <EstadoVacio
                            v-if="decisiones.length === 0"
                            titulo="Sin decisiones"
                            descripcion="La cláusula 9.3.3 pide dejar constancia de las decisiones sobre oportunidades de mejora y sobre cambios en el sistema de gestión."
                        />

                        <template v-else>
                            <p class="flex flex-wrap items-baseline gap-x-8 gap-y-1 text-[13px] text-muted-foreground">
                                <span>
                                    <Cifra class="cifra text-[22px] font-medium text-foreground" :valor="abiertas" />
                                    {{ abiertas === 1 ? 'abierta' : 'abiertas' }} de {{ decisiones.length }}
                                </span>
                                <span>
                                    Coste estimado: <span class="text-foreground">{{ coste.total }}</span>
                                    <template v-if="coste.sinEstimar > 0">
                                        ({{ coste.sinEstimar }} sin estimar)
                                    </template>
                                </span>
                            </p>

                            <ul class="divide-y border-t">
                                <li
                                    v-for="item in decisiones"
                                    :key="item.id"
                                    class="grid gap-x-3 gap-y-1.5 py-3 text-[13px] sm:grid-cols-[minmax(0,1fr)_9rem_auto_auto] sm:items-center"
                                >
                                    <Link
                                        :href="`/tareas/${item.id}`"
                                        class="font-medium underline-offset-4 hover:underline"
                                    >
                                        {{ item.titulo }}
                                    </Link>
                                    <span class="text-muted-foreground">{{ item.responsable ?? 'Sin responsable' }}</span>
                                    <span class="flex flex-wrap items-center gap-2">
                                        <CeldaBadge
                                            :valor="{
                                                valor: 'plazo',
                                                etiqueta: item.plazoEtiqueta,
                                                tono: item.plazoTono,
                                                icono: null,
                                            }"
                                        />
                                        <CeldaBadge
                                            :valor="{
                                                valor: item.estado,
                                                etiqueta: item.estadoEtiqueta,
                                                tono: item.estadoTono,
                                                icono: item.estadoIcono,
                                            }"
                                        />
                                    </span>
                                    <Button
                                        v-if="puedeGestionar"
                                        variant="ghost"
                                        size="sm"
                                        class="justify-self-start text-muted-foreground sm:justify-self-end"
                                        @click="desvincular(item.id)"
                                    >
                                        Desvincular
                                    </Button>
                                </li>
                            </ul>
                        </template>
                    </CardContent>
                </Card>

                <Card id="conclusiones" class="scroll-mt-24">
                    <CardHeader>
                        <CardTitle>Conclusiones</CardTitle>
                        <CardDescription>La única parte del acta que escribe una persona.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p v-if="revision.conclusiones" class="max-w-prose text-sm whitespace-pre-line">
                            {{ revision.conclusiones }}
                        </p>
                        <!--
                            No se esconde vacía: es lo primero que el acta necesita de
                            una persona, y una tarjeta ausente no dice que falta.
                        -->
                        <EstadoVacio
                            v-else
                            titulo="Sin conclusiones"
                            descripcion="Lo que la dirección concluye sobre la idoneidad, adecuación y eficacia del sistema, y la retroalimentación que se aportó fuera."
                            :accion="
                                editable
                                    ? { etiqueta: 'Redactar conclusiones', href: `/revision-direccion/${revision.id}/editar` }
                                    : undefined
                            "
                        />
                    </CardContent>
                </Card>
            </div>

            <div class="h-fit space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Estado</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <ol aria-label="Recorrido de la revisión" class="grid grid-cols-3 gap-1">
                            <li
                                v-for="paso in pasos"
                                :key="paso.valor"
                                :aria-current="paso.actual ? 'step' : undefined"
                                class="flex flex-col gap-1.5"
                            >
                                <span class="h-1 rounded-full" :class="paso.relleno" aria-hidden="true" />
                                <span
                                    class="text-xs"
                                    :class="paso.actual ? 'font-semibold text-foreground' : 'text-muted-foreground'"
                                >
                                    {{ paso.etiqueta }}
                                </span>
                            </li>
                        </ol>

                        <!-- La firma, cuando la hay. -->
                        <div v-if="revision.aprobadaEn" class="flex items-center gap-3 rounded-lg border p-3.5">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full"
                                :class="tono('implantado').badge"
                            >
                                <BadgeCheckIcon class="size-5" aria-hidden="true" />
                            </span>
                            <div class="text-[13px]">
                                <p class="font-semibold">
                                    {{ revision.aprobadaPor ? `Firmada por ${revision.aprobadaPor}` : 'Firmada' }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ revision.aprobadaEn }}
                                    <template v-if="!congeladas">· firma anterior, antes de reabrir</template>
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="puedeAprobar"
                            class="space-y-3 rounded-lg border border-acento-borde bg-acento-suave p-4"
                        >
                            <p class="text-sm font-semibold">Firmar el acta</p>
                            <p class="text-[13px] leading-[18px] text-secondary-foreground">
                                Congela las siete entradas tal como están ahora y deja el acta firmada a tu
                                nombre. Las decisiones se pueden seguir registrando después.
                            </p>
                            <Button variant="acento" class="w-full" :disabled="enviando" @click="confirmando = true">
                                <BadgeCheckIcon aria-hidden="true" />
                                Aprobar el acta
                            </Button>
                        </div>

                        <p
                            v-else-if="revision.estado === 'en_curso'"
                            class="text-[13px] leading-[18px] text-muted-foreground"
                        >
                            Pendiente de que la dirección firme el acta. Hasta entonces, lo que se ve es la
                            situación de hoy y no la del día de la reunión.
                        </p>

                        <div v-if="puedeGestionar && transiciones.length > 0" class="space-y-2 border-t pt-4">
                            <p class="text-xs text-muted-foreground">
                                <template v-if="revision.estado === 'aprobada'">
                                    De aprobada sólo se vuelve a «en curso»: decir que la reunión no se celebró
                                    sería reescribir el pasado.
                                </template>
                                <template v-else-if="revision.estado === 'en_curso'">¿Se aplaza la reunión?</template>
                                <template v-else>Cuando empiece la reunión:</template>
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <BotonEstado
                                    v-for="paso in transiciones"
                                    :key="paso.valor"
                                    :destino="paso"
                                    :deshabilitado="enviando"
                                    @click="mover(paso)"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ficha</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="space-y-3.5 text-[13px]">
                            <div class="space-y-0.5">
                                <dt class="text-muted-foreground">Celebrada</dt>
                                <dd>{{ revision.fechaLarga }}</dd>
                            </div>
                            <div class="space-y-0.5">
                                <dt class="text-muted-foreground">Periodo revisado</dt>
                                <dd>{{ revision.periodo }}</dd>
                            </div>
                            <div class="space-y-0.5">
                                <dt class="text-muted-foreground">Asistentes</dt>
                                <!--
                                    «Sin registrar» y no un hueco: quién asistió es lo
                                    primero que un auditor comprueba, y una línea ausente
                                    se lee como que el dato no aplica.
                                -->
                                <dd :class="revision.asistentes ? '' : 'text-muted-foreground'">
                                    {{ revision.asistentes ?? 'Sin registrar' }}
                                </dd>
                            </div>
                            <div class="space-y-0.5">
                                <dt class="text-muted-foreground">Revisión anterior</dt>
                                <dd v-if="anterior">
                                    <span class="cifra">{{ anterior.codigo }}</span>
                                    <span class="text-muted-foreground">, celebrada el {{ anterior.fecha }}</span>
                                </dd>
                                <dd v-else class="text-muted-foreground">Es la primera</dd>
                            </div>
                            <div class="space-y-0.5">
                                <dt class="text-muted-foreground">Acta</dt>
                                <dd v-if="acta">
                                    <Link
                                        :href="`/documentos/${acta.id}`"
                                        class="cifra text-primary underline-offset-4 hover:underline"
                                    >
                                        {{ acta.codigo }}
                                    </Link>
                                </dd>
                                <dd v-else-if="revision.estado === 'aprobada'" class="text-muted-foreground">
                                    Sin preparar
                                </dd>
                                <dd v-else class="text-muted-foreground">Se prepara al aprobar la revisión</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="confirmando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Aprobar el acta {{ revision.codigo }}</DialogTitle>
                    <DialogDescription>
                        Se recogen las siete entradas tal como están ahora y se sellan con tu firma. A partir de
                        aquí el acta no cambia aunque los registros sigan vivos.
                    </DialogDescription>
                </DialogHeader>

                <ul class="space-y-2 text-[13px] text-secondary-foreground">
                    <li class="flex gap-2">
                        <LockIcon class="mt-0.5 size-4 shrink-0 text-acento" aria-hidden="true" />
                        Las entradas a) a g) quedan congeladas con la fecha de hoy.
                    </li>
                    <li class="flex gap-2">
                        <BadgeCheckIcon class="mt-0.5 size-4 shrink-0 text-acento" aria-hidden="true" />
                        El acta no admite cambios. Corregirla obliga a reabrir la revisión.
                    </li>
                    <li class="flex gap-2">
                        <PlusIcon class="mt-0.5 size-4 shrink-0 text-acento" aria-hidden="true" />
                        {{
                            decisiones.length === 0
                                ? 'Sin decisiones registradas todavía; se pueden añadir después.'
                                : `${decisiones.length} ${decisiones.length === 1 ? 'decisión vinculada, que sigue' : 'decisiones vinculadas, que siguen'} abierta${decisiones.length === 1 ? '' : 's'} a cambios.`
                        }}
                    </li>
                </ul>

                <div
                    v-if="pendientes.length > 0"
                    class="flex items-start gap-2.5 rounded-lg border bg-superficie px-3.5 py-3 text-[13px] text-secondary-foreground"
                >
                    <InfoIcon class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <p>
                        Faltan {{ faltan }}. Se puede aprobar igualmente, pero el
                        acta saldrá sin ello y añadirlo después obliga a reabrirla.
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="confirmando = false">Cancelar</Button>
                    <Button variant="acento" :disabled="enviando" @click="aprobar">
                        <BadgeCheckIcon aria-hidden="true" />
                        Aprobar y congelar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Registrar decisión</DialogTitle>
                    <DialogDescription>
                        Nace como tarea del plan de acción, con el origen ya puesto. Su estado será
                        la primera entrada del acta siguiente.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <CampoTexto
                        nombre="titulo"
                        etiqueta="Decisión"
                        :error="decision.errors.titulo"
                        requerido
                        @input="decision.titulo = ($event.target as HTMLInputElement).value"
                    />
                    <CampoSelect
                        nombre="prioridad"
                        etiqueta="Prioridad"
                        :opciones="prioridades"
                        :valor-inicial="decision.prioridad"
                        :error="decision.errors.prioridad"
                        requerido
                        @update:model-value="(valor?: string) => (decision.prioridad = valor ?? 'media')"
                    />
                    <CampoSelect
                        nombre="responsable_id"
                        etiqueta="Responsable"
                        :opciones="responsables"
                        :error="decision.errors.responsable_id"
                        @update:model-value="(valor?: string) => (decision.responsable_id = valor ?? '')"
                    />
                    <CampoTexto
                        nombre="fecha_limite"
                        etiqueta="Fecha límite"
                        tipo="date"
                        :error="decision.errors.fecha_limite"
                        @input="decision.fecha_limite = ($event.target as HTMLInputElement).value"
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="abierto = false">Cancelar</Button>
                    <Button :disabled="decision.processing" @click="crearDecision">Registrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
