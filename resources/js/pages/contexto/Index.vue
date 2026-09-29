<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import ConmutadorContexto from '@/components/contexto/ConmutadorContexto.vue';
import MatrizDafo from '@/components/contexto/MatrizDafo.vue';
import IconoTipo from '@/components/IconoTipo.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { distanciaLegible, fechaLegible } from '@/lib/celdas';
import { tono } from '@/lib/tonos';
import { Link, router } from '@inertiajs/vue3';
import { CheckIcon, ChevronRightIcon, CircleDashedIcon, LockIcon } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * El panorama del contexto: la cara del § 4.1.
 *
 * Tres bloques y en este orden, que es el de la norma: **qué hay firmado y desde
 * cuándo**, **qué dice el DAFO** —con las partes interesadas y lo que pide acción
 * a su lado— y **hasta dónde llega el SGSI**. La pregunta que un auditor hace
 * primero es la primera, y por eso la tarjeta del análisis va arriba aunque la
 * matriz sea lo que se mira a diario.
 *
 * **La tarjeta del análisis dice qué le falta al borrador para aprobarse.** Antes
 * el único aviso era una frase gris dentro del bloque del clima, y el resto se
 * descubría al pulsar y recibir el rechazo. La lista la manda el servidor desde
 * `AprobarAnalisis::comprobaciones()`, que son los mismos predicados que
 * rechazan: aquí no se decide nada.
 *
 * **Lo que pide acción** son las dos cifras de `resumen` que ya llegaban y que la
 * pantalla sólo enseñaba a medias, cada una enlazada a la lista exacta que
 * cuenta. Sin las que están a cero, como en el panel.
 *
 * **El alcance se enseña y no se edita aquí.** La cláusula 4.3 vive en
 * `sistemas.alcance_declarado` desde la primera migración y ya se imprime en la
 * portada de los cuatro documentos; repetir el campo aquí sería el mismo dato en
 * dos pantallas que pueden discrepar. Lo que este módulo le añade es histórico: al
 * aprobar, se congela en la instantánea.
 */

interface Estado {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
}

interface Analisis {
    id: number;
    numero: number | null;
    etiqueta: string;
    fechaAnalisis: string;
    estado: Estado;
    climaPertinente: boolean | null;
    climaJustificacion: string | null;
    nota: string | null;
    creadoPor: string | null;
    aprobadoPor: string | null;
    aprobadoEn: string | null;
    tieneInstantanea: boolean;
}

interface Sistema {
    id: number;
    codigo: string;
    nombre: string;
    marco: string | null;
    alcanceDeclarado: string | null;
    exclusiones: string | null;
}

/*
 * Repetidas aquí y en `MatrizDafo`, a propósito y sin compartirlas: un SFC no
 * exporta tipos desde `<script setup>` sin un segundo bloque de script, y montar
 * ese andamiaje para dos interfaces que el servidor ya fija en
 * `ContextoController` cuesta más de lo que ahorra.
 */
interface Cuestion {
    id: number;
    codigo: string;
    titulo: string;
    tipo: string;
    tipoEtiqueta: string;
    tono: string;
    icono: string;
    materia: string;
    esClimatica: boolean;
    responsable: string | null;
    riesgos: number;
    tareas: number;
    sinRiesgo: boolean;
}

interface Comprobacion {
    clave: string;
    etiqueta: string;
    cumplida: boolean;
    ayuda: string;
}

interface Parte {
    id: number;
    codigo: string;
    nombre: string;
    ambito: string;
    obligan: number;
}

interface TipoEje {
    valor: string;
    etiqueta: string;
    tono: string;
    icono: string;
    signo: string;
    signoEtiqueta: string;
}

interface AmbitoEje {
    valor: string;
    etiqueta: string;
    ayuda: string;
    tipos: TipoEje[];
}

const props = defineProps<{
    vigente: Analisis | null;
    borrador: Analisis | null;
    comprobaciones: Comprobacion[];
    dafo: Record<string, Cuestion[]>;
    ejes: { ambitos: AmbitoEje[] };
    partes: Parte[];
    alcance: Sistema[];
    resumen: App.Http.Resources.Panel.ResumenContextoPanel;
    puedeGestionar: boolean;
    puedeAprobar: boolean;
}>();

/* El borrador manda cuando existe: es lo que se está escribiendo ahora mismo. */
const enCurso = computed(() => props.borrador ?? props.vigente);

const editando = ref(false);
const enviando = ref(false);
const fecha = ref('');
const nota = ref('');
const clima = ref<'' | 'si' | 'no'>('');
const climaJustificacion = ref('');

