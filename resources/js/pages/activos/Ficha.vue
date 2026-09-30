<script setup lang="ts">
import Aviso from '@/components/Aviso.vue';
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import BloqueRiesgos, { type RiesgoDelActivo } from '@/components/activo/BloqueRiesgos.vue';
import ComparativaValoracion, {
    type ValoracionSerializada,
} from '@/components/activo/ComparativaValoracion.vue';
import EtiquetaQr from '@/components/activo/EtiquetaQr.vue';
import GrafoDependencias, { type ActivoDelGrafo } from '@/components/activo/GrafoDependencias.vue';
import CampoSelect from '@/components/formulario/CampoSelect.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import CeldaBadge from '@/components/tabla/celdas/CeldaBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { distanciaLegible, formatoFecha } from '@/lib/celdas';
import type { Opcion } from '@/lib/formularios';
import { ArrowDownIcon, ArrowRightIcon, InfoIcon, NetworkIcon, PlusIcon } from '@lucide/vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import { computed, ref } from 'vue';

interface Motivo {
    activo: ActivoDelGrafo;
    dimensiones: { codigo: string; nombre: string }[];
}

const props = defineProps<{
    activo: {
        id: number;
        codigo: string;
        nombre: string;
        descripcion: string | null;
        tipo: string;
        tipoEtiqueta: string;
        tipoIcono: string;
        subtipo: string | null;
        marca_modelo: string | null;
        especificaciones: string | null;
        sistema_operativo: string | null;
        fin_soporte_so: string | null;
        identificador: string | null;
        propietario: string | null;
        custodio: string | null;
        departamento: string | null;
        ubicacion: string | null;
        proveedor: { id: number; codigo: string; nombre: string } | null;
        fin_garantia: string | null;
        estado_ciclo_vida: string;
        estadoEtiqueta: string;
        estadoTono: string;
        estadoIcono: string;
        clasificacion: string;
        clasificacionEtiqueta: string;
        clasificacionTono: string;
        clasificacionIcono: string;
        cifrado: string;
        cifradoEtiqueta: string;
        cifradoTono: string;
        cifradoIcono: string;
        copia_seguridad: string;
        copiaEtiqueta: string;
        copiaTono: string;
        copiaIcono: string;
        ultima_revision: string | null;
        sinRevisar: boolean;
        llevaEtiqueta: boolean;
        observaciones: string | null;
        esperaBorradoSeguro: boolean;
        fecha_alta: string | null;
        fecha_baja: string | null;
        borrado_seguro_en: string | null;
        nota_baja: string | null;
        sistemas: { id: number; codigo: string; nombre: string }[];
    };
    etiqueta: { svg: string; url: string } | null;
    avisoSoporte: string | null;
    valoracionPropia: ValoracionSerializada;
    valoracionEfectiva: ValoracionSerializada;
    motivos: Motivo[];
    dependeDe: ActivoDelGrafo[];
    dependientes: ActivoDelGrafo[];
    /** Vacío y con el bloque escondido si quien mira no tiene `riesgos.ver`. */
    puedeVerRiesgos: boolean;
    riesgos: RiesgoDelActivo[];
    candidatos: Opcion[];
    vulnerabilidades: { id: number; codigo: string; titulo: string; severidad: string; severidadTono: string; fueraDePlazo: boolean; aceptada: { etiqueta: string; tono: string; icono: string } | null }[];
    puedeGestionar: boolean;
    puedeVerVulnerabilidades: boolean;
    puedeRegistrarVulnerabilidad: boolean;
}>();

const { variantesEntrada } = useMovimientoReducido();

/**
 * Une una lista en prosa: «a, b y c».
 *
 * Con `join(', ')` la frase salía «Sube en disponibilidad, trazabilidad», que se
 * lee como una lista truncada. La copia de la aplicación se escribe entera
 * (DESIGN.md §13), y eso incluye la conjunción.
 */
