<script setup lang="ts">
import EstadoVacio from '@/components/EstadoVacio.vue';
import CabeceraPanel from '@/components/panel/CabeceraPanel.vue';
import ListaVencimientos from '@/components/panel/ListaVencimientos.vue';
import ResumenIncidentesPanel from '@/components/incidente/ResumenIncidentesPanel.vue';
import ResumenMetricasPanel from '@/components/metrica/ResumenMetricasPanel.vue';
import ResumenNoConformidadesPanel from '@/components/no-conformidad/ResumenNoConformidadesPanel.vue';
import ResumenObjetivosPanel from '@/components/objetivo/ResumenObjetivosPanel.vue';
import ResumenObligacionesPanel from '@/components/obligacion/ResumenObligacionesPanel.vue';
import ResumenPlanPanel from '@/components/tarea/ResumenPlanPanel.vue';
import ResumenVulnerabilidadesPanel from '@/components/vulnerabilidad/ResumenVulnerabilidadesPanel.vue';
import { Card } from '@/components/ui/card';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { ListTodoIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed } from 'vue';

/**
 * «¿Qué está pasando y estamos mejorando?» — la segunda vista del panel.
 *
 * **Un solo elemento fuerte**, como en las otras dos: el plan de acción con la
 * cifra grande y, en la misma tarjeta, «Lo que vence» de todos los registros de
 * la pestaña. El plan dice cuánto hay abierto y la lista para cuándo; antes cada
 * tarjeta decía sus vencidas por separado y la pregunta «qué vence esta semana»
 * no tenía dónde contestarse.
 *
 * Detrás, los registros en **dos grupos por la pregunta que contestan** y en
 * tarjetas de tercio (`TarjetaRegistro`): lo que falló y cómo se trata —no
 * conformidades, incidentes, vulnerabilidades— y si estamos mejorando
 * —indicadores, objetivos, obligaciones periódicas—. Eran seis tarjetas a todo
 * el ancho y del mismo peso apiladas, y no mandaba ninguna.
 *
 * **El rojo está al lado de su cifra**, dentro de la tarjeta del módulo que lo
 * produce y en «Lo que vence», que es donde puede explicarse: el punto de la
 * pestaña dice «mira aquí» y esta vista tiene que contestar.
 *
 * Los nulos los decide el servidor, no esta página: conectar dos módulos abre
 * una puerta lateral al registro del otro si el frontend es quien elige qué
 * esconder.
 */
const props = defineProps<{
    vistas: App.Http.Resources.Panel.VistaPanel[];
    plan: App.Http.Resources.Panel.ResumenPlanPanel;
    vencimientos: App.Http.Resources.Panel.VencimientosPanel;
    noConformidades: App.Http.Resources.Panel.ResumenNoConformidadesPanel | null;
    incidentes: App.Http.Resources.Panel.ResumenIncidentesPanel | null;
    desempeno: App.Http.Resources.Panel.ResumenMetricasPanel | null;
    objetivos: App.Http.Resources.Panel.ResumenObjetivosPanel | null;
    obligaciones: App.Http.Resources.Panel.ResumenObligacionesPanel | null;
    vulnerabilidades: App.Http.Resources.Panel.ResumenVulnerabilidadesPanel | null;
}>();

const { variantesEntrada, variantesEscalonado } = useMovimientoReducido();
const escalonado = variantesEscalonado(0.05);

/*
 * Cada tarjeta se pinta sólo con su registro lleno: una tarjeta de ceros enseña
 * a no mirar la tarjeta, y aquí el vacío es además el estado normal de quien
 * todavía no ha auditado.
 */
const hay = {
    noConformidades: computed(() => (props.noConformidades?.total ?? 0) > 0),
    incidentes: computed(() => (props.incidentes?.total ?? 0) > 0),
    vulnerabilidades: computed(() => (props.vulnerabilidades?.total ?? 0) > 0),
    desempeno: computed(() => (props.desempeno?.total ?? 0) > 0),
    objetivos: computed(() => (props.objetivos?.total ?? 0) > 0),
    obligaciones: computed(() => (props.obligaciones?.total ?? 0) > 0),
};

