<script setup lang="ts">
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import Cifra from '@/components/Cifra.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import PrimerosPasos from '@/components/PrimerosPasos.vue';
import CabeceraPanel from '@/components/panel/CabeceraPanel.vue';
import ListaAcciones, { type Accion } from '@/components/panel/ListaAcciones.vue';
import GraficaBarras, { type Barra } from '@/components/grafica/GraficaBarras.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { useRecorrido } from '@/composables/useRecorrido';
import AppLayout from '@/layouts/AppLayout.vue';
import { fechaLegible } from '@/lib/celdas';
import { Link, router } from '@inertiajs/vue3';
import { ChevronRightIcon, CircleAlertIcon, CircleMinusIcon, ClockIcon, PaperclipIcon, ServerIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, onMounted } from 'vue';

type AvanceDominio = App.Http.Resources.Panel.AvanceDominio;
type AvanceMarco = App.Http.Resources.Panel.AvanceMarco;
type ResumenEvidencias = App.Http.Resources.Panel.ResumenEvidencias;
type ResumenPanel = App.Http.Resources.Panel.ResumenPanel;
type SegmentoEstado = App.Http.Resources.Panel.SegmentoEstado;
type SistemaResumido = App.Http.Resources.Panel.SistemaResumido;

/**
 * «¿Cómo vamos con lo exigible?» — la vista por defecto del panel.
 *
 * **Un solo elemento fuerte**, y es una tarjeta con dos mitades: el porcentaje
 * implantado con su fracción real y, a su lado, lo que falta para poder
 * demostrarlo. Antes el anillo abría la pantalla y las cifras de las pruebas
 * vivían en otra tarjeta más abajo, del mismo peso que todo lo demás: la
 * pantalla decía cómo íbamos, pero había que ir a buscar qué hacer.
 *
 * **Sin anillo.** DESIGN.md § 9 lo dice para las gráficas: una cifra sola es una
 * cifra grande con su fracción debajo, no un anillo.
 *
 * Detrás va lo que la sostiene: el avance **por marco y por dominio de
 * control** —un 66 % en el Anexo A puede ser un 88 % en personas y un 57 % en
 * físicos— y los sistemas en los que se mide, en tabla estática.
 *
 * **Cada vista se queda con su propio rojo**, y el conmutador pone un punto en
 * la pestaña que lo tenga. Los tres anclajes del recorrido guiado siguen aquí:
 * la cifra, las pruebas y los sistemas.
 *
 * Los tipos se generan desde PHP (`composer types`).
 */
const props = defineProps<{
    vistas: App.Http.Resources.Panel.VistaPanel[];
    sistemas: SistemaResumido[];
    resumen: ResumenPanel;
    evidencias: ResumenEvidencias;
    porEstado: SegmentoEstado[];
    porMarco: AvanceMarco[];
    porDominio: AvanceDominio[];
    /** Si la ficha está completa; nulo para quien no puede editarla (punto 42). */
    fichaOrganizacion: boolean | null;
}>();

const { reducido, variantesEntrada, variantesEscalonado } = useMovimientoReducido();
const escalonado = variantesEscalonado(0.05);

const { arrancarSiEsLaPrimeraVez } = useRecorrido();

/*
 * Primer arranque es que no haya NADA EXIGIBLE, no que no haya sistemas: un
 * sistema dado de alta y sin valorar sigue sin producir un solo requisito.
 */
const primerArranque = computed(() => props.resumen.aplicables === 0);

onMounted(() => arrancarSiEsLaPrimeraVez('panel'));

const porcentaje = (implantadas: number, aplicables: number): number =>
    aplicables === 0 ? 0 : Math.round((implantadas / aplicables) * 100);

const porcentajeGlobal = computed(() => porcentaje(props.resumen.implantadas, props.resumen.aplicables));

/*
 * El remate del cien por cien, que DESIGN.md § 10 cuenta entre los cuatro
 * momentos: un pulso, una vez, sin bucle y sólo en 100. Lo llevaba el anillo, y
 * se queda con la cifra que lo sustituye.
 */
const completo = computed(() => porcentajeGlobal.value >= 100 && !reducido.value);

const madurez = computed(() => (props.resumen.madurezMedia === null ? null : props.resumen.madurezMedia));

const conPrueba = computed(() => Math.max(props.resumen.implantadas - props.evidencias.implantadasSinEvidencia, 0));