function abrirEdicion(): void {
    const base = enCurso.value;
    fecha.value = base?.fechaAnalisis ?? new Date().toISOString().slice(0, 10);
    nota.value = base?.nota ?? '';
    clima.value = base?.climaPertinente === null || base?.climaPertinente === undefined
        ? ''
        : base.climaPertinente
          ? 'si'
          : 'no';
    climaJustificacion.value = base?.climaJustificacion ?? '';
    editando.value = true;
}

function guardar(): void {
    enviando.value = true;

    router.put(
        '/contexto/analisis',
        {
            fecha_analisis: fecha.value,
            nota: nota.value || null,
            // Cadena vacía es «sin contestar», que no es lo mismo que «no es
            // pertinente»: la enmienda 1:2024 obliga a determinarlo, y las dos
            // respuestas tienen que poder distinguirse.
            clima_pertinente: clima.value === '' ? null : clima.value === 'si',
            clima_justificacion: climaJustificacion.value || null,
        },
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

function aprobar(): void {
    if (!props.borrador) {
        return;
    }

    enviando.value = true;
    router.post(
        `/contexto/analisis/${props.borrador.id}/aprobacion`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                enviando.value = false;
            },
        },
    );
}

const cumplidas = computed(() => props.comprobaciones.filter((comprobacion) => comprobacion.cumplida).length);

/** Hay columna de acciones sólo si hay algo que pulsar en ella. */
const hayAcciones = computed(() => props.puedeGestionar || (props.puedeAprobar && props.borrador !== null));

const pendientes = computed(() =>
    [
        {
            clave: 'sin_riesgo',
            cifra: props.resumen.sinRiesgo,
            texto: props.resumen.sinRiesgo === 1 ? 'Cuestión adversa sin riesgo vinculado' : 'Cuestiones adversas sin riesgo vinculado',
            ayuda: 'La 6.1.1 pide considerarlas al apreciar los riesgos.',
            href: '/contexto/cuestiones?filter[sin_riesgo]=1&filter[vigentes]=1',
        },
        {
            clave: 'obligacion_sin_cubrir',
            cifra: props.resumen.obligacionesSinCubrir,
            texto: props.resumen.obligacionesSinCubrir === 1 ? 'Obligación sin medida que la cubra' : 'Obligaciones sin medida que las cubra',
            ayuda: `De ${props.resumen.requisitosQueObligan} requisitos legales o contractuales de las partes interesadas.`,
            href: '/partes-interesadas?filter[obligacion_sin_cubrir]=1',
        },
    ].filter((pendiente) => pendiente.cifra > 0),
);

const climaTexto = computed(() => {
    const base = enCurso.value;

    if (!base || base.climaPertinente === null) {
        return null;
    }

    return base.climaPertinente
        ? 'Sí, es una cuestión pertinente.'
        : 'No es una cuestión pertinente.';
});
</script>

