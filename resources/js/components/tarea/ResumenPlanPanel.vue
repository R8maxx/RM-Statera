<script setup lang="ts">
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import Cifra from '@/components/Cifra.vue';
import { Link } from '@inertiajs/vue3';

type Reparto = App.Http.Resources.Panel.Reparto;

/**
 * El plan de acción, de un vistazo: la mitad izquierda de la tarjeta que abre
 * «El ciclo». La otra mitad es «Lo que vence», y por eso esto ya no es una
 * tarjeta propia: el plan dice cuánto hay abierto y aquélla para cuándo.
 *
 * **Sin anillo de progreso, a diferencia del inventario.** Allí el denominador
 * es estable —los activos vigentes— y el porcentaje mide de verdad cuánto está
 * decidido. Aquí el denominador crece cada vez que alguien apunta trabajo, así
 * que «porcentaje de tareas hechas» baja al ser más honesto y sube al cerrar
 * cosas pequeñas: mide actividad, no salud, y un indicador que castiga por
 * apuntar lo que falta enseña a no apuntarlo. Lo que abre es cuántas quedan
 * abiertas, con su denominador al lado.
 *
 * **De dónde sale lo abierto, en cifras que enlazan.** Existe porque «40 tareas
 * abiertas» mezcla la deuda que alguien planificó con el trabajo correctivo que
 * viene de algo que ya falló, y eso no se ve en el total. Iba en barras; con la
 * tarjeta compartida caben como una fila de cifras, cada una con su filtro.
 */
const props = defineProps<{ plan: App.Http.Resources.Panel.ResumenPlanPanel }>();

const segmentos = (reparto: Reparto[]): Segmento[] =>
    reparto.map((tramo) => ({
        clave: tramo.clave,
        etiqueta: tramo.etiqueta,
        valor: tramo.valor,
        tono: tramo.tono,
    }));

const enlace = (tramo: Reparto): string => (tramo.filtro ? `/tareas?${tramo.filtro}` : '/tareas');
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold tracking-[-0.01em]">Plan de acción</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    El trabajo apuntado para cerrar lo que falta, venga de donde venga.
                </p>
            </div>
            <Link href="/tareas" class="shrink-0 text-sm font-medium text-primary underline-offset-4 hover:underline">
                Abrir el plan
            </Link>
        </div>

        <div class="flex flex-wrap items-end gap-x-5 gap-y-2">
            <Cifra class="cifra text-6xl leading-none font-semibold tracking-[-0.03em] sm:text-7xl" :valor="plan.abiertas" />
            <p class="pb-1.5 font-semibold">
                {{ plan.abiertas === 1 ? 'tarea abierta' : 'tareas abiertas' }} de
                <Cifra class="cifra" :valor="plan.total" /> apuntadas
            </p>
        </div>

        <BarraSegmentada v-if="plan.abiertas > 0" :segmentos="segmentos(plan.porEstado)" leyenda />

        <div v-if="plan.porOrigen.length > 0" class="border-t pt-5">
            <h3 class="mb-3 text-xs font-medium text-muted-foreground">De dónde sale lo abierto</h3>
            <ul class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3 lg:grid-cols-5">
                <li v-for="tramo in plan.porOrigen" :key="tramo.clave">
                    <Link :href="enlace(tramo)" class="group block rounded">
                        <Cifra class="cifra block text-lg font-semibold" :valor="tramo.valor" />
                        <span class="text-xs text-muted-foreground group-hover:underline">{{ tramo.etiqueta }}</span>
                    </Link>
                </li>
            </ul>
        </div>

        <!--
            Lo que arde, en una línea con enlace y nunca a cero. Las vencidas
            salen también en «Lo que vence», una a una; aquí va el recuento y el
            filtro que las reúne.
        -->
        <p
            v-if="plan.vencidas > 0 || plan.sinResponsable > 0 || plan.porPrioridad.length > 0"
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
            <template v-for="tramo in plan.porPrioridad" :key="tramo.clave">
                <Link :href="enlace(tramo)" class="text-muted-foreground underline-offset-4 hover:underline">
                    <Cifra class="font-medium text-foreground" :valor="tramo.valor" />
                    de prioridad {{ tramo.etiqueta.toLowerCase() }}
                </Link>
            </template>
        </p>
    </div>
</template>
