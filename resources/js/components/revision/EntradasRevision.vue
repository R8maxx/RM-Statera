<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { tono } from '@/lib/tonos';
import { ChevronRightIcon, CloudLightningIcon, LightbulbIcon, ShieldAlertIcon, ShieldCheckIcon } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Las siete entradas de la cláusula 9.3.2, en el orden en que la norma las
 * enumera: primero de un vistazo y después apartado por apartado.
 *
 * **El orden es el de la norma y no el que quedaría mejor.** Un auditor recorre
 * la 9.3.2 de la a) a la g) con el acta delante; reordenarlas le obliga a buscar
 * cada una. Por eso el resumen no ordena por gravedad: dice qué pide atención en
 * cada fila y deja cada fila en su sitio.
 *
 * **Un cero es una entrada recogida, no una entrada que falte.** Una organización
 * puede llegar a su primera revisión sin auditorías en el periodo, y eso es lo
 * que la revisión tiene que decir. Por eso en el detalle no se esconde ninguna
 * cifra a cero; en el resumen sí, porque ahí lo que se lista es lo que pide
 * atención, y una fila de ceros es la tira de «todo completo» otra vez.
 *
 * **El rojo, sólo para lo que ya va mal** (DESIGN.md §3): una acción o una no
 * conformidad vencida, un objetivo que se pasó de fecha, una reevaluación
 * vencida. Lo demás que conviene mirar —sin medir, sin aceptar, sin empezar— va
 * en chip neutro: falta, no está mal.
 *
 * Las entradas llegan como el árbol que se congela en la instantánea, así que el
 * tipo es abierto a propósito: lo que este componente pinta y lo que el acta
 * imprime son literalmente la misma estructura, y fijarla aquí con una interfaz
 * obligaría a mantenerla en dos sitios. Una instantánea anterior a un campo nuevo
 * —`vencidas`, los iconos— llega sin él, y se pinta sin él.
 */
interface Entradas {
    [clave: string]: Record<string, unknown> | undefined;
}

const props = defineProps<{
    entradas: Entradas;
    /** Congeladas con el acta firmada: el resumen habla en pasado. */
    congeladas: boolean;
}>();

/** Lo que haya bajo una clave, siempre como objeto: la instantánea puede venir a medias. */
function bloque(clave: string): Record<string, any> {
    const valor = props.entradas[clave];

    return valor && typeof valor === 'object' ? (valor as Record<string, any>) : {};
}

function lista(origen: Record<string, any>, clave: string): Record<string, any>[] {
    const valor = origen[clave];

    return Array.isArray(valor) ? valor : [];
}

function numero(valor: unknown): number {
    return typeof valor === 'number' ? valor : 0;
}

function plural(cantidad: number, singular: string, varios: string): string {
    return `${cantidad} ${cantidad === 1 ? singular : varios}`;
}

const previas = computed(() => bloque('accionesPrevias'));
const contexto = computed(() => bloque('contexto'));
const partes = computed(() => bloque('partesInteresadas'));
const desempeno = computed(() => bloque('desempeno'));
const riesgos = computed(() => bloque('riesgos'));
const mejoras = computed(() => bloque('mejoras'));

const acciones = computed(() => lista(previas.value, 'acciones'));
const listaPartes = computed(() => lista(partes.value, 'partes'));
const auditorias = computed(() => lista(desempeno.value.auditorias ?? {}, 'detalle'));
const objetivos = computed(() => lista(desempeno.value.objetivos ?? {}, 'detalle'));
const listaMejoras = computed(() => lista(mejoras.value, 'detalle'));

const requisitos = computed(() => listaPartes.value.reduce((suma, parte) => suma + numero(parte.requisitos), 0));

/** Los cuatro cuadrantes del DAFO, con su icono de DESIGN.md §3. */
const cuadrantes: { clave: string; etiqueta: string; icono: LucideIcon }[] = [
    { clave: 'fortaleza', etiqueta: 'Fortalezas', icono: ShieldCheckIcon },
    { clave: 'debilidad', etiqueta: 'Debilidades', icono: ShieldAlertIcon },
    { clave: 'oportunidad', etiqueta: 'Oportunidades', icono: LightbulbIcon },
    { clave: 'amenaza', etiqueta: 'Amenazas', icono: CloudLightningIcon },
];

