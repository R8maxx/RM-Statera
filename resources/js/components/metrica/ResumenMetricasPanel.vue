<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import GraficaBarras, { type Barra } from '@/components/grafica/GraficaBarras.vue';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * El desempeño, de un vistazo (§ 4.14, cláusula 9.1).
 *
 * **Sin anillo de progreso y sin porcentaje de «indicadores en objetivo»**, por
 * el mismo motivo por el que el plan de acción no lleva porcentaje de tareas
 * hechas: esa cifra sube al ponerse objetivos flojos y baja al ponerse
 * ambiciosos, así que mide el listón y no el desempeño. Un indicador que castiga
 * por apuntar lo que falta enseña a no apuntarlo.
 *
 * **Una sola cifra en rojo, y no es «fuera de objetivo»**: es el periodo que
 * cerró sin medición. Quedarse por debajo de una cifra que la propia
 * organización se puso es la distancia que queda; haberse comprometido a medir
 * cada trimestre y no haber medido es la cláusula 9.1 sin hacer, y es lo primero
 * que un auditor comprueba.
 */
const props = defineProps<{ resumen: App.Http.Resources.Panel.ResumenMetricasPanel }>();

const barras = computed<Barra[]>(() =>
    props.resumen.porCumplimiento.map((tramo) => ({
        clave: tramo.clave,
        etiqueta: tramo.etiqueta,
        valor: tramo.valor,
        de: props.resumen.activos,
        tono: tramo.tono,
    })),
);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Desempeño</CardTitle>
            <CardDescription>
                Qué se mide y contra qué objetivo. La cláusula 9.1 no pide una cifra:
                pide seguirla con una cadencia declarada.
            </CardDescription>
            <CardAction>
                <Link
                    href="/indicadores"
                    class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    Ver los indicadores
                    <ChevronRightIcon class="size-4" />
                </Link>
            </CardAction>
        </CardHeader>

        <CardContent class="space-y-6 pt-0">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:gap-10">
                <div class="shrink-0">
                    <p class="flex items-baseline gap-1.5">
                        <Cifra class="text-3xl font-bold" :valor="resumen.activos" />
                        <!-- Con su denominador, como toda cifra del producto: los
                             retirados conservan su serie y siguen contando en el total. -->
                        <span class="text-sm text-muted-foreground">de {{ resumen.total }}</span>
                    </p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ resumen.activos === 1 ? 'indicador en seguimiento' : 'indicadores en seguimiento' }}
                    </p>
                </div>

                <div v-if="resumen.activos > 0" class="min-w-0 flex-1">
                    <p class="mb-1.5 text-xs font-medium text-muted-foreground">Cómo va cada uno</p>
                    <GraficaBarras :barras="barras" />
                </div>
            </div>

            <!--
                Lo que pide acción, en una línea y nunca a cero: una línea que dice
                «0 sin medir» enseña a no leer la línea.
            -->
            <p
                v-if="resumen.periodoSinMedir > 0 || resumen.fueraDeObjetivo > 0 || resumen.nuncaMedidos > 0"
                class="flex flex-wrap gap-x-4 gap-y-1 border-t pt-4 text-sm"
            >
                <Link
                    v-if="resumen.periodoSinMedir > 0"
                    href="/indicadores?filter[periodo_sin_medir]=1"
                    class="text-destructive underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium" :valor="resumen.periodoSinMedir" />
                    con el periodo sin medir
                </Link>

                <Link
                    v-if="resumen.fueraDeObjetivo > 0"
                    href="/indicadores?filter[fuera_de_objetivo]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.fueraDeObjetivo" />
                    fuera de objetivo
                </Link>

                <Link
                    v-if="resumen.nuncaMedidos > 0"
                    href="/indicadores?filter[sin_medir]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.nuncaMedidos" />
                    nunca medidos
                </Link>
            </p>
        </CardContent>
    </Card>
</template>
