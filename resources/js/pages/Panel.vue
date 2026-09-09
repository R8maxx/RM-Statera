<script setup lang="ts">
import AnilloProgreso from '@/components/AnilloProgreso.vue';
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import GraficaBarras, { type Barra } from '@/components/grafica/GraficaBarras.vue';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon, ServerIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed } from 'vue';

type AvanceMarco = App.Http.Resources.Panel.AvanceMarco;
type ResumenPanel = App.Http.Resources.Panel.ResumenPanel;
type SegmentoEstado = App.Http.Resources.Panel.SegmentoEstado;
type SistemaResumido = App.Http.Resources.Panel.SistemaResumido;

/*
 * Los tipos se generan desde PHP (`composer types`). Antes esta página
 * declaraba a mano su propia versión de la fila de sistema, y una columna que
 * cambiara de nombre en el controlador no rompía nada aquí hasta que alguien
 * abría el panel.
 */
const props = defineProps<{
    sistemas: SistemaResumido[];
    resumen: ResumenPanel;
    porEstado: SegmentoEstado[];
    porMarco: AvanceMarco[];
}>();

const { variantesEntrada, variantesEscalonado } = useMovimientoReducido();
const escalonado = variantesEscalonado(0.05);

const porcentaje = (implantadas: number, aplicables: number): number =>
    aplicables === 0 ? 0 : Math.round((implantadas / aplicables) * 100);

const porcentajeGlobal = computed(() => porcentaje(props.resumen.implantadas, props.resumen.aplicables));

/*
 * El reparto de una fila de la lista. Ahí sólo hay implantadas y aplicables, y
 * en una barra de 1,5 px un reparto a cuatro tramos es ruido: la fila responde
 * «cuánto llevas», y el detalle por estado está en la barra de arriba, que sí
 * viene con el recuento real del servidor.
 */
const reparto = (implantadas: number, aplicables: number): Segmento[] => [
    { clave: 'implantado', etiqueta: 'Implantado', valor: implantadas },
    { clave: 'no_iniciado', etiqueta: 'Pendiente', valor: Math.max(aplicables - implantadas, 0) },
];

const barrasPorMarco = computed<Barra[]>(() =>
    props.porMarco.map((marco) => ({
        clave: marco.codigo,
        etiqueta: marco.nombre,
        valor: marco.implantadas,
        de: marco.aplicables,
    })),
);

const madurez = computed(() => {
    if (props.resumen.madurezMedia === null) {
        return { cifra: '—', apoyo: 'sin valorar' };
    }

    return {
        cifra: `L${props.resumen.madurezMedia.toFixed(1).replace('.', ',')}`,
        apoyo: `sobre ${props.resumen.madurezEvaluadas} valoradas`,
    };
});

const metricas = computed(() => [
    { etiqueta: 'Sistemas en alcance', valor: String(props.resumen.sistemas), apoyo: null as string | null },
    { etiqueta: 'Requisitos aplicables', valor: String(props.resumen.aplicables), apoyo: null },
    { etiqueta: 'Pendientes', valor: String(props.resumen.pendientes), apoyo: null },
    { etiqueta: 'Madurez media', valor: madurez.value.cifra, apoyo: madurez.value.apoyo },
]);
</script>

