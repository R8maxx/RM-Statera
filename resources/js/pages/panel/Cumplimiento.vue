<script setup lang="ts">
import AnilloProgreso from '@/components/AnilloProgreso.vue';
import Cifra from '@/components/Cifra.vue';
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import PrimerosPasos from '@/components/PrimerosPasos.vue';
import CabeceraPanel from '@/components/panel/CabeceraPanel.vue';
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
import { useRecorrido } from '@/composables/useRecorrido';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon, PaperclipIcon, ServerIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, onMounted } from 'vue';

type AvanceMarco = App.Http.Resources.Panel.AvanceMarco;
type ResumenEvidencias = App.Http.Resources.Panel.ResumenEvidencias;
type ResumenPanel = App.Http.Resources.Panel.ResumenPanel;
type SegmentoEstado = App.Http.Resources.Panel.SegmentoEstado;
type SistemaResumido = App.Http.Resources.Panel.SistemaResumido;

/**
 * «¿Cómo vamos con lo exigible?» — la vista por defecto del panel.
 *
 * Es la primera de tres. El panel tenía trece tarjetas apiladas y mezclaba tres
 * preguntas: cómo va el cumplimiento, qué está pasando y de qué organización
 * estamos hablando. Ahora cada una tiene su vista.
 *
 * **Cada vista se queda con su propio rojo**, y el conmutador pone un punto en
 * la pestaña que lo tenga: sin ese punto, repartir el panel escondería un
 * incumplimiento detrás de un clic que nadie da.
 *
 * Los tres anclajes del recorrido guiado se quedan aquí, que es donde estaban:
 * el anillo, las pruebas y los sistemas. El recorrido se ofrece desde `/panel`,
 * que sigue siendo la única pantalla por la que se pasa sí o sí.
 *
 * Los tipos se generan desde PHP (`composer types`). Antes esta página declaraba
 * a mano su propia versión de la fila de sistema, y una columna que cambiara de
 * nombre en el controlador no rompía nada aquí hasta que alguien abría el panel.
 */
const props = defineProps<{
    vistas: App.Http.Resources.Panel.VistaPanel[];
    sistemas: SistemaResumido[];
    resumen: ResumenPanel;
    evidencias: ResumenEvidencias;
    porEstado: SegmentoEstado[];
    porMarco: AvanceMarco[];
}>();

const { variantesEntrada, variantesEscalonado } = useMovimientoReducido();
const escalonado = variantesEscalonado(0.05);

const { arrancarSiEsLaPrimeraVez } = useRecorrido();

/*
 * Primer arranque es que no haya NADA EXIGIBLE, no que no haya sistemas.
 *
 * La diferencia importa: un sistema dado de alta y sin valorar sigue sin
 * producir un solo requisito, así que el panel seguiría enseñando ceros y el
 * anillo seguiría diciendo 0 % de 0. Lo que abre la pantalla mientras tanto es
 * por dónde se sigue.
 */
const primerArranque = computed(() => props.resumen.aplicables === 0);

/*
 * El recorrido se ofrece solo la primera vez, y desde el panel, que es la única
 * pantalla por la que se pasa sí o sí. Que ya se vio se recuerda en el
 * navegador: no se relanza a quien lo cerró.
 */
onMounted(() => arrancarSiEsLaPrimeraVez());

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

/*
 * Las cuatro cifras del repositorio de pruebas.
 *
 * Sólo dos pueden ir en rojo, y sólo cuando de verdad hay algo que mirar: una
 * evidencia caducada deja sin prueba al requisito que sostenía, y un requisito
 * implantado sin ninguna prueba es un hallazgo esperando a que alguien
 * pregunte. Pintar de rojo un cero sería alarmar sin motivo.
 *
 * **Y ahora las tres que se pueden accionar llevan a su lista.** Eran el último
 * callejón sin salida del panel: «3 caducadas», y ahora búscalas. `enlace`
 * apunta al mismo scope con el que se cuenta la cifra, así que la lista enseña
 * exactamente lo que dice el número.
 */