const hayFallos = computed(() => hay.noConformidades.value || hay.incidentes.value || hay.vulnerabilidades.value);
const hayMejora = computed(() => hay.desempeno.value || hay.objetivos.value || hay.obligaciones.value);

/*
 * Una pestaña que no pinta nada es peor que una pestaña larga: parece rota. Con
 * todos los registros vacíos —el estado de quien acaba de empezar— dice por
 * dónde se empieza.
 */
const vacia = computed(
    () =>
        props.plan.total === 0 &&
        props.vencimientos.pasados + props.vencimientos.proximos === 0 &&
        !hayFallos.value &&
        !hayMejora.value,
);
</script>

<template>
    <AppLayout titulo="El ciclo">
        <CabeceraPanel :vistas="vistas" />

        <motion.div :variants="escalonado" initial="oculto" animate="visible" class="space-y-8">
            <motion.section v-if="vacia" :variants="variantesEntrada">
                <EstadoVacio
                    :icono="ListTodoIcon"
                    titulo="El ciclo todavía no ha empezado"
                    descripcion="Aquí aparecen el trabajo abierto, lo que vence, lo que se rompió y cómo se está tratando, las cifras que se miden y los objetivos a los que la dirección se ha comprometido. El primer paso suele ser apuntar lo que falta por implantar."
                    :accion="{ etiqueta: 'Abrir el plan de acción', href: '/tareas' }"
                />
            </motion.section>

            <!-- ── El elemento fuerte: el plan y lo que vence ─────────────── -->
            <motion.section v-else :variants="variantesEntrada">
                <Card class="gap-0 overflow-hidden py-0 lg:flex-row">
                    <div class="min-w-0 flex-1 p-6 sm:p-8">
                        <ResumenPlanPanel :plan="plan" />
                    </div>
                    <div class="border-t p-6 sm:p-8 lg:w-[28rem] lg:shrink-0 lg:border-t-0 lg:border-l">
                        <ListaVencimientos :vencimientos="vencimientos" />
                    </div>
                </Card>
            </motion.section>

            <!-- ── Lo que falló ───────────────────────────────────────────── -->
            <motion.section v-if="hayFallos" :variants="variantesEntrada" class="space-y-4">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 class="text-base font-semibold tracking-[-0.01em]">Lo que falló y cómo se trata</h2>
                    <p class="text-sm text-muted-foreground">Lo que se encontró, lo que pasó y lo que podría pasar.</p>
                </div>
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3 [&>*]:h-full">
                    <ResumenNoConformidadesPanel v-if="noConformidades && hay.noConformidades.value" :resumen="noConformidades" />
                    <ResumenIncidentesPanel v-if="incidentes && hay.incidentes.value" :resumen="incidentes" />
                    <ResumenVulnerabilidadesPanel
                        v-if="vulnerabilidades && hay.vulnerabilidades.value"
                        :resumen="vulnerabilidades"
                    />
                </div>
            </motion.section>

            <!-- ── ¿Mejoramos? ────────────────────────────────────────────── -->
            <!-- Y aquí baja el ritmo: un indicador trimestral cambia cuatro
                 veces al año, así que va detrás de lo que se mira a diario. -->
            <motion.section v-if="hayMejora" :variants="variantesEntrada" class="space-y-4">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 class="text-base font-semibold tracking-[-0.01em]">¿Estamos mejorando?</h2>
                    <p class="text-sm text-muted-foreground">Lo que se mide, contra qué, y lo que toca cada tanto.</p>
                </div>
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3 [&>*]:h-full">
                    <ResumenMetricasPanel v-if="desempeno && hay.desempeno.value" :resumen="desempeno" />
                    <ResumenObjetivosPanel v-if="objetivos && hay.objetivos.value" :resumen="objetivos" />
                    <ResumenObligacionesPanel v-if="obligaciones && hay.obligaciones.value" :resumen="obligaciones" />
                </div>
            </motion.section>
        </motion.div>
    </AppLayout>
</template>