function enCuadrante(clave: string): number {
    const dafo = contexto.value.dafo;

    return dafo && Array.isArray(dafo[clave]) ? dafo[clave].length : 0;
}

const hayDafo = computed(() => contexto.value.dafo && typeof contexto.value.dafo === 'object');

/* --- El resumen --- */

interface Senal {
    texto: string;
    /** Algo que ya va mal: rojo. Si no, chip neutro. */
    vencida: boolean;
}

interface Fila {
    ancla: string;
    letra: string;
    titulo: string;
    resumen: string;
    senales: Senal[];
}

function senal(cantidad: number, texto: string, vencida = false): Senal | null {
    return cantidad > 0 ? { texto, vencida } : null;
}

function senales(...lista: (Senal | null)[]): Senal[] {
    return lista.filter((una): una is Senal => una !== null);
}

const filas = computed<Fila[]>(() => {
    const nc = desempeno.value.noConformidades ?? {};
    const indicadores = desempeno.value.indicadores ?? {};
    const obj = desempeno.value.objetivos ?? {};
    const aud = desempeno.value.auditorias ?? {};
    const anterior = previas.value.revision;

    return [
        {
            ancla: 'entrada-a',
            letra: 'a)',
            titulo: 'Acciones de revisiones previas',
            resumen: anterior
                ? `De la ${anterior.codigo} · ${numero(previas.value.abiertas)} de ${acciones.value.length} siguen abiertas`
                : 'Es la primera revisión registrada',
            senales: senales(
                senal(numero(previas.value.vencidas), plural(numero(previas.value.vencidas), 'vencida', 'vencidas'), true),
            ),
        },
        {
            ancla: 'entrada-b',
            letra: 'b)',
            titulo: 'Cambios en las cuestiones internas y externas',
            resumen: contexto.value.analisis
                ? `${contexto.value.analisis.etiqueta}, aprobado el ${contexto.value.analisis.fecha} · ${plural(numero(contexto.value.cuestiones), 'cuestión', 'cuestiones')}`
                : 'Sin análisis del contexto aprobado',
            senales: contexto.value.analisis ? [] : [{ texto: 'Se aporta fuera', vencida: false }],
        },
        {
            ancla: 'entrada-c',
            letra: 'c) e)',
            titulo: 'Partes interesadas: necesidades y retroalimentación',
            resumen: `${plural(listaPartes.value.length, 'parte', 'partes')} · ${plural(requisitos.value, 'requisito', 'requisitos')}`,
            // La limitación se dice también aquí: quien prepara la reunión tiene
            // que saber de un vistazo que la e) la aporta él.
            senales: [{ texto: 'e) se aporta fuera', vencida: false }],
        },
        {
            ancla: 'entrada-d',
            letra: 'd)',
            titulo: 'Desempeño y eficacia del sistema de gestión',
            resumen: `${plural(numero(nc.abiertas), 'no conformidad abierta', 'no conformidades abiertas')} · ${plural(numero(aud.total), 'auditoría', 'auditorías')} en el periodo`,
            senales: senales(
                senal(numero(nc.vencidas), `${plural(numero(nc.vencidas), 'NC vencida', 'NC vencidas')}`, true),
                senal(numero(obj.vencidos), plural(numero(obj.vencidos), 'objetivo vencido', 'objetivos vencidos'), true),
                senal(numero(nc.sinVerificar), `${numero(nc.sinVerificar)} sin verificar la eficacia`),
                senal(numero(indicadores.fueraDeObjetivo), `${numero(indicadores.fueraDeObjetivo)} fuera de objetivo`),
                senal(numero(indicadores.periodoSinMedir), plural(numero(indicadores.periodoSinMedir), 'indicador sin medir', 'indicadores sin medir')),
            ),
        },
        {
            ancla: 'entrada-f',
            letra: 'f)',
            titulo: 'Apreciación de riesgos y estado del tratamiento',
            resumen: `${plural(numero(riesgos.value.total), 'riesgo', 'riesgos')} · ${numero(riesgos.value.sobreUmbral)} sobre el umbral`,
            senales: senales(
                senal(
                    numero(riesgos.value.revisionVencida),
                    plural(numero(riesgos.value.revisionVencida), 'reevaluación vencida', 'reevaluaciones vencidas'),
                    true,
                ),
                senal(numero(riesgos.value.sobreUmbral), `${numero(riesgos.value.sobreUmbral)} sobre el umbral`),
                senal(numero(riesgos.value.sinAceptar), `${numero(riesgos.value.sinAceptar)} sin aceptar`),
                senal(numero(riesgos.value.sinValorar), `${numero(riesgos.value.sinValorar)} sin valorar`),
            ),
        },
        {
            ancla: 'entrada-g',
            letra: 'g)',
            titulo: 'Oportunidades de mejora continua',
            resumen: `${plural(numero(mejoras.value.total), 'registrada', 'registradas')} · ${numero(mejoras.value.abiertas)} abiertas`,
            senales: senales(senal(numero(mejoras.value.sinEmpezar), `${numero(mejoras.value.sinEmpezar)} sin empezar`)),
        },
    ];
});