const pruebas = computed(() => [
    { etiqueta: 'Evidencias registradas', valor: props.evidencias.total, alerta: false, enlace: '/evidencias' },
    {
        etiqueta: 'Caducadas',
        valor: props.evidencias.caducadas,
        alerta: props.evidencias.caducadas > 0,
        enlace: '/evidencias?filter[caducadas]=1',
    },
    {
        etiqueta: 'Caducan en 30 días',
        valor: props.evidencias.porCaducar,
        alerta: false,
        enlace: '/evidencias?filter[por_caducar]=1',
    },
    {
        etiqueta: 'Implantados sin prueba',
        valor: props.evidencias.implantadasSinEvidencia,
        alerta: props.evidencias.implantadasSinEvidencia > 0,
        enlace: '/implantaciones?filter[sin_evidencia]=1',
    },
]);

/*
 * El valor viaja como NÚMERO y no como cadena ya formateada: `Cifra` cuenta
 * hasta él al entrar, y para eso necesita el número. `null` es «no hay dato»,
 * que no es lo mismo que cero y se pinta como una raya.
 */
const metricas = computed(() => [
    { etiqueta: 'Sistemas en alcance', valor: props.resumen.sistemas, prefijo: '', decimales: 0, apoyo: null as string | null, enlace: null as string | null },
    { etiqueta: 'Requisitos aplicables', valor: props.resumen.aplicables, prefijo: '', decimales: 0, apoyo: null, enlace: null },
    {
        etiqueta: 'Pendientes',
        valor: props.resumen.pendientes,
        prefijo: '',
        decimales: 0,
        apoyo: null,
        /*
         * La única de las cuatro que lleva a alguna parte, y es la única que
         * pide acción. El filtro apunta al mismo scope con el que se cuenta la
         * cifra, así que la lista enseña exactamente lo que dice el número.
         */
        enlace: '/implantaciones?filter[pendientes]=1',
    },
    {
        etiqueta: 'Madurez media',
        valor: props.resumen.madurezMedia,
        // La madurez del CCN se escribe «L4,2»: la ele va delante del número.
        prefijo: 'L',
        decimales: 1,
        apoyo: madurez.value.apoyo,
        enlace: null,
    },
]);
</script>