function enumerar(elementos: string[]): string {
    if (elementos.length <= 1) {
        return elementos[0] ?? '';
    }

    return `${elementos.slice(0, -1).join(', ')} y ${elementos[elementos.length - 1]}`;
}

const fecha = (valor: string | null): string => (valor ? formatoFecha.format(new Date(valor)) : '—');

const abierto = ref(false);
const vincular = useForm({ depende_de_id: undefined as string | undefined, nota: '' });

/**
 * El texto que explica la diferencia entre lo valorado y lo efectivo.
 *
 * Se escribe con nombres de activos y no con un «heredado» a secas: quien mira
 * esta ficha necesita saber a quién preguntarle, no que la cifra la puso el
 * sistema.
 */
const explicacionHerencia = computed<string | null>(() => {
    if (props.motivos.length === 0) {
        return null;
    }

    const nombres = enumerar(props.motivos.map((motivo) => motivo.activo.codigo));
    const dimensiones = enumerar([
        ...new Set(props.motivos.flatMap((motivo) => motivo.dimensiones.map((una) => una.nombre.toLowerCase()))),
    ]);

    return `Sube en ${dimensiones} porque ${nombres} se apoya${props.motivos.length === 1 ? '' : 'n'} en este activo.`;
});

/** De quién hereda cada dimensión, para que la tabla lo diga fila a fila. */
const origenes = computed<Record<string, string[]>>(() => {
    const mapa: Record<string, string[]> = {};

    for (const motivo of props.motivos) {
        for (const dimension of motivo.dimensiones) {
            (mapa[dimension.codigo] ??= []).push(motivo.activo.codigo);
        }
    }

    return mapa;
});

/**
 * Lo que le falta a la ficha para poder defenderse en una auditoría.
 *
 * Estaba repartido por la columna lateral —un «Sin asignar» aquí, un «Por
 * confirmar» allá— y se leía como un dato más. Delante, y sólo cuando falta:
 * una tira que dijera «todo completo» sería la fila de ceros del inventario
 * otra vez. «Por confirmar» cuenta porque es una pregunta abierta; «No aplica»
 * no, porque es una respuesta.
 */
const pendientes = computed<string[]>(() =>
    [
        props.activo.propietario === null ? 'Propietario sin asignar' : null,
        props.activo.custodio === null ? 'Custodio sin asignar' : null,
        props.activo.copia_seguridad === 'por_confirmar' ? 'Copia de seguridad por confirmar' : null,
        props.activo.cifrado === 'por_confirmar' ? 'Cifrado en reposo por confirmar' : null,
    ].filter((uno): uno is string => uno !== null),
);

const hayExposicion = computed(() => props.puedeVerRiesgos || props.puedeVerVulnerabilidades);

function confirmar(): void {
    vincular.post(`/activos/${props.activo.id}/dependencias`, {
        preserveScroll: true,
        onSuccess: () => {
            vincular.reset();
            abierto.value = false;
        },
    });
}