/** La c) y e) lleva siempre su limitación: no cuenta como fila que pida atención. */
const conAtencion = computed(
    () => filas.value.filter((fila) => fila.ancla !== 'entrada-c' && fila.senales.length > 0).length,
);

function badge(texto: string): App.Http.Resources.Definicion.ValorEtiquetado {
    return { valor: texto, etiqueta: texto, tono: 'caducada', icono: null };
}

function estado(fila: Record<string, any>): App.Http.Resources.Definicion.ValorEtiquetado {
    return { valor: fila.estado, etiqueta: fila.estado, tono: fila.tono, icono: fila.icono ?? null };
}
</script>

<template>
    <div class="space-y-6">
        <!-- El elemento fuerte de la ficha: lo que hay que mirar en la reunión. -->
        <Card class="gap-0 pb-2">
            <CardHeader class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 pb-4">
                <div class="space-y-1">
                    <CardTitle>
                        {{ congeladas ? 'Lo que la dirección tuvo delante' : 'Lo que la dirección tiene delante' }}
                    </CardTitle>
                    <CardDescription>
                        Las siete entradas de la cláusula 9.3.2, en el orden de la norma. Cada fila lleva a su
                        detalle.
                    </CardDescription>
                </div>
                <p class="text-sm whitespace-nowrap text-muted-foreground">
                    <Cifra class="font-medium text-foreground" :valor="conAtencion" />
                    {{ conAtencion === 1 ? 'pide atención' : 'piden atención' }}
                </p>
            </CardHeader>

            <CardContent class="px-0">
                <ul class="border-t">
                    <li v-for="fila in filas" :key="fila.ancla" class="border-b last:border-b-0">
                        <a
                            :href="`#${fila.ancla}`"
                            class="grid grid-cols-[3.5rem_minmax(0,1fr)_1rem] items-center gap-x-3 gap-y-2 px-6 py-3 transition-colors hover:bg-(--fila-hover) md:grid-cols-[3.5rem_minmax(0,1fr)_minmax(0,17rem)_1rem]"
                        >
                            <span class="cifra text-[13px] text-muted-foreground">{{ fila.letra }}</span>
                            <span class="min-w-0 space-y-0.5">
                                <span class="block text-sm font-medium">{{ fila.titulo }}</span>
                                <span class="block text-xs text-muted-foreground">{{ fila.resumen }}</span>
                            </span>
                            <span
                                class="col-start-2 row-start-2 flex flex-wrap gap-1.5 md:col-start-auto md:row-start-auto"
                            >
                                <template v-for="una in fila.senales" :key="una.texto">
                                    <CeldaBadge v-if="una.vencida" :valor="badge(una.texto)" />
                                    <span
                                        v-else
                                        class="inline-flex h-5 items-center rounded-full bg-muted px-2 text-xs font-medium whitespace-nowrap text-secondary-foreground"
                                    >
                                        {{ una.texto }}
                                    </span>
                                </template>
                                <span v-if="fila.senales.length === 0" class="text-xs text-muted-foreground">
                                    Nada pendiente
                                </span>
                            </span>
                            <ChevronRightIcon
                                class="col-start-3 row-start-1 size-4 text-muted-foreground md:col-start-auto md:row-start-auto"
                                aria-hidden="true"
                            />
                        </a>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Detalle de las entradas</CardTitle>
                <CardDescription>
                    <template v-if="congeladas">
                        Tal como se recogieron al aprobar el acta. No cambian aunque los registros sigan vivos.
                    </template>
                    <template v-else>Lo mismo que imprimirá el acta, apartado por apartado.</template>
                </CardDescription>
            </CardHeader>

            <CardContent class="divide-y">
                <!-- a) -->
                <section id="entrada-a" class="grid scroll-mt-24 grid-cols-[2.5rem_minmax(0,1fr)] gap-4 py-6 first:pt-0 last:pb-0">
                    <span class="cifra text-[13px] leading-6 text-muted-foreground">a)</span>
                    <div class="space-y-3">
                        <div class="space-y-0.5">
                            <h3 class="text-[15px] leading-6 font-semibold">Acciones de revisiones previas</h3>
                            <p v-if="!previas.revision" class="text-[13px] text-muted-foreground">
                                Es la primera revisión registrada: no hay acciones previas que comprobar.
                            </p>
                            <p v-else class="text-[13px] text-muted-foreground">
                                Decididas en la <span class="cifra">{{ previas.revision.codigo }}</span>, celebrada
                                el {{ previas.revision.fecha }}.
                                <Cifra class="font-medium text-foreground" :valor="numero(previas.abiertas)" />
                                de {{ acciones.length }} siguen abiertas.
                            </p>
                        </div>

                        <ul v-if="acciones.length > 0" class="divide-y border-t">
                            <li
                                v-for="(accion, i) in acciones"
                                :key="i"
                                class="grid gap-x-3 gap-y-1 py-3 text-[13px] sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-center"
                            >
                                <span class="font-medium">{{ accion.titulo }}</span>
                                <span class="text-muted-foreground">{{ accion.responsable ?? 'Sin responsable' }}</span>
                                <span class="flex flex-wrap items-center gap-2">
                                    <span v-if="accion.fecha" :class="accion.vencida ? 'text-destructive' : 'text-muted-foreground'">
                                        {{ accion.vencida ? 'Venció el' : 'Vence el' }} {{ accion.fecha }}
                                    </span>
                                    <CeldaBadge :valor="estado(accion)" />
                                </span>
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- b) -->
                <section id="entrada-b" class="grid scroll-mt-24 grid-cols-[2.5rem_minmax(0,1fr)] gap-4 py-6 first:pt-0 last:pb-0">
                    <span class="cifra text-[13px] leading-6 text-muted-foreground">b)</span>
                    <div class="space-y-3">
                        <div class="space-y-0.5">
                            <h3 class="text-[15px] leading-6 font-semibold">Cambios en las cuestiones internas y externas</h3>
                            <p v-if="!contexto.analisis" class="text-[13px] text-muted-foreground">
                                No hay ningún análisis del contexto aprobado. Esta entrada se aporta fuera de la
                                herramienta, o se aprueba el análisis en
                                <Link href="/contexto" class="text-primary underline-offset-4 hover:underline">Contexto</Link>.
                            </p>
                            <p v-else class="text-[13px] text-muted-foreground">
                                {{ contexto.analisis.etiqueta }}, aprobado el {{ contexto.analisis.fecha }}, con
                                <Cifra class="font-medium text-foreground" :valor="numero(contexto.cuestiones)" />
                                cuestiones vigentes.
                            </p>
                        </div>

                        <dl v-if="contexto.analisis && hayDafo" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div
                                v-for="cuadrante in cuadrantes"
                                :key="cuadrante.clave"
                                class="flex flex-col gap-1.5 rounded-lg p-3"
                                :class="tono(`dafo:${cuadrante.clave}`).badge"
                            >
                                <dt class="flex items-center gap-1.5 text-xs font-medium">
                                    <component :is="cuadrante.icono" class="size-3.5" aria-hidden="true" />
                                    {{ cuadrante.etiqueta }}
                                </dt>
                                <dd class="cifra text-xl font-medium">{{ enCuadrante(cuadrante.clave) }}</dd>
                            </div>
                        </dl>

                        <p v-if="contexto.clima" class="border-l-2 pl-3 text-[13px] leading-5 text-secondary-foreground">
                            El cambio climático se ha determinado
                            <strong class="font-semibold">{{ contexto.clima.pertinente ? 'pertinente' : 'no pertinente' }}</strong>
                            para la organización<template v-if="contexto.clima.justificacion">:
                                {{ contexto.clima.justificacion }}</template
                            ><template v-else>.</template>
                        </p>
                    </div>
                </section>

                <!-- c) y e) -->
                <section id="entrada-c" class="grid scroll-mt-24 grid-cols-[2.5rem_minmax(0,1fr)] gap-4 py-6 first:pt-0 last:pb-0">
                    <span class="cifra text-[13px] leading-6 text-muted-foreground">c) e)</span>
                    <div class="space-y-3">
                        <div class="space-y-0.5">
                            <h3 class="text-[15px] leading-6 font-semibold">Partes interesadas: necesidades y retroalimentación</h3>
                            <p v-if="listaPartes.length > 0" class="text-[13px] text-muted-foreground">
                                <Cifra class="font-medium text-foreground" :valor="listaPartes.length" />
                                partes interesadas con
                                <Cifra class="font-medium text-foreground" :valor="requisitos" />
                                requisitos registrados.
                            </p>
                        </div>

                        <EstadoVacio
                            v-if="listaPartes.length === 0"
                            titulo="Sin partes interesadas"
                            descripcion="La cláusula 4.2 pide determinarlas, y la 9.3 revisar si han cambiado."
                        />
                        <ul v-else class="divide-y border-t">
                            <li
                                v-for="(parte, i) in listaPartes"
                                :key="i"
                                class="grid gap-x-3 gap-y-0.5 py-2.5 text-[13px] sm:grid-cols-[minmax(0,1fr)_14rem_6.5rem] sm:items-center"
                            >
                                <span class="font-medium">{{ parte.nombre }}</span>
                                <span class="text-muted-foreground">{{ parte.tipo }} · {{ parte.ambito }}</span>
                                <span class="text-muted-foreground sm:text-right">
                                    <span class="cifra text-foreground">{{ parte.requisitos }}</span>
                                    {{ parte.requisitos === 1 ? 'requisito' : 'requisitos' }}
                                </span>
                            </li>
                        </ul>

                        <!--
                            La limitación se dice aquí y no sólo en el PDF: quien prepara la
                            reunión tiene que saber que esta entrada la aporta él.
                        -->
                        <p class="border-l-2 pl-3 text-[13px] leading-5 text-secondary-foreground">
                            <strong class="font-semibold">La retroalimentación (e) se aporta fuera.</strong>
                            Quejas, encuestas y comunicaciones recibidas no se registran en Statera: se traen a la
                            reunión y se recogen en las conclusiones.
                        </p>
                    </div>
                </section>

                <!-- d) -->
                <section id="entrada-d" class="grid scroll-mt-24 grid-cols-[2.5rem_minmax(0,1fr)] gap-4 py-6 first:pt-0 last:pb-0">
                    <span class="cifra text-[13px] leading-6 text-muted-foreground">d)</span>
                    <div class="space-y-4">
                        <h3 class="text-[15px] leading-6 font-semibold">Desempeño y eficacia del sistema de gestión</h3>

                        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border bg-border sm:grid-cols-3">
                            <div class="flex flex-col gap-1 bg-card px-4 py-3.5">
                                <dt class="text-xs text-muted-foreground">No conformidades abiertas</dt>
                                <dd class="flex items-baseline gap-1.5">
                                    <Cifra class="cifra text-[22px] font-medium" :valor="numero(desempeno.noConformidades?.abiertas)" />
                                    <span class="text-[13px] text-muted-foreground">de {{ numero(desempeno.noConformidades?.total) }}</span>
                                </dd>
                                <dd
                                    v-if="numero(desempeno.noConformidades?.vencidas) > 0"
                                    class="text-xs text-destructive"
                                >
                                    {{ plural(numero(desempeno.noConformidades?.vencidas), 'vencida', 'vencidas') }}
                                </dd>
                            </div>
                            <div class="flex flex-col gap-1 bg-card px-4 py-3.5">
                                <dt class="text-xs text-muted-foreground">Sin verificar la eficacia</dt>
                                <dd class="flex items-baseline gap-1.5"><Cifra class="cifra text-[22px] font-medium" :valor="numero(desempeno.noConformidades?.sinVerificar)" /></dd>
                            </div>
                            <div class="flex flex-col gap-1 bg-card px-4 py-3.5">
                                <dt class="text-xs text-muted-foreground">Indicadores fuera de objetivo</dt>
                                <dd class="flex items-baseline gap-1.5">
                                    <Cifra class="cifra text-[22px] font-medium" :valor="numero(desempeno.indicadores?.fueraDeObjetivo)" />
                                    <span class="text-[13px] text-muted-foreground">de {{ numero(desempeno.indicadores?.activos) }}</span>
                                </dd>
                            </div>
                            <div class="flex flex-col gap-1 bg-card px-4 py-3.5">
                                <dt class="text-xs text-muted-foreground">Con el periodo sin medir</dt>
                                <dd class="flex items-baseline gap-1.5">
                                    <Cifra class="cifra text-[22px] font-medium" :valor="numero(desempeno.indicadores?.periodoSinMedir)" />
                                    <span class="text-[13px] text-muted-foreground">de {{ numero(desempeno.indicadores?.activos) }}</span>
                                </dd>
                            </div>
                            <div class="flex flex-col gap-1 bg-card px-4 py-3.5">
                                <dt class="text-xs text-muted-foreground">Auditorías en el periodo</dt>
                                <dd class="flex items-baseline gap-1.5"><Cifra class="cifra text-[22px] font-medium" :valor="numero(desempeno.auditorias?.total)" /></dd>
                            </div>
                            <div class="flex flex-col gap-1 bg-card px-4 py-3.5">
                                <dt class="text-xs text-muted-foreground">Objetivos en curso</dt>
                                <dd class="flex items-baseline gap-1.5">
                                    <Cifra class="cifra text-[22px] font-medium" :valor="numero(desempeno.objetivos?.vivos)" />
                                    <span class="text-[13px] text-muted-foreground">de {{ numero(desempeno.objetivos?.total) }}</span>
                                </dd>
                            </div>
                        </dl>

                        <div class="space-y-1.5">
                            <h4 class="text-[13px] font-semibold">Auditorías del periodo</h4>
                            <ul v-if="auditorias.length > 0" class="divide-y border-t">
                                <li
                                    v-for="(auditoria, i) in auditorias"
                                    :key="i"
                                    class="grid gap-x-3 gap-y-1 py-2.5 text-[13px] sm:grid-cols-[7rem_8rem_minmax(0,1fr)_7rem] sm:items-center"
                                >
                                    <span class="cifra">{{ auditoria.codigo }}</span>
                                    <span><CeldaBadge :valor="estado(auditoria)" /></span>
                                    <span class="text-muted-foreground">{{ auditoria.tipo }} · {{ auditoria.fecha }}</span>
                                    <span class="text-muted-foreground sm:text-right">
                                        <span class="cifra text-foreground">{{ auditoria.hallazgos }}</span>
                                        {{ auditoria.hallazgos === 1 ? 'hallazgo' : 'hallazgos' }}
                                    </span>
                                </li>
                            </ul>
                            <p v-else class="text-[13px] text-muted-foreground">
                                No se celebró ninguna auditoría dentro del periodo revisado.
                            </p>
                        </div>

                        <div v-if="objetivos.length > 0" class="space-y-1.5">
                            <h4 class="text-[13px] font-semibold">Objetivos de seguridad (6.2)</h4>
                            <ul class="divide-y border-t">
                                <li
                                    v-for="(objetivo, i) in objetivos"
                                    :key="i"
                                    class="grid gap-x-3 gap-y-1 py-2.5 text-[13px] sm:grid-cols-[7rem_8rem_minmax(0,1fr)_7rem] sm:items-center"
                                >
                                    <span class="cifra">{{ objetivo.codigo }}</span>
                                    <span><CeldaBadge :valor="estado(objetivo)" /></span>
                                    <span>{{ objetivo.titulo }}</span>
                                    <span class="text-muted-foreground sm:text-right">{{ objetivo.avance }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- f) -->
                <section id="entrada-f" class="grid scroll-mt-24 grid-cols-[2.5rem_minmax(0,1fr)] gap-4 py-6 first:pt-0 last:pb-0">
                    <span class="cifra text-[13px] leading-6 text-muted-foreground">f)</span>
                    <div class="space-y-3">
                        <h3 class="text-[15px] leading-6 font-semibold">Apreciación de riesgos y estado del tratamiento</h3>

                        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border bg-border sm:grid-cols-4">
                            <div class="flex flex-col gap-1 bg-card px-4 py-3.5">
                                <dt class="text-xs text-muted-foreground">Registrados</dt>
                                <dd class="flex items-baseline gap-1.5"><Cifra class="cifra text-[22px] font-medium" :valor="numero(riesgos.total)" /></dd>
                            </div>
                            <div class="flex flex-col gap-1 bg-card px-4 py-3.5">
                                <dt class="text-xs text-muted-foreground">Sobre el umbral</dt>
                                <dd class="flex items-baseline gap-1.5"><Cifra class="cifra text-[22px] font-medium" :valor="numero(riesgos.sobreUmbral)" /></dd>
                            </div>
                            <div class="flex flex-col gap-1 bg-card px-4 py-3.5">
                                <dt class="text-xs text-muted-foreground">Sin aceptar</dt>
                                <dd class="flex items-baseline gap-1.5"><Cifra class="cifra text-[22px] font-medium" :valor="numero(riesgos.sinAceptar)" /></dd>
                            </div>
                            <div class="flex flex-col gap-1 bg-card px-4 py-3.5">
                                <dt class="text-xs text-muted-foreground">Reevaluación vencida</dt>
                                <dd class="flex items-baseline gap-1.5">
                                    <Cifra
                                        class="cifra text-[22px] font-medium"
                                        :class="numero(riesgos.revisionVencida) > 0 ? 'text-destructive' : ''"
                                        :valor="numero(riesgos.revisionVencida)"
                                    />
                                </dd>
                            </div>
                        </dl>

                        <p v-if="numero(riesgos.sinValorar) > 0" class="text-[13px] text-muted-foreground">
                            {{ plural(numero(riesgos.sinValorar), 'riesgo sin valorar todavía', 'riesgos sin valorar todavía') }}.
                        </p>
                    </div>
                </section>

                <!-- g) -->
                <section id="entrada-g" class="grid scroll-mt-24 grid-cols-[2.5rem_minmax(0,1fr)] gap-4 py-6 first:pt-0 last:pb-0">
                    <span class="cifra text-[13px] leading-6 text-muted-foreground">g)</span>
                    <div class="space-y-3">
                        <div class="space-y-0.5">
                            <h3 class="text-[15px] leading-6 font-semibold">Oportunidades de mejora continua</h3>
                            <p class="text-[13px] text-muted-foreground">
                                <Cifra class="font-medium text-foreground" :valor="numero(mejoras.total)" />
                                registradas ·
                                <Cifra class="font-medium text-foreground" :valor="numero(mejoras.abiertas)" />
                                abiertas ·
                                <Cifra class="font-medium text-foreground" :valor="numero(mejoras.sinEmpezar)" />
                                sin empezar
                            </p>
                        </div>

                        <ul v-if="listaMejoras.length > 0" class="divide-y border-t">
                            <li
                                v-for="(mejora, i) in listaMejoras"
                                :key="i"
                                class="grid gap-x-3 gap-y-1 py-2.5 text-[13px] sm:grid-cols-[7rem_8rem_minmax(0,1fr)_11rem] sm:items-center"
                            >
                                <span class="cifra">{{ mejora.codigo }}</span>
                                <span><CeldaBadge :valor="estado(mejora)" /></span>
                                <span>{{ mejora.titulo }}</span>
                                <span class="text-muted-foreground">{{ mejora.origen }}</span>
                            </li>
                        </ul>
                    </div>
                </section>
            </CardContent>
        </Card>
    </div>
</template>