<template>
    <AppLayout titulo="Panel">
        <motion.div :variants="escalonado" initial="oculto" animate="visible" class="space-y-6">
            <!-- El porcentaje global es la cifra que abre la pantalla; las
                 cuatro de apoyo no necesitan caja, porque ahí la elevación no
                 comunica nada. Cinco tarjetas idénticas no tendrían jerarquía. -->
            <motion.section :variants="variantesEntrada">
                <Card>
                    <CardContent class="flex flex-col gap-8 py-2 sm:flex-row sm:items-center sm:gap-12">
                        <AnilloProgreso
                            :valor="porcentajeGlobal"
                            etiqueta="implantado"
                            class="mx-auto sm:mx-0"
                        />

                        <div class="min-w-0 flex-1">
                            <h2 class="text-sm font-medium">Estado de implantación</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ resumen.implantadas }} de {{ resumen.aplicables }} requisitos exigibles están
                                implantados en los sistemas dentro del alcance.
                            </p>

                            <!-- Con leyenda: cuatro tramos no pueden distinguirse
                                 sólo por el color. -->
                            <BarraSegmentada class="mt-5" :segmentos="porEstado" leyenda />

                            <dl class="mt-6 grid grid-cols-2 gap-y-4 sm:grid-cols-4 sm:divide-x sm:divide-border">
                                <div
                                    v-for="(metrica, indice) in metricas"
                                    :key="metrica.etiqueta"
                                    :class="indice > 0 && 'sm:pl-4'"
                                >
                                    <dt class="text-xs text-muted-foreground">{{ metrica.etiqueta }}</dt>
                                    <dd class="cifra mt-0.5 text-2xl font-semibold tracking-tight">
                                        {{ metrica.valor }}
                                    </dd>
                                    <dd v-if="metrica.apoyo" class="text-xs text-muted-foreground">
                                        {{ metrica.apoyo }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </CardContent>
                </Card>
            </motion.section>

            <!-- ── Por marco ──────────────────────────────────────────────── -->
            <motion.section v-if="barrasPorMarco.length > 0" :variants="variantesEntrada">
                <Card>
                    <CardHeader>
                        <CardTitle>Implantación por marco</CardTitle>
                        <CardDescription>
                            Sobre los requisitos exigibles de cada marco. Una evidencia puede contar en varios.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="pt-0">
                        <GraficaBarras :barras="barrasPorMarco" />
                    </CardContent>
                </Card>
            </motion.section>

            <!-- ── Sistemas ───────────────────────────────────────────────── -->
            <motion.section :variants="variantesEntrada">
                <Card>
                    <CardHeader>
                        <CardTitle>Sistemas</CardTitle>
                        <CardDescription>
                            La categoría se deriva de la valoración de las cinco dimensiones.
                        </CardDescription>
                        <CardAction v-if="sistemas.length > 0">
                            <Link
                                href="/sistemas"
                                class="rounded text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Ver todos
                            </Link>
                        </CardAction>
                    </CardHeader>

                    <CardContent class="pt-0">
                        <EstadoVacio
                            v-if="sistemas.length === 0"
                            :icono="ServerIcon"
                            titulo="Todavía no hay ningún sistema"
                            descripcion="Un sistema delimita el alcance: sobre él se valoran las cinco dimensiones y de ahí sale la categoría ENS y el conjunto de requisitos exigibles."
                            :accion="{ etiqueta: 'Dar de alta el primero', href: '/sistemas/crear' }"
                        />

                        <ul v-else class="-mx-2 divide-y divide-border">
                            <li v-for="sistema in sistemas" :key="sistema.id">
                                <Link
                                    :href="`/sistemas/${sistema.id}/editar`"
                                    class="group flex items-center gap-4 rounded-md px-2 py-3.5 transition-colors hover:bg-muted/60"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">
                                            <span class="cifra text-muted-foreground">{{ sistema.codigo }}</span>
                                            <span class="mx-1.5 text-muted-foreground/50">/</span>
                                            {{ sistema.nombre }}
                                        </p>
                                        <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                            {{ sistema.marco ?? 'Sin marco' }}
                                            <template v-if="sistema.categoria">
                                                · categoría {{ sistema.categoria }}
                                            </template>
                                        </p>
                                    </div>

                                    <div class="hidden w-44 shrink-0 sm:block">
                                        <BarraSegmentada
                                            alto="fino"
                                            :segmentos="reparto(sistema.implantadas, sistema.aplicables)"
                                        />
                                        <p class="cifra mt-1.5 text-right text-xs text-muted-foreground">
                                            {{ sistema.implantadas }} / {{ sistema.aplicables }}
                                        </p>
                                    </div>

                                    <span class="cifra w-12 shrink-0 text-right text-sm font-medium">
                                        {{ porcentaje(sistema.implantadas, sistema.aplicables) }}%
                                    </span>

                                    <ChevronRightIcon
                                        class="size-4 shrink-0 text-muted-foreground/50 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-muted-foreground"
                                    />
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </motion.section>
        </motion.div>
    </AppLayout>
</template>