function retirar(dependenciaId: number): void {
    router.delete(`/activos/${props.activo.id}/dependencias/${dependenciaId}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :titulo="`${activo.codigo} · ${activo.nombre}`">
        <CabeceraPagina :titulo="activo.nombre" :codigo="activo.codigo" :descripcion="activo.descripcion">
            <!--
                Qué es y cómo está, en una línea bajo el título. Antes era una
                fila suelta de cinco badges del mismo peso donde el tipo, el
                estado, la clasificación y el alcance competían entre sí; la
                clasificación se fue a «Seguridad», que es donde se pregunta.
            -->
            <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1.5 text-sm text-muted-foreground">
                <CeldaBadge
                    :valor="{
                        valor: activo.tipo,
                        etiqueta: activo.tipoEtiqueta,
                        tono: `tipo:${activo.tipo}`,
                        icono: activo.tipoIcono,
                    }"
                />
                <span v-if="activo.subtipo">{{ activo.subtipo }}</span>
                <span aria-hidden="true">·</span>
                <CeldaBadge anunciar
                    :valor="{
                        valor: activo.estado_ciclo_vida,
                        etiqueta: activo.estadoEtiqueta,
                        tono: activo.estadoTono,
                        icono: activo.estadoIcono,
                    }"
                />
                <template v-if="activo.sistemas.length > 0">
                    <span aria-hidden="true">·</span>
                    <span>
                        En el alcance de
                        <template v-for="(sistema, posicion) in activo.sistemas" :key="sistema.id">
                            <span class="cifra text-foreground">{{ sistema.codigo }}</span>
                            {{ sistema.nombre }}<template v-if="posicion < activo.sistemas.length - 1">, </template>
                        </template>
                    </span>
                </template>
            </div>

            <template #acciones>
                <!--
                    El grafo sube a la cabecera: es una subpantalla DE ESTE
                    activo, como Editar, y no una acción de una de sus tarjetas.
                -->
                <Button as-child variant="outline">
                    <Link :href="`/activos/${activo.id}/grafo`">
                        <NetworkIcon class="size-4" aria-hidden="true" />
                        Ver el grafo
                    </Link>
                </Button>
                <Button v-if="puedeGestionar" as-child variant="outline">
                    <Link :href="`/activos/${activo.id}/editar`">Editar</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <motion.div :variants="variantesEntrada" initial="oculto" animate="visible" class="space-y-6">
            <Aviso v-if="avisoSoporte" tono="error" titulo="Fuera de soporte">
                {{ avisoSoporte }} Un sistema que ya no recibe parches es op.exp.4 de la misma manera el día antes
                y el día después de que salga el primer CVE sin arreglo.
                <Link
                    v-if="puedeRegistrarVulnerabilidad"
                    :href="`/vulnerabilidades/crear?activo=${activo.id}`"
                    class="mt-2 block font-medium underline underline-offset-4"
                >
                    Registrarlo como vulnerabilidad
                </Link>
            </Aviso>

            <Aviso v-if="activo.esperaBorradoSeguro" tono="error" titulo="Sin constancia del borrado seguro">
                El activo ya no presta servicio, pero nadie ha registrado qué se hizo con lo que contenía. Hasta que
                esa constancia exista, mp.si.5 sigue sin cumplirse y esto es un hallazgo a la vista de cualquier
                auditor.
            </Aviso>

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
                            :href="`/activos/${activo.id}/editar`"
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
                <p v-if="activo.propietario === null" class="text-xs text-muted-foreground">
                    Sin propietario, nadie responde por él en la auditoría.
                </p>
            </section>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start">
                <div class="space-y-6">
                    <!-- El elemento fuerte de la pantalla (DESIGN.md §1): cuánto vale y por qué. -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Valoración</CardTitle>
                        </CardHeader>

                        <CardContent class="space-y-6">
                            <div class="grid gap-4 sm:grid-cols-[13rem_minmax(0,1fr)] sm:gap-8">
                                <div>
                                    <p class="text-4xl leading-11 font-bold tracking-[-0.02em] text-primary">
                                        {{ valoracionEfectiva.maximoEtiqueta }}
                                    </p>
                                    <p class="text-[13px] text-muted-foreground">
                                        Nivel efectivo más alto ·
                                        <template v-if="valoracionEfectiva.categoriaEtiqueta">
                                            categoría
                                            <strong class="font-medium text-foreground">
                                                {{ valoracionEfectiva.categoriaEtiqueta.toLowerCase() }}
                                            </strong>
                                        </template>
                                        <template v-else>sin categoría</template>
                                    </p>
                                </div>
                                <p class="max-w-prose text-sm leading-6 text-pretty text-secondary-foreground sm:pt-1">
                                    {{
                                        explicacionHerencia ??
                                        'Lo que la organización valoró y lo que el activo vale contando lo que se apoya en él. Aquí coinciden.'
                                    }}
                                </p>
                            </div>

                            <ComparativaValoracion
                                :propia="valoracionPropia"
                                :efectiva="valoracionEfectiva"
                                :origenes="origenes"
                            />
                        </CardContent>
                    </Card>

                    <!--
                        Las dos direcciones en una sola tarjeta y en el orden en
                        que se lee la cadena: lo que se cae con él, él, y lo que
                        necesita. Separadas en dos tarjetas se leían como dos
                        listas sin relación. Siguen siendo listas —el camino con
                        teclado y a 375 px que el grafo no es—, y sólo en pantallas
                        anchas se ponen en fila.
                    -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Dependencias</CardTitle>
                            <CardDescription>
                                Qué se cae si cae, y qué necesita para funcionar. La valoración baja por la cadena hacia
                                lo que necesita.
                            </CardDescription>

                            <CardAction v-if="puedeGestionar">
                                <Button variant="outline" size="sm" @click="abierto = true">
                                    <PlusIcon class="size-4" aria-hidden="true" />
                                    Declarar dependencia
                                </Button>
                            </CardAction>
                        </CardHeader>

                        <CardContent class="space-y-4">
                            <div
                                class="grid gap-3 2xl:grid-cols-[minmax(0,1fr)_auto_minmax(0,12rem)_auto_minmax(0,1fr)] 2xl:items-start"
                            >
                                <div class="space-y-2">
                                    <h3 class="text-xs font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                                        Lo sostiene · <span class="cifra">{{ dependientes.length }}</span>
                                    </h3>
                                    <GrafoDependencias
                                        :activos="dependientes"
                                        :activo-id="activo.id"
                                        vacio="Ningún activo declarado se apoya en éste."
                                    />
                                </div>

                                <div class="flex justify-center text-muted-foreground 2xl:pt-7" aria-hidden="true">
                                    <ArrowDownIcon class="size-5 2xl:hidden" />
                                    <ArrowRightIcon class="hidden size-5 2xl:block" />
                                </div>

                                <div class="rounded-lg border border-primary/30 bg-accent/60 p-3 2xl:mt-6">
                                    <p class="cifra text-xs text-muted-foreground">{{ activo.codigo }} · este activo</p>
                                    <p class="text-sm font-medium">{{ activo.nombre }}</p>
                                </div>

                                <div class="flex justify-center text-muted-foreground 2xl:pt-7" aria-hidden="true">
                                    <ArrowDownIcon class="size-5 2xl:hidden" />
                                    <ArrowRightIcon class="hidden size-5 2xl:block" />
                                </div>

                                <div class="space-y-2">
                                    <h3 class="text-xs font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                                        Depende de · <span class="cifra">{{ dependeDe.length }}</span>
                                    </h3>
                                    <GrafoDependencias
                                        :activos="dependeDe"
                                        :activo-id="activo.id"
                                        :retirable="puedeGestionar"
                                        vacio="No depende de nada declarado. Si en realidad se apoya en un servidor, una red o una base de datos, decláralo: sin el grafo, la valoración no se propaga y el análisis de impacto se queda sin respuesta."
                                        @retirar="retirar"
                                    />
                                </div>
                            </div>

                            <p class="text-[13px] text-muted-foreground">
                                Cadenas largas y activos compartidos, en
                                <Link :href="`/activos/${activo.id}/grafo`" class="text-primary underline-offset-4 hover:underline">
                                    el grafo de este activo</Link>.
                            </p>
                        </CardContent>
                    </Card>

                    <!--
                        Riesgos y vulnerabilidades en una sola tarjeta: las dos
                        contestan a lo mismo —contra qué hay que proteger este
                        activo—, y cada mitad sólo si quien mira puede verla.
                    -->
                    <Card v-if="hayExposicion">
                        <CardHeader>
                            <CardTitle>A qué está expuesto</CardTitle>
                            <CardDescription>
                                Los riesgos del registro que pesan sobre este activo y las vulnerabilidades que siguen en
                                él. El impacto de los riesgos se deduce de la valoración efectiva, así que lo que hereda
                                por el grafo también los sube.
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="space-y-6">
                            <div v-if="puedeVerRiesgos" class="space-y-3">
                                <h3 class="text-xs font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                                    Riesgos · <span class="cifra">{{ riesgos.length }}</span>
                                </h3>
                                <BloqueRiesgos :riesgos="riesgos" :activo-id="activo.id" />
                            </div>

                            <div v-if="puedeVerVulnerabilidades" class="space-y-3">
                                <h3 class="text-xs font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                                    Vulnerabilidades · <span class="cifra">{{ vulnerabilidades.length }}</span>
                                </h3>
                                <ul v-if="vulnerabilidades.length > 0" class="divide-y divide-border">
                                    <li
                                        v-for="una in vulnerabilidades"
                                        :key="una.id"
                                        class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm first:pt-0"
                                    >
                                        <Link
                                            :href="`/vulnerabilidades/${una.id}`"
                                            class="min-w-0 font-medium underline-offset-4 hover:underline"
                                        >
                                            <span class="cifra text-muted-foreground">{{ una.codigo }}</span>
                                            {{ una.titulo }}
                                        </Link>
                                        <span class="flex flex-wrap items-center gap-1.5">
                                            <CeldaBadge
                                                :valor="{ valor: una.severidad, etiqueta: una.severidad, tono: una.severidadTono }"
                                            />
                                            <CeldaBadge v-if="una.aceptada" :valor="{ valor: 'aceptada', ...una.aceptada }" />
                                            <CeldaBadge
                                                v-if="una.fueraDePlazo"
                                                :valor="{
                                                    valor: 'fuera',
                                                    etiqueta: 'Fuera de plazo',
                                                    tono: 'caducada',
                                                    icono: 'TriangleAlert',
                                                }"
                                            />
                                        </span>
                                    </li>
                                </ul>
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p v-if="vulnerabilidades.length === 0" class="text-sm text-muted-foreground">
                                        Ninguna pendiente. Que no haya ninguna apuntada no significa que no las tenga.
                                    </p>
                                    <Button
                                        v-if="puedeRegistrarVulnerabilidad"
                                        as-child
                                        variant="outline"
                                        size="sm"
                                        class="ml-auto"
                                    >
                                        <Link :href="`/vulnerabilidades/crear?activo=${activo.id}`">Registrar una</Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- La columna lateral en el orden de DESIGN.md §9: Estado, Ficha y el resto. -->
                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Estado</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <dl class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-4 gap-y-3 text-sm">
                                <dt class="text-muted-foreground">Ciclo de vida</dt>
                                <dd class="justify-self-end">
                                    <CeldaBadge
                                        :valor="{
                                            valor: activo.estado_ciclo_vida,
                                            etiqueta: activo.estadoEtiqueta,
                                            tono: activo.estadoTono,
                                            icono: activo.estadoIcono,
                                        }"
                                    />
                                </dd>

                                <dt class="text-muted-foreground">Alta</dt>
                                <dd class="justify-self-end">{{ fecha(activo.fecha_alta) }}</dd>

                                <template v-if="activo.fin_garantia">
                                    <dt class="text-muted-foreground">Fin de garantía</dt>
                                    <dd class="justify-self-end text-right">
                                        {{ fecha(activo.fin_garantia) }}
                                        <span class="block text-xs text-muted-foreground">
                                            {{ distanciaLegible(activo.fin_garantia) }}
                                        </span>
                                    </dd>
                                </template>

                                <template v-if="activo.fecha_baja">
                                    <dt class="text-muted-foreground">Baja</dt>
                                    <dd class="justify-self-end">{{ fecha(activo.fecha_baja) }}</dd>
                                </template>

                                <template v-if="activo.borrado_seguro_en">
                                    <dt class="text-muted-foreground">Borrado seguro</dt>
                                    <dd class="justify-self-end">{{ fecha(activo.borrado_seguro_en) }}</dd>
                                    <dd v-if="activo.nota_baja" class="col-span-2 text-muted-foreground">
                                        {{ activo.nota_baja }}
                                    </dd>
                                </template>

                                <dt class="text-muted-foreground">Última revisión</dt>
                                <dd class="justify-self-end text-right">
                                    <CeldaBadge
                                        v-if="activo.sinRevisar"
                                        :valor="{
                                            valor: activo.ultima_revision,
                                            etiqueta: activo.ultima_revision ? fecha(activo.ultima_revision) : 'Nunca',
                                            tono: 'caducada',
                                        }"
                                    />
                                    <template v-else>{{ fecha(activo.ultima_revision) }}</template>
                                    <span v-if="activo.ultima_revision" class="block text-xs text-muted-foreground">
                                        {{ distanciaLegible(activo.ultima_revision) }}
                                    </span>
                                </dd>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Ficha</CardTitle>
                        </CardHeader>

                        <CardContent class="space-y-5 text-sm">
                            <div class="space-y-3">
                                <h3 class="text-xs font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                                    Responsables
                                </h3>
                                <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-3">
                                    <dt class="text-muted-foreground">Propietario</dt>
                                    <dd class="justify-self-end text-right" :class="{ 'text-muted-foreground': !activo.propietario }">
                                        {{ activo.propietario ?? 'Sin asignar' }}
                                    </dd>
                                    <dt class="text-muted-foreground">Custodio</dt>
                                    <dd class="justify-self-end text-right" :class="{ 'text-muted-foreground': !activo.custodio }">
                                        {{ activo.custodio ?? 'Sin asignar' }}
                                    </dd>
                                    <template v-if="activo.departamento">
                                        <dt class="text-muted-foreground">Departamento</dt>
                                        <dd class="justify-self-end text-right">{{ activo.departamento }}</dd>
                                    </template>
                                    <template v-if="activo.proveedor">
                                        <dt class="text-muted-foreground">Lo presta</dt>
                                        <dd class="justify-self-end text-right">
                                            <Link
                                                :href="`/proveedores/${activo.proveedor.id}`"
                                                class="text-primary underline-offset-4 hover:underline"
                                            >
                                                {{ activo.proveedor.nombre }}
                                            </Link>
                                        </dd>
                                    </template>
                                </dl>
                            </div>

                            <div class="border-t" />

                            <div class="space-y-3">
                                <h3 class="text-xs font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                                    Técnica
                                </h3>
                                <dl class="grid gap-3">
                                    <div>
                                        <dt class="text-xs text-muted-foreground">Nº de serie / identificador</dt>
                                        <dd class="cifra mt-0.5 text-[13px] break-all">{{ activo.identificador ?? '—' }}</dd>
                                    </div>
                                    <div v-if="activo.marca_modelo">
                                        <dt class="text-xs text-muted-foreground">Marca y modelo</dt>
                                        <dd class="mt-0.5">{{ activo.marca_modelo }}</dd>
                                    </div>
                                    <div v-if="activo.especificaciones">
                                        <dt class="text-xs text-muted-foreground">Especificaciones</dt>
                                        <dd class="mt-0.5">{{ activo.especificaciones }}</dd>
                                    </div>
                                    <div v-if="activo.sistema_operativo">
                                        <dt class="text-xs text-muted-foreground">Sistema operativo</dt>
                                        <dd class="mt-0.5">
                                            {{ activo.sistema_operativo }}
                                            <span v-if="activo.fin_soporte_so" class="block text-xs text-muted-foreground">
                                                Soporte hasta el {{ fecha(activo.fin_soporte_so) }}
                                                ({{ distanciaLegible(activo.fin_soporte_so) }})
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-muted-foreground">Ubicación</dt>
                                        <dd class="mt-0.5">{{ activo.ubicacion ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="border-t" />

                            <div class="space-y-3">
                                <h3 class="text-xs font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                                    Alcance
                                </h3>
                                <ul v-if="activo.sistemas.length > 0" class="grid gap-1">
                                    <li v-for="sistema in activo.sistemas" :key="sistema.id">
                                        <span class="cifra text-muted-foreground">{{ sistema.codigo }}</span>
                                        {{ sistema.nombre }}
                                    </li>
                                </ul>
                                <p v-else class="text-muted-foreground">No está declarado en el alcance de ningún sistema.</p>
                            </div>

                            <template v-if="activo.observaciones">
                                <div class="border-t" />
                                <div class="space-y-2">
                                    <h3 class="text-xs font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                                        Observaciones
                                    </h3>
                                    <p class="text-pretty">{{ activo.observaciones }}</p>
                                </div>
                            </template>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Seguridad</CardTitle>
                            <CardDescription>
                                «Por confirmar» no es «no»: significa que nadie lo ha comprobado todavía.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <dl class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-4 gap-y-3 text-sm">
                                <dt class="text-muted-foreground">Copia de seguridad</dt>
                                <dd class="justify-self-end">
                                    <CeldaBadge
                                        :valor="{
                                            valor: activo.copia_seguridad,
                                            etiqueta: activo.copiaEtiqueta,
                                            tono: activo.copiaTono,
                                            icono: activo.copiaIcono,
                                        }"
                                    />
                                </dd>
                                <dt class="text-muted-foreground">Cifrado en reposo</dt>
                                <dd class="justify-self-end">
                                    <CeldaBadge
                                        :valor="{
                                            valor: activo.cifrado,
                                            etiqueta: activo.cifradoEtiqueta,
                                            tono: activo.cifradoTono,
                                            icono: activo.cifradoIcono,
                                        }"
                                    />
                                </dd>
                                <dt class="text-muted-foreground">Clasificación</dt>
                                <dd class="justify-self-end">
                                    <CeldaBadge
                                        :valor="{
                                            valor: activo.clasificacion,
                                            etiqueta: activo.clasificacionEtiqueta,
                                            tono: activo.clasificacionTono,
                                            icono: activo.clasificacionIcono,
                                        }"
                                    />
                                </dd>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card v-if="etiqueta">
                        <CardHeader>
                            <CardTitle>Etiqueta QR</CardTitle>
                            <CardDescription>
                                La que va pegada en la carcasa. El código no cambia aunque el activo cambie de manos.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <EtiquetaQr :activo-id="activo.id" :svg="etiqueta.svg" :url="etiqueta.url" />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </motion.div>

        <Dialog v-model:open="abierto">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Declarar de qué depende {{ activo.codigo }}</DialogTitle>
                    <DialogDescription>
                        La dirección importa: aquí se elige lo que este activo NECESITA. Su valoración bajará hasta
                        ello, no al revés.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-5">
                    <CampoSelect
                        v-model="vincular.depende_de_id"
                        nombre="depende_de_id"
                        etiqueta="Depende de"
                        :opciones="candidatos"
                        :error="vincular.errors.depende_de_id"
                        placeholder="Elige un activo"
                        requerido
                    />

                    <CampoTextarea
                        v-model="vincular.nota"
                        nombre="nota"
                        etiqueta="Qué clase de dependencia"
                        :filas="2"
                        :error="vincular.errors.nota"
                        ayuda="«Se ejecuta sobre», «almacena en», «se comunica por». El BIA necesita saber cuál es, no sólo que la hay."
                    />
                </div>

                <DialogFooter>
                    <Button variant="ghost" @click="abierto = false">Cancelar</Button>
                    <Button :disabled="vincular.processing || !vincular.depende_de_id" @click="confirmar">
                        {{ vincular.processing ? 'Guardando…' : 'Declarar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