const enProgreso = computed(() => props.porEstado.find((tramo) => tramo.clave === 'en_progreso')?.valor ?? 0);
const planificadas = computed(() => props.porEstado.find((tramo) => tramo.clave === 'planificado')?.valor ?? 0);

/*
 * Lo que falta para demostrarlo, cada cifra con su lista.
 *
 * Sólo dos van en rojo, y sólo cuando no están a cero: una evidencia caducada
 * deja sin prueba al requisito que sostenía, y un requisito implantado sin
 * prueba es un hallazgo esperando a que alguien pregunte. `enlace` apunta al
 * mismo scope con el que se cuenta la cifra, así que la lista enseña
 * exactamente lo que dice el número.
 */
const acciones = computed<Accion[]>(() => [
    {
        clave: 'caducadas',
        etiqueta: 'Evidencias caducadas',
        detalle: 'Ya no prueban el requisito que sostenían.',
        valor: props.evidencias.caducadas,
        href: '/evidencias?filter[caducadas]=1',
        icono: CircleAlertIcon,
        alerta: true,
    },
    {
        clave: 'sin_prueba',
        etiqueta: 'Implantados sin prueba',
        detalle: 'Un auditor los daría por no implantados.',
        valor: props.evidencias.implantadasSinEvidencia,
        href: '/implantaciones?filter[sin_evidencia]=1',
        icono: PaperclipIcon,
        alerta: true,
    },
    {
        clave: 'por_caducar',
        etiqueta: 'Caducan en 30 días',
        detalle: props.evidencias.proximaCaducidad
            ? `La primera, el ${fechaLegible(props.evidencias.proximaCaducidad)}.`
            : null,
        valor: props.evidencias.porCaducar,
        href: '/evidencias?filter[por_caducar]=1',
        icono: ClockIcon,
    },
    {
        clave: 'pendientes',
        etiqueta: 'Pendientes de implantar',
        detalle: `${enProgreso.value} en progreso, ${planificadas.value} planificadas.`,
        valor: props.resumen.pendientes,
        href: '/implantaciones?filter[pendientes]=1',
        icono: CircleMinusIcon,
    },
]);

/*
 * Cada marco con sus dominios. `porDominio` llega plano y ordenado por marco y
 * por el orden del catálogo; aquí sólo se agrupa.
 */
const marcos = computed(() =>
    props.porMarco.map((marco) => ({
        ...marco,
        porcentaje: porcentaje(marco.implantadas, marco.aplicables),
        dominios: props.porDominio
            .filter((dominio) => dominio.marco === marco.codigo)
            .map<Barra>((dominio) => ({
                clave: dominio.codigo,
                etiqueta: `${dominio.codigo} · ${dominio.titulo}`,
                valor: dominio.implantadas,
                de: dominio.aplicables,
            })),
    })),
);

/*
 * El reparto de una fila de sistema. Ahí sólo hay implantadas y aplicables, y
 * en una barra fina un reparto a cuatro tramos es ruido: la fila responde
 * «cuánto llevas», y el detalle por estado está arriba.
 */
const reparto = (implantadas: number, aplicables: number): Segmento[] => [
    { clave: 'implantado', etiqueta: 'Implantado', valor: implantadas },
    { clave: 'no_iniciado', etiqueta: 'Pendiente', valor: Math.max(aplicables - implantadas, 0) },
];
</script>

