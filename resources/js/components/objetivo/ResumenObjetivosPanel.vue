<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
import GraficaBarras, { type Barra } from '@/components/grafica/GraficaBarras.vue';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Los objetivos de seguridad, de un vistazo (cláusula 6.2).
 *
 * Va pegado al desempeño porque son las dos mitades de la misma pregunta: los
 * indicadores dicen cómo va y los objetivos dicen contra qué.
 *
 * **Sin anillo y sin porcentaje de objetivos alcanzados**, por el mismo motivo
 * por el que no lo llevan el plan de acción ni el cuadro de indicadores: esa
 * cifra sube al cerrar y baja al comprometerse con uno nuevo, así que castiga
 * por ponerse objetivos ambiciosos.
 *
 * **Una sola cifra en rojo, y no es «no alcanzado»**: es el objetivo aprobado
 * cuyo plazo pasó y que nadie ha cerrado. Quedarse corto es la distancia que
 * queda; haberse comprometido por escrito a una fecha que ya pasó y no decir si
 * se consiguió es la 6.2 sin terminar.
 *
 * El reparto por estado **incluye los cerrados**, a diferencia del plan de
 * acción: la pregunta aquí es «de los que nos pusimos, cuántos alcanzamos», que
 * es literalmente una de las siete entradas de la revisión por la dirección.
 */
const props = defineProps<{ resumen: App.Http.Resources.Panel.ResumenObjetivosPanel }>();

const barras = computed<Barra[]>(() =>
    props.resumen.porEstado.map((tramo) => ({
        clave: tramo.clave,
        etiqueta: tramo.etiqueta,
        valor: tramo.valor,
        de: props.resumen.total,
        tono: tramo.tono,
    })),
);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Objetivos de seguridad</CardTitle>
            <CardDescription>
                A qué se ha comprometido la organización, para cuándo y con qué cifra se
                comprueba. La cláusula 6.2 exige que sean medibles.
            </CardDescription>
            <CardAction>
                <Link
                    href="/objetivos"
                    class="flex items-center gap-1 rounded text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Ver los objetivos
                    <ChevronRightIcon class="size-4" />
                </Link>
            </CardAction>
        </CardHeader>

        <CardContent class="space-y-6 pt-0">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:gap-10">
                <div class="shrink-0">
                    <p class="flex items-baseline gap-1.5">
                        <Cifra class="text-2xl font-semibold" :valor="resumen.vivos" />
                        <!-- Con su denominador: dos vivos sobre tres es una
                             organización que no se pone objetivos, y sobre treinta
                             es una que ya ha cerrado el año. -->
                        <span class="text-sm text-muted-foreground">de {{ resumen.total }}</span>
                    </p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ resumen.vivos === 1 ? 'objetivo en curso' : 'objetivos en curso' }}
                    </p>
                </div>

                <div v-if="resumen.total > 0" class="min-w-0 flex-1">
                    <p class="mb-1.5 text-xs font-medium text-muted-foreground">Cómo acabó cada uno</p>
                    <GraficaBarras :barras="barras" />
                </div>
            </div>

            <!--
                Lo que pide acción, en una línea y nunca a cero: una línea que dice
                «0 fuera de plazo» enseña a no leer la línea.
            -->
            <p
                v-if="resumen.vencidos > 0 || resumen.sinIndicador > 0 || resumen.sinActuacion > 0"
                class="flex flex-wrap gap-x-4 gap-y-1 border-t pt-4 text-sm"
            >
                <Link
                    v-if="resumen.vencidos > 0"
                    href="/objetivos?filter[vencidos]=1"
                    class="text-destructive underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium" :valor="resumen.vencidos" />
                    fuera de plazo
                </Link>

                <Link
                    v-if="resumen.sinIndicador > 0"
                    href="/objetivos?filter[sin_indicador]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.sinIndicador" />
                    sin indicador
                </Link>

                <Link
                    v-if="resumen.sinActuacion > 0"
                    href="/objetivos?filter[sin_actuacion]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="resumen.sinActuacion" />
                    sin actuación
                </Link>
            </p>
        </CardContent>
    </Card>
</template>
