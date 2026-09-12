<script setup lang="ts">
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import Cifra from '@/components/Cifra.vue';
import GraficaBarras, { type Barra } from '@/components/grafica/GraficaBarras.vue';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@lucide/vue';
import { computed } from 'vue';

type Reparto = App.Http.Resources.Panel.Reparto;

/**
 * El plan de acción, de un vistazo.
 *
 * Contesta «qué queda por hacer y en qué punto está», que es la pregunta de un
 * panel. Lo que pide acción hoy no vive aquí sino encima de la tabla de tareas:
 * una pantalla para saber, otra para trabajar.
 *
 * **Sin anillo de progreso, a diferencia del inventario.** Allí el denominador
 * es estable —los activos vigentes— y el porcentaje mide de verdad cuánto está
 * decidido. Aquí el denominador crece cada vez que alguien apunta trabajo, así
 * que «porcentaje de tareas hechas» baja al ser más honesto y sube al cerrar
 * cosas pequeñas: mide actividad, no salud, y un indicador que castiga por
 * apuntar lo que falta enseña a no apuntarlo. Lo que abre la tarjeta es cuántas
 * quedan abiertas, con su denominador al lado.
 */
const props = defineProps<{ plan: App.Http.Resources.Panel.ResumenPlanPanel }>();

/** Un reparto del servidor, tal y como lo espera cada componente. */
const segmentos = (reparto: Reparto[]): Segmento[] =>
    reparto.map((tramo) => ({
        clave: tramo.clave,
        etiqueta: tramo.etiqueta,
        valor: tramo.valor,
        tono: tramo.tono,
    }));

const barras = (reparto: Reparto[], total: number): Barra[] =>
    reparto.map((tramo) => ({
        clave: tramo.clave,
        etiqueta: tramo.etiqueta,
        valor: tramo.valor,
        de: total,
        tono: tramo.tono,
    }));

const totalPrioridades = computed(() => props.plan.porPrioridad.reduce((suma, t) => suma + t.valor, 0));
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Plan de acción</CardTitle>
            <CardDescription>
                Lo que queda por hacer y en qué punto está. Una tarea puede hacer avanzar requisitos de varios
                marcos a la vez.
            </CardDescription>
            <CardAction>
                <Link href="/tareas" class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    Ver el plan
                    <ChevronRightIcon class="size-4" />
                </Link>
            </CardAction>
        </CardHeader>

        <CardContent class="space-y-6 pt-0">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:gap-10">
                <div class="shrink-0">
                    <p class="flex items-baseline gap-1.5">
                        <Cifra class="text-3xl font-bold" :valor="plan.abiertas" />
                        <!-- El denominador al lado: ocho abiertas sobre diez es un
                             plan que no ha arrancado, y sobre doscientas es un
                             martes. -->
                        <span class="cifra text-sm text-muted-foreground">de <Cifra :valor="plan.total" /></span>
                    </p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ plan.abiertas === 1 ? 'tarea abierta' : 'tareas abiertas' }}
                    </p>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="mb-1.5 text-xs font-medium text-muted-foreground">En qué punto están</p>
                    <BarraSegmentada :segmentos="segmentos(plan.porEstado)" leyenda />
                </div>
            </div>

            <div v-if="plan.porPrioridad.length > 0" class="border-t pt-6">
                <h3 class="mb-4 text-sm font-medium">Por prioridad</h3>
                <GraficaBarras :barras="barras(plan.porPrioridad, totalPrioridades)" />
            </div>

            <!--
                Lo que arde va en una línea con enlace, no en tarjetas: las
                tarjetas son de la tabla, que es donde se trabaja. Y a cero no se
                pinta: una línea que dice «0 vencidas» enseña a no leer la línea.
            -->
            <p
                v-if="plan.vencidas > 0 || plan.sinResponsable > 0"
                class="flex flex-wrap gap-x-4 gap-y-1 border-t pt-4 text-sm"
            >
                <Link
                    v-if="plan.vencidas > 0"
                    href="/tareas?filter[vencidas]=1"
                    class="text-destructive underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium" :valor="plan.vencidas" />
                    {{ plan.vencidas === 1 ? 'tarea vencida' : 'tareas vencidas' }}
                </Link>

                <Link
                    v-if="plan.sinResponsable > 0"
                    href="/tareas?filter[sin_responsable]=1"
                    class="text-muted-foreground underline-offset-4 hover:underline"
                >
                    <Cifra class="font-medium text-foreground" :valor="plan.sinResponsable" />
                    sin responsable
                </Link>
            </p>
        </CardContent>
    </Card>
</template>