<template>
    <AppLayout titulo="Panel">
        <!--
            **En primer arranque no se pinta**, y es el mismo argumento que ya
            estaba escrito dos líneas más abajo: mientras no haya nada exigible
            la pantalla no resume, orienta. Dos pestañas que llevan a vistas
            vacías compiten con lo único que hay que hacer, que es dar de alta
            el primer sistema.
        -->
        <CabeceraPanel :vistas="vistas" :conmutador="!primerArranque" />

        <motion.div :variants="escalonado" initial="oculto" animate="visible" class="space-y-6">
            <!--
                Mientras no haya nada exigible, la cabecera no resume: orienta.
                Un anillo al 0 % sobre un denominador de cero no es un dato
                pequeño, es un dato que no existe, y ocupa el sitio más visible
                de la pantalla.
            -->
            <motion.section v-if="primerArranque" :variants="variantesEntrada">
                <PrimerosPasos
                    :sistemas="sistemas.length"
                    :aplicables="resumen.aplicables"
                    :evidencias="evidencias.total"
                />
            </motion.section>

            <!-- El porcentaje global es la cifra que abre la pantalla; las
                 cuatro de apoyo no necesitan caja, porque ahí la elevación no
                 comunica nada. Cinco tarjetas idénticas no tendrían jerarquía. -->
            <motion.section v-else :variants="variantesEntrada">
                <Card data-recorrido="anillo-progreso">
                    <CardContent class="flex flex-col gap-8 py-2 sm:flex-row sm:items-center sm:gap-12">
                        <AnilloProgreso
                            :valor="porcentajeGlobal"
                            etiqueta="implantado"
                            class="mx-auto sm:mx-0"
                        />

                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-semibold tracking-[-0.01em]">Estado de implantación</h2>
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
                                    <dt class="text-xs text-muted-foreground">
                                        <Link
                                            v-if="metrica.enlace"
                                            :href="metrica.enlace"
                                            class="underline-offset-4 hover:underline"
                                        >
                                            {{ metrica.etiqueta }}
                                        </Link>
                                        <template v-else>{{ metrica.etiqueta }}</template>
                                    </dt>
                                    <dd class="cifra mt-0.5 text-lg font-semibold">
                                        <Cifra
                                            v-if="metrica.valor !== null"
                                            :valor="metrica.valor"
                                            :decimales="metrica.decimales"
                                            :prefijo="metrica.prefijo"
                                        />
                                        <span v-else>—</span>
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

            <!-- ── Pruebas ────────────────────────────────────────────────── -->
            <motion.section :variants="variantesEntrada">
                <Card data-recorrido="tarjeta-pruebas">
                    <CardHeader>
                        <CardTitle>Pruebas</CardTitle>
                        <CardDescription>
                            Lo que separa «lo tenemos hecho» de «lo podemos demostrar», que es lo único que un
                            auditor distingue.
                        </CardDescription>
                        <CardAction>
                            <Link
                                href="/evidencias"
                                class="flex items-center gap-1 rounded text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Ver todas
                                <ChevronRightIcon class="size-4" />
                            </Link>
                        </CardAction>
                    </CardHeader>

                    <CardContent class="pt-0">
                        <!--
                            Sin nada exigible, esta acción es un callejón: no hay
                            implantaciones a las que vincular la prueba, y quien
                            la pulsa aprende que la herramienta le manda a sitios
                            que no sirven. Mientras tanto la cabecera ya lleva la
                            única acción de la pantalla, que además es la que toca.
                        -->
                        <EstadoVacio
                            v-if="evidencias.total === 0"
                            :icono="PaperclipIcon"
                            titulo="Todavía no hay ninguna evidencia"
                            descripcion="Una evidencia se registra una vez y cuenta en todos los marcos donde aplique: la misma captura puede probar un control de ISO y tres medidas del ENS."
                            :accion="
                                primerArranque
                                    ? undefined
                                    : { etiqueta: 'Registrar la primera', href: '/evidencias/crear' }
                            "
                        />

                        <dl v-else class="grid grid-cols-2 gap-y-4 sm:grid-cols-4 sm:divide-x sm:divide-border">
                            <div
                                v-for="(prueba, indice) in pruebas"
                                :key="prueba.etiqueta"
                                :class="indice > 0 && 'sm:pl-4'"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    <Link :href="prueba.enlace" class="underline-offset-4 hover:underline">
                                        {{ prueba.etiqueta }}
                                    </Link>
                                </dt>
                                <dd
                                    class="cifra mt-0.5 text-lg font-semibold"
                                    :class="prueba.alerta && 'text-destructive'"
                                >
                                    <Cifra :valor="prueba.valor" />
                                </dd>
                            </div>
                        </dl>
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
                <Card data-recorrido="tarjeta-sistemas">
                    <CardHeader>
                        <CardTitle>Sistemas</CardTitle>
                        <CardDescription>
                            La categoría se deriva de la valoración de las cinco dimensiones.
                        </CardDescription>
                        <CardAction v-if="sistemas.length > 0">
                            <Link
                                href="/sistemas"
                                class="flex items-center gap-1 rounded text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Ver todos
                                <ChevronRightIcon class="size-4" />
                            </Link>
                        </CardAction>
                    </CardHeader>

                    <CardContent class="pt-0">
                        <EstadoVacio
                            v-if="sistemas.length === 0"
                            :icono="ServerIcon"
                            titulo="Todavía no hay ningún sistema"
                            descripcion="Un sistema delimita el alcance: sobre él se valoran las cinco dimensiones y de ahí sale la categoría ENS y el conjunto de requisitos exigibles."
                            :accion="
                                primerArranque
                                    ? undefined
                                    : { etiqueta: 'Dar de alta el primero', href: '/sistemas/crear' }
                            "
                        />

                        <ul v-else class="-mx-2 divide-y divide-border">
                            <li v-for="sistema in sistemas" :key="sistema.id">
                                <Link
                                    :href="`/sistemas/${sistema.id}/editar`"
                                    class="group flex items-center gap-4 rounded-md px-2 py-3.5 transition-colors hover:bg-fila-hover"
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
                                        {{ porcentaje(sistema.implantadas, sistema.aplicables) }}&#8239;%
                                    </span>

                                    <ChevronRightIcon
                                        class="size-4 shrink-0 text-muted-foreground/50 transition-transform group-hover:translate-x-0.5 group-hover:text-muted-foreground"
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