<template>
    <AppLayout titulo="Panel">
        <!-- En primer arranque no se pinta el conmutador: la pantalla no
             resume, orienta. -->
        <CabeceraPanel :vistas="vistas" :conmutador="!primerArranque" />

        <motion.div :variants="escalonado" initial="oculto" animate="visible" class="space-y-8">
            <motion.section v-if="primerArranque" :variants="variantesEntrada">
                <PrimerosPasos
                    :sistemas="sistemas.length"
                    :aplicables="resumen.aplicables"
                    :evidencias="evidencias.total"
                    :ficha-organizacion="fichaOrganizacion"
                />
            </motion.section>

            <!-- ── El elemento fuerte: el estado y lo que falta ──────────── -->
            <motion.section v-else :variants="variantesEntrada">
                <Card class="gap-0 overflow-hidden py-0 lg:flex-row">
                    <div class="min-w-0 flex-1 space-y-6 p-6 sm:p-8" data-recorrido="anillo-progreso">
                        <div>
                            <h2 class="text-base font-semibold tracking-[-0.01em]">Estado de implantación</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Requisitos exigibles de todos los marcos, en los sistemas dentro del alcance.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-end gap-x-5 gap-y-2">
                            <motion.p
                                class="cifra text-6xl leading-none font-semibold tracking-[-0.03em] sm:text-7xl"
                                :animate="completo ? { scale: [1, 1.04, 1] } : undefined"
                                :transition="{ duration: 0.6 }"
                            >
                                <Cifra :valor="porcentajeGlobal" /><span class="ml-1 text-4xl text-muted-foreground">%</span>
                            </motion.p>
                            <div class="pb-1.5">
                                <p class="font-semibold">
                                    <Cifra class="cifra" :valor="resumen.implantadas" /> de
                                    <Cifra class="cifra" :valor="resumen.aplicables" /> requisitos implantados
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    en {{ resumen.sistemas }} {{ resumen.sistemas === 1 ? 'sistema' : 'sistemas' }}
                                </p>
                            </div>
                        </div>

                        <!-- Con leyenda: cuatro tramos no pueden distinguirse
                             sólo por el color. -->
                        <BarraSegmentada :segmentos="porEstado" leyenda />

                        <dl class="grid grid-cols-2 gap-y-4 border-t pt-5 sm:grid-cols-3 sm:divide-x sm:divide-border">
                            <div>
                                <dt class="text-xs text-muted-foreground">Madurez media</dt>
                                <dd class="cifra mt-0.5 text-lg font-semibold">
                                    <!-- La madurez del CCN se escribe «L4,2». -->
                                    <Cifra v-if="madurez !== null" :valor="madurez" :decimales="1" prefijo="L" />
                                    <span v-else>—</span>
                                </dd>
                                <dd class="text-xs text-muted-foreground">
                                    {{ madurez === null ? 'sin valorar' : `sobre ${resumen.madurezEvaluadas} valoradas` }}
                                </dd>
                            </div>
                            <div class="sm:pl-4" data-recorrido="tarjeta-pruebas">
                                <dt class="text-xs text-muted-foreground">
                                    <Link href="/evidencias" class="underline-offset-4 hover:underline">
                                        Pruebas registradas
                                    </Link>
                                </dt>
                                <dd class="cifra mt-0.5 text-lg font-semibold"><Cifra :valor="evidencias.total" /></dd>
                                <dd class="text-xs text-muted-foreground">
                                    en <span class="cifra">{{ conPrueba }}</span> de
                                    <span class="cifra">{{ resumen.implantadas }}</span> implantados
                                </dd>
                            </div>
                            <div class="col-span-2 sm:col-span-1 sm:pl-4">
                                <dt class="text-xs text-muted-foreground">
                                    <Link href="/sistemas" class="underline-offset-4 hover:underline">
                                        Sistemas en alcance
                                    </Link>
                                </dt>
                                <dd class="cifra mt-0.5 text-lg font-semibold"><Cifra :valor="resumen.sistemas" /></dd>
                            </div>
                        </dl>
                    </div>

                    <div class="border-t bg-background/60 p-6 sm:p-8 lg:w-[26rem] lg:shrink-0 lg:border-t-0 lg:border-l">
                        <ListaAcciones
                            titulo="Lo que falta para demostrarlo"
                            descripcion="Cada cifra abre la lista exacta que cuenta."
                            :acciones="acciones"
                            vacio="Nada pendiente de demostrar."
                        />
                        <EstadoVacio
                            v-if="evidencias.total === 0"
                            class="mt-4"
                            :icono="PaperclipIcon"
                            titulo="Todavía no hay ninguna evidencia"
                            descripcion="Una evidencia se registra una vez y cuenta en todos los marcos donde aplique."
                            :accion="{ etiqueta: 'Registrar la primera', href: '/evidencias/crear' }"
                        />
                    </div>
                </Card>
            </motion.section>

            <!-- ── Por marco y dominio ────────────────────────────────────── -->
            <motion.section v-if="marcos.length > 0" :variants="variantesEntrada" class="space-y-4">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 class="text-base font-semibold tracking-[-0.01em]">Por marco y dominio</h2>
                    <p class="text-sm text-muted-foreground">
                        Una misma evidencia cuenta en los dos marcos cuando están mapeados.
                    </p>
                </div>

                <div class="grid gap-6" :class="marcos.length > 1 && 'lg:grid-cols-2'">
                    <Card v-for="marco in marcos" :key="marco.codigo">
                        <CardHeader>
                            <CardDescription>
                                <span class="cifra rounded-full bg-muted px-2 py-0.5 text-xs text-secondary-foreground">
                                    {{ marco.codigo }}
                                </span>
                            </CardDescription>
                            <CardTitle>{{ marco.nombre }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-6 pt-0">
                            <p class="flex items-baseline gap-3">
                                <Cifra class="cifra text-3xl font-semibold" :valor="marco.porcentaje" :sufijo="' %'" />
                                <span class="text-sm text-muted-foreground">
                                    <span class="cifra">{{ marco.implantadas }}</span> de
                                    <span class="cifra">{{ marco.aplicables }}</span> implantados
                                </span>
                            </p>
                            <div v-if="marco.dominios.length > 0" class="border-t pt-5">
                                <h3 class="mb-4 text-xs font-medium text-muted-foreground">Por dominio de control</h3>
                                <GraficaBarras :barras="marco.dominios" />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </motion.section>

            <!-- ── Sistemas ───────────────────────────────────────────────── -->
            <motion.section :variants="variantesEntrada" class="space-y-4" data-recorrido="tarjeta-sistemas">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="text-base font-semibold tracking-[-0.01em]">Sistemas en alcance</h2>
                    <Link
                        v-if="sistemas.length > 0"
                        href="/sistemas"
                        class="flex items-center gap-1 rounded text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                        Ver todos
                        <ChevronRightIcon class="size-4" aria-hidden="true" />
                    </Link>
                </div>

                <Card v-if="sistemas.length === 0">
                    <CardContent>
                        <EstadoVacio
                            :icono="ServerIcon"
                            titulo="Todavía no hay ningún sistema"
                            descripcion="Un sistema delimita el alcance: sobre él se valoran las cinco dimensiones y de ahí sale la categoría ENS y el conjunto de requisitos exigibles."
                            :accion="primerArranque ? undefined : { etiqueta: 'Dar de alta el primero', href: '/sistemas/crear' }"
                        />
                    </CardContent>
                </Card>

                <!-- Tabla estática (DESIGN.md § 9): unas pocas filas fijas, que
                     no se paginan ni se filtran en servidor. -->
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Sistema</TableHead>
                            <TableHead>Marco</TableHead>
                            <TableHead>Categoría ENS</TableHead>
                            <TableHead class="w-80">Implantación</TableHead>
                            <TableHead class="text-right">Sin prueba</TableHead>
                            <TableHead class="w-10"><span class="sr-only">Abrir</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <!-- La fila entera abre la ficha; el enlace del nombre
                             es el que se alcanza con el teclado. -->
                        <TableRow
                            v-for="sistema in sistemas"
                            :key="sistema.id"
                            class="cursor-pointer"
                            @click="router.visit(`/sistemas/${sistema.id}/editar`)"
                        >
                            <TableCell>
                                <Link
                                    :href="`/sistemas/${sistema.id}/editar`"
                                    class="flex items-baseline gap-2 rounded underline-offset-4 hover:underline"
                                    @click.stop
                                >
                                    <span class="cifra text-xs text-muted-foreground">{{ sistema.codigo }}</span>
                                    <span class="font-medium">{{ sistema.nombre }}</span>
                                </Link>
                            </TableCell>
                            <TableCell class="text-secondary-foreground">{{ sistema.marco ?? 'Sin marco' }}</TableCell>
                            <TableCell :class="!sistema.categoria && 'text-muted-foreground'">
                                {{ sistema.categoria ?? 'Sin valorar' }}
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-3">
                                    <BarraSegmentada
                                        class="flex-1"
                                        alto="fino"
                                        :segmentos="reparto(sistema.implantadas, sistema.aplicables)"
                                    />
                                    <span class="cifra w-16 text-right text-xs text-muted-foreground">
                                        {{ sistema.implantadas }} / {{ sistema.aplicables }}
                                    </span>
                                    <span class="cifra w-12 text-right font-medium">
                                        {{ porcentaje(sistema.implantadas, sistema.aplicables) }}&#8239;%
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell class="text-right">
                                <Link
                                    v-if="sistema.sinPrueba > 0"
                                    :href="`/implantaciones?filter[sin_evidencia]=1&filter[sistema_id]=${sistema.id}`"
                                    class="cifra font-medium text-destructive underline-offset-4 hover:underline"
                                    @click.stop
                                >
                                    {{ sistema.sinPrueba }}
                                </Link>
                                <span v-else class="cifra text-muted-foreground">0</span>
                            </TableCell>
                            <TableCell>
                                <ChevronRightIcon class="size-4 text-muted-foreground/60" aria-hidden="true" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </motion.section>
        </motion.div>
    </AppLayout>
</template>