<template>
    <AppLayout ancho="completo" titulo="Contexto de la organización">
        <CabeceraPagina
            titulo="Contexto de la organización"
            descripcion="Lo que la organización tiene a favor y en contra, quién le exige qué y hasta dónde llega el SGSI. Cláusulas 4.1 a 4.3 de ISO/IEC 27001:2022."
        >
            <template #acciones>
                <ConmutadorContexto vista="matriz" />
            </template>
        </CabeceraPagina>

        <div class="space-y-8">
            <!-- 1. Qué hay firmado, qué se está escribiendo y qué le falta. -->
            <Card
                class="grid gap-0 py-0 lg:grid-cols-[20rem_minmax(0,1fr)]"
                :class="hayAcciones ? 'xl:grid-cols-[20rem_minmax(0,1fr)_15rem]' : ''"
               
            >
                <div class="space-y-3 p-6 max-lg:border-b lg:border-r lg:border-border/60">
                    <h2 id="titulo-analisis" class="flex flex-wrap items-center gap-2 text-base font-semibold">
                        <!-- El borrador se llama «Borrador», y el badge ya lo dice. -->
                        <span>{{ borrador ? 'Revisión en curso' : (enCurso?.etiqueta ?? 'Sin análisis del contexto') }}</span>
                        <span
                            v-if="enCurso"
                            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="tono(enCurso.estado.tono).badge"
                        >
                            <IconoTipo :nombre="enCurso.estado.icono" />
                            {{ enCurso.estado.etiqueta }}
                        </span>
                    </h2>

                    <template v-if="enCurso">
                        <p class="text-sm text-secondary-foreground">
                            Analizado el {{ fechaLegible(enCurso.fechaAnalisis) }}<template v-if="enCurso.aprobadoPor">, aprobado por {{ enCurso.aprobadoPor }}</template><template v-else-if="enCurso.creadoPor">, abierto por {{ enCurso.creadoPor }}</template>.
                        </p>

                        <!-- Lo firmado sigue mandando: se cita, con la regla de 2 px de §9. -->
                        <div v-if="borrador && vigente" class="space-y-0.5 border-l-2 border-border pl-3">
                            <p class="text-xs text-muted-foreground">Sigue vigente hasta que se apruebe</p>
                            <Link :href="`/contexto/analisis/${vigente.id}`" class="text-sm font-medium text-primary hover:underline">
                                {{ vigente.etiqueta }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                Analizado el {{ fechaLegible(vigente.fechaAnalisis) }} ({{ distanciaLegible(vigente.fechaAnalisis) }})<template v-if="vigente.aprobadoPor"> · aprobado por {{ vigente.aprobadoPor }}</template>
                            </p>
                        </div>
                    </template>

                    <p v-else class="text-sm text-muted-foreground">
                        Nadie ha declarado todavía cuál es el contexto de la organización. Es la
                        cláusula 4.1, y de ella cuelgan el alcance del SGSI y la apreciación de riesgos.
                    </p>
                </div>

                <div class="space-y-5 p-6">
                    <!--
                        Lo que falta para aprobar, antes de pulsar. Sólo sobre el
                        borrador: lo aprobado ya pasó por aquí.
                    -->
                    <section v-if="borrador && comprobaciones.length > 0" aria-labelledby="titulo-aprobar" class="space-y-3">
                        <div class="flex items-baseline justify-between gap-3">
                            <h3 id="titulo-aprobar" class="text-sm font-semibold">Para aprobarlo</h3>
                            <span class="cifra text-xs text-muted-foreground">{{ cumplidas }} de {{ comprobaciones.length }}</span>
                        </div>
                        <ul class="space-y-3">
                            <li v-for="comprobacion in comprobaciones" :key="comprobacion.clave" class="flex items-start gap-3">
                                <span
                                    class="mt-px flex size-5 shrink-0 items-center justify-center rounded-full"
                                    :class="comprobacion.cumplida ? tono('implantado').badge : tono('en_progreso').badge"
                                    aria-hidden="true"
                                >
                                    <CheckIcon v-if="comprobacion.cumplida" class="size-3" :stroke-width="3" />
                                    <CircleDashedIcon v-else class="size-3" :stroke-width="2.5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">
                                        {{ comprobacion.etiqueta }}
                                        <span class="sr-only">: {{ comprobacion.cumplida ? 'hecho' : 'pendiente' }}</span>
                                    </p>
                                    <p v-if="!comprobacion.cumplida" class="text-xs text-muted-foreground">{{ comprobacion.ayuda }}</p>
                                </div>
                                <template v-if="!comprobacion.cumplida && puedeGestionar">
                                    <Button v-if="comprobacion.clave === 'clima'" variant="link" size="sm" class="h-auto shrink-0 p-0" @click="abrirEdicion">
                                        Contestar
                                    </Button>
                                    <Link
                                        v-else-if="comprobacion.clave === 'cuestiones'"
                                        href="/contexto/cuestiones/crear"
                                        class="shrink-0 text-sm font-medium text-primary hover:underline"
                                    >
                                        Añadir una
                                    </Link>
                                </template>
                            </li>
                        </ul>
                    </section>

                    <!--
                        La declaración del cambio climático, con su razonamiento: la
                        enmienda 1:2024 obliga a determinar si es pertinente, y «no
                        lo hemos mirado» y «lo hemos mirado y no aplica» tienen que
                        poder distinguirse.
                    -->
                    <dl v-if="enCurso" class="grid gap-4 text-sm sm:grid-cols-2">
                        <div class="space-y-1">
                            <dt class="text-xs font-medium text-muted-foreground">Cambio climático</dt>
                            <dd v-if="climaTexto">{{ climaTexto }}</dd>
                            <dd v-else class="text-muted-foreground">Sin contestar.</dd>
                            <dd v-if="enCurso.climaJustificacion" class="text-muted-foreground">{{ enCurso.climaJustificacion }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium text-muted-foreground">Cómo se hizo</dt>
                            <dd v-if="enCurso.nota" class="text-muted-foreground">{{ enCurso.nota }}</dd>
                            <dd v-else class="text-muted-foreground">
                                Sin anotar. Quién participó y qué fuentes se miraron es lo que el auditor pregunta.
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    v-if="hayAcciones"
                    class="flex flex-col gap-2 bg-superficie p-6 max-xl:border-t max-xl:border-border/60 lg:col-span-2 xl:col-span-1 xl:border-l xl:border-border/60"
                >
                    <template v-if="puedeAprobar && borrador">
                        <!--
                            Nunca deshabilitado: la lista de al lado ya dice qué
                            falta, y si se pulsa igual, el rechazo sale con su motivo.
                        -->
                        <Button variant="acento" :disabled="enviando" @click="aprobar">
                            <LockIcon class="size-4" aria-hidden="true" />
                            Aprobar y congelar
                        </Button>
                        <p class="text-xs text-muted-foreground">
                            Congela el DAFO, las partes interesadas y el alcance tal y como están hoy.<template v-if="vigente"> {{ vigente.etiqueta }} pasa a obsoleto.</template>
                        </p>
                    </template>
                    <!--
                        «Empezar» cuando no hay borrador, aunque haya un análisis
                        vigente: guardar estrena una revisión nueva partiendo de la
                        anterior, no reescribe la firmada — que además el trigger no
                        dejaría.
                    -->
                    <Button v-if="puedeGestionar" variant="outline" class="xl:mt-auto" @click="abrirEdicion">
                        {{ borrador ? 'Editar la revisión' : 'Empezar una revisión' }}
                    </Button>
                </div>
            </Card>

            <!-- 2. El DAFO, y a su lado lo que pide acción y a quién se le debe algo. -->
            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <section aria-labelledby="titulo-dafo" class="min-w-0 space-y-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 id="titulo-dafo" class="flex items-baseline gap-3 text-base font-semibold">
                            Cuestiones internas y externas
                            <span class="text-sm font-normal text-muted-foreground">
                                <span class="cifra">{{ resumen.cuestiones }}</span> vigentes
                            </span>
                        </h2>
                        <Link href="/contexto/cuestiones" class="text-sm font-medium text-primary hover:underline">
                            Ver en la tabla
                        </Link>
                    </div>

                    <MatrizDafo :ambitos="ejes.ambitos" :dafo="dafo" :puede-anadir="puedeGestionar" />
                </section>

                <aside class="grid gap-6 md:grid-cols-2 xl:grid-cols-1">
                    <Card class="gap-0 py-0">
                        <h2 id="titulo-pendiente" class="px-4 pt-4 pb-2 text-base font-semibold">Lo que pide acción</h2>
                        <ul v-if="pendientes.length > 0" class="pb-2">
                            <li v-for="pendiente in pendientes" :key="pendiente.clave">
                                <Link
                                    :href="pendiente.href"
                                    class="flex min-h-11 items-center gap-3 px-4 py-2.5 transition-colors hover:bg-fila-hover"
                                >
                                    <span class="cifra w-7 shrink-0 text-right text-xl font-bold">{{ pendiente.cifra }}</span>
                                    <span class="min-w-0 flex-1 text-sm">
                                        {{ pendiente.texto }}
                                        <span class="block text-xs text-muted-foreground">{{ pendiente.ayuda }}</span>
                                    </span>
                                    <ChevronRightIcon class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                </Link>
                            </li>
                        </ul>
                        <p v-else class="px-4 pb-4 text-sm text-muted-foreground">
                            Toda cuestión adversa tiene un riesgo detrás y toda obligación, una medida.
                        </p>
                    </Card>

                    <Card class="gap-0 py-0">
                        <div class="flex items-baseline justify-between gap-2 px-4 pt-4">
                            <h2 id="titulo-partes" class="text-base font-semibold">Partes interesadas</h2>
                            <span class="text-xs text-muted-foreground">Cláusula 4.2</span>
                        </div>
                        <p class="px-4 pt-0.5 pb-3 text-sm text-muted-foreground">
                            <span class="cifra text-foreground">{{ resumen.partes }}</span> vigentes ·
                            <span class="cifra text-foreground">{{ resumen.requisitosQueObligan }}</span>
                            {{ resumen.requisitosQueObligan === 1 ? 'requisito que obliga' : 'requisitos que obligan' }}
                        </p>

                        <ul v-if="partes.length > 0">
                            <li v-for="parte in partes" :key="parte.id" class="border-t border-border/60">
                                <Link
                                    :href="`/partes-interesadas/${parte.id}`"
                                    class="flex items-center gap-3 px-4 py-2 transition-colors hover:bg-fila-hover"
                                >
                                    <span class="cifra w-10 shrink-0 text-xs text-muted-foreground">{{ parte.codigo }}</span>
                                    <span class="min-w-0 flex-1 text-sm">
                                        {{ parte.nombre }}
                                        <span class="block text-xs text-muted-foreground">{{ parte.ambito }}</span>
                                    </span>
                                    <span v-if="parte.obligan > 0" class="shrink-0 text-xs text-muted-foreground">
                                        {{ parte.obligan }} {{ parte.obligan === 1 ? 'obliga' : 'obligan' }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                        <p v-else class="border-t border-border/60 px-4 py-3 text-sm text-muted-foreground">
                            Ninguna registrada todavía.
                            <Link v-if="puedeGestionar" href="/partes-interesadas/crear" class="font-medium text-primary hover:underline">
                                Registrar la primera
                            </Link>
                        </p>

                        <Link
                            v-if="resumen.partes > partes.length"
                            href="/partes-interesadas"
                            class="block border-t border-border/60 px-4 py-2.5 text-sm font-medium text-primary hover:underline"
                        >
                            Ver las {{ resumen.partes }}
                        </Link>
                    </Card>
                </aside>
            </div>

            <!-- 3. Hasta dónde llega. -->
            <section aria-labelledby="titulo-alcance" class="space-y-3">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <h2 id="titulo-alcance" class="text-base font-semibold">Alcance declarado</h2>
                    <p class="text-sm text-muted-foreground">
                        Cláusula 4.3. Se escribe en la ficha de cada sistema y se congela al aprobar.
                    </p>
                </div>

                <p v-if="alcance.length === 0" class="text-sm text-muted-foreground">
                    No hay ningún sistema activo, así que no hay alcance que declarar todavía.
                </p>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-24">Código</TableHead>
                            <TableHead class="w-64">Sistema</TableHead>
                            <TableHead>Alcance</TableHead>
                            <TableHead class="w-72">Exclusiones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="sistema in alcance" :key="sistema.id" class="align-top">
                            <TableCell class="cifra text-xs text-muted-foreground">{{ sistema.codigo }}</TableCell>
                            <TableCell class="font-medium whitespace-normal">
                                {{ sistema.nombre }}
                                <span v-if="sistema.marco" class="block text-xs font-normal text-muted-foreground">{{ sistema.marco }}</span>
                            </TableCell>
                            <TableCell class="min-w-72 whitespace-normal">
                                <template v-if="sistema.alcanceDeclarado">{{ sistema.alcanceDeclarado }}</template>
                                <span v-else class="text-muted-foreground">
                                    Sin alcance declarado.
                                    <Link :href="`/sistemas/${sistema.id}/editar`" class="font-medium text-primary hover:underline">
                                        Escribirlo
                                    </Link>
                                </span>
                            </TableCell>
                            <TableCell class="min-w-56 whitespace-normal text-muted-foreground">
                                {{ sistema.exclusiones ?? '—' }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </section>
        </div>

        <Dialog v-model:open="editando">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Revisión del contexto</DialogTitle>
                    <DialogDescription>
                        Se guarda en el borrador. Lo que lo convierte en el contexto vigente de la
                        organización es aprobarlo, y eso lo congela.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="fecha-analisis">Fecha del análisis</Label>
                        <input
                            id="fecha-analisis"
                            v-model="fecha"
                            type="date"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="clima">¿Es pertinente el cambio climático?</Label>
                        <select
                            id="clima"
                            v-model="clima"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                        >
                            <option value="">Sin contestar</option>
                            <option value="si">Sí, es pertinente</option>
                            <option value="no">No es pertinente</option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            La enmienda 1:2024 obliga a determinarlo. «No es pertinente» es una
                            respuesta válida siempre que venga razonada.
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="clima-justificacion">Razonamiento sobre el clima</Label>
                        <textarea
                            id="clima-justificacion"
                            v-model="climaJustificacion"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="nota-analisis">Cómo se hizo</Label>
                        <textarea
                            id="nota-analisis"
                            v-model="nota"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                        />
                        <p class="text-xs text-muted-foreground">
                            Quién participó, qué fuentes se miraron. Es lo que el auditor pregunta
                            cuando quiere saber si el análisis lo hizo alguien o salió de una plantilla.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="enviando" @click="editando = false">Cancelar</Button>
                    <Button :disabled="enviando" @click="guardar">Guardar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
