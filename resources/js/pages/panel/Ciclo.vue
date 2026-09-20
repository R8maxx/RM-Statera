<script setup lang="ts">
import EstadoVacio from '@/components/EstadoVacio.vue';
import ConmutadorPanel from '@/components/panel/ConmutadorPanel.vue';
import ResumenIncidentesPanel from '@/components/incidente/ResumenIncidentesPanel.vue';
import ResumenMetricasPanel from '@/components/metrica/ResumenMetricasPanel.vue';
import ResumenNoConformidadesPanel from '@/components/no-conformidad/ResumenNoConformidadesPanel.vue';
import ResumenObjetivosPanel from '@/components/objetivo/ResumenObjetivosPanel.vue';
import ResumenPlanPanel from '@/components/tarea/ResumenPlanPanel.vue';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { ListTodoIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed } from 'vue';

/**
 * «¿Qué está pasando y estamos mejorando?» — la segunda vista del panel.
 *
 * Las cinco piezas del ciclo vivo, en el orden en que se recorren: lo que hay
 * abierto, lo que se rompió, lo que pasó, lo que se mide y a qué nos
 * comprometimos. Antes estaban desperdigadas entre las trece tarjetas de una
 * sola pantalla, cada una con su rojo dentro.
 *
 * **Aquí no hay ningún rojo**, y es deliberado: todos subieron a la tira de la
 * cabecera, que se pinta también en esta vista. Lo que queda son los repartos y
 * las cifras de cabecera de cada registro, que es lo que se viene a mirar
 * cuando ya se sabe que no arde nada.
 *
 * Los nulos los decide el servidor, no esta página: conectar dos módulos abre
 * una puerta lateral al registro del otro si el frontend es quien elige qué
 * esconder.
 */
const props = defineProps<{
    vistas: App.Http.Resources.Panel.VistaPanel[];
    plan: App.Http.Resources.Panel.ResumenPlanPanel;
    noConformidades: App.Http.Resources.Panel.ResumenNoConformidadesPanel | null;
    incidentes: App.Http.Resources.Panel.ResumenIncidentesPanel | null;
    desempeno: App.Http.Resources.Panel.ResumenMetricasPanel | null;
    objetivos: App.Http.Resources.Panel.ResumenObjetivosPanel | null;
}>();

const { variantesEntrada, variantesEscalonado } = useMovimientoReducido();
const escalonado = variantesEscalonado(0.05);

/*
 * Una pestaña que no pinta nada es peor que una pestaña larga: parece rota.
 * Con los cinco registros vacíos —que es el estado de una organización que
 * acaba de empezar— esta vista diría literalmente nada, así que dice por dónde
 * se empieza.
 */
const vacia = computed(
    () =>
        props.plan.total === 0 &&
        (props.noConformidades?.total ?? 0) === 0 &&
        (props.incidentes?.total ?? 0) === 0 &&
        (props.desempeno?.total ?? 0) === 0 &&
        (props.objetivos?.total ?? 0) === 0,
);
</script>

<template>
    <AppLayout titulo="El ciclo">
        <ConmutadorPanel :vistas="vistas" />

        <motion.div :variants="escalonado" initial="oculto" animate="visible" class="mt-6 space-y-6">
            <motion.section v-if="vacia" :variants="variantesEntrada">
                <EstadoVacio
                    :icono="ListTodoIcon"
                    titulo="El ciclo todavía no ha empezado"
                    descripcion="Aquí aparecen el trabajo abierto, lo que se rompió y cómo se está tratando, las cifras que se miden y los objetivos a los que la dirección se ha comprometido. El primer paso suele ser apuntar lo que falta por implantar."
                    :accion="{ etiqueta: 'Abrir el plan de acción', href: '/tareas' }"
                />
            </motion.section>

            <!-- ── Plan de acción ─────────────────────────────────────────── -->
            <!--
                Abre la vista porque es la única de las cinco que habla de lo que
                está pasando ahora mismo: el cumplimiento, en la pestaña de al
                lado, dice qué falta, y esto dice quién lo está haciendo.
            -->
            <motion.section v-if="plan.total > 0" :variants="variantesEntrada">
                <ResumenPlanPanel :plan="plan" />
            </motion.section>

            <!-- ── No conformidades ───────────────────────────────────────── -->
            <!--
                Detrás del plan y por lo mismo que aquél abre: éste dice qué se
                está haciendo y esto dice qué se rompió por el camino. Con el
                registro vacío no se pinta: una tarjeta de ceros enseña a no
                mirar la tarjeta, y aquí el vacío es además el estado normal de
                quien todavía no ha auditado.
            -->
            <motion.section
                v-if="noConformidades && noConformidades.total > 0"
                :variants="variantesEntrada"
            >
                <ResumenNoConformidadesPanel :resumen="noConformidades" />
            </motion.section>

            <!-- ── Incidentes ─────────────────────────────────────────────── -->
            <!--
                Detrás de las no conformidades y no junto a personas, que es
                donde estaba en el panel de una sola columna: allí las dos eran
                «lo último que llegó» y aquí manda la pregunta —un incidente es
                algo que pasó, y personas es de qué organización hablamos—. Con
                el registro vacío no se pinta, como las demás.
            -->
            <motion.section v-if="incidentes && incidentes.total > 0" :variants="variantesEntrada">
                <ResumenIncidentesPanel :resumen="incidentes" />
            </motion.section>

            <!-- ── Desempeño ──────────────────────────────────────────────── -->
            <!--
                Y aquí baja el ritmo: un indicador trimestral cambia cuatro veces
                al año, así que va detrás de lo que se mira a diario. Con el
                cuadro vacío no se pinta, como las demás.
            -->
            <motion.section
                v-if="desempeno && desempeno.total > 0"
                :variants="variantesEntrada"
            >
                <ResumenMetricasPanel :resumen="desempeno" />
            </motion.section>

            <!-- ── Objetivos ──────────────────────────────────────────────── -->
            <!--
                Pegado al desempeño, que es su otra mitad: los indicadores dicen
                cómo va y los objetivos dicen contra qué. Con el registro vacío no
                se pinta, como las demás.
            -->
            <motion.section
                v-if="objetivos && objetivos.total > 0"
                :variants="variantesEntrada"
            >
                <ResumenObjetivosPanel :resumen="objetivos" />
            </motion.section>

        </motion.div>
    </AppLayout>
</template>
