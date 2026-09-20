<script setup lang="ts">
import EstadoVacio from '@/components/EstadoVacio.vue';
import ConmutadorPanel from '@/components/panel/ConmutadorPanel.vue';
import ResumenContextoPanel from '@/components/contexto/ResumenContextoPanel.vue';
import ResumenInventarioPanelCard from '@/components/activo/ResumenInventarioPanel.vue';
import ResumenPersonasPanel from '@/components/persona/ResumenPersonasPanel.vue';
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import AppLayout from '@/layouts/AppLayout.vue';
import { CompassIcon } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed } from 'vue';

/**
 * «¿De qué estamos hablando?» — la tercera vista del panel.
 *
 * El entorno y lo que hay dentro del alcance: el contexto que lo enmarca, la
 * gente que lo sostiene y el inventario sobre el que se aplica todo lo demás.
 *
 * **Es la que menos se abre, y por eso es la tercera.** Una plantilla se mueve
 * por altas y bajas y un DAFO se revisa una vez al año; nada de esto cambia de
 * un día para otro. Tenerlo apilado debajo del cumplimiento era gastar el
 * scroll de todos los días en lo que se consulta cuatro veces al año.
 */
const props = defineProps<{
    vistas: App.Http.Resources.Panel.VistaPanel[];
    contexto: App.Http.Resources.Panel.ResumenContextoPanel | null;
    personas: App.Http.Resources.Panel.ResumenPersonasPanel | null;
    inventario: App.Http.Resources.Panel.ResumenInventarioPanel;
}>();

const { variantesEntrada, variantesEscalonado } = useMovimientoReducido();
const escalonado = variantesEscalonado(0.05);

/* Ver la nota de `Ciclo.vue`: una pestaña en blanco parece rota. */
const vacia = computed(
    () =>
        (props.contexto?.cuestiones ?? 0) === 0 &&
        (props.personas?.total ?? 0) === 0 &&
        props.inventario.vigentes === 0,
);
</script>

<template>
    <AppLayout titulo="La organización">
        <ConmutadorPanel :vistas="vistas" />

        <motion.div :variants="escalonado" initial="oculto" animate="visible" class="mt-6 space-y-6">
            <motion.section v-if="vacia" :variants="variantesEntrada">
                <EstadoVacio
                    :icono="CompassIcon"
                    titulo="Todavía no hay contexto registrado"
                    descripcion="Aquí aparecen el análisis del entorno, la plantilla con sus roles ENS y el inventario de activos. La cláusula 4.1 pide empezar por determinar las cuestiones internas y externas: es de donde salen los riesgos."
                    :accion="{ etiqueta: 'Empezar por el contexto', href: '/contexto' }"
                />
            </motion.section>

            <!-- ── Contexto de la organización ────────────────────────────── -->
            <!--
                Abre la vista, y con el panel repartido eso cambió de significado:
                en la pantalla única iba la última porque el contexto se revisa
                una vez al año y el cumplimiento todas las semanas. Aquí dentro
                manda otra cosa — de las tres, es la que enmarca a las otras dos:
                el alcance sale del contexto, y de ahí salen la plantilla y el
                inventario. Con el registro vacío no se pinta, como las demás.
            -->
            <motion.section
                v-if="contexto && contexto.cuestiones > 0"
                :variants="variantesEntrada"
            >
                <ResumenContextoPanel :resumen="contexto" />
            </motion.section>

            <!-- ── Personas ───────────────────────────────────────────────── -->
            <!--
                Detrás del contexto: quién sostiene el sistema que aquél
                delimita. Lo que de aquí se consulta a diario es una sola cifra
                —la salida sin cerrar— y ésa vive arriba, en la tira. Con el
                registro vacío no se pinta, como las demás.
            -->
            <motion.section v-if="personas && personas.total > 0" :variants="variantesEntrada">
                <ResumenPersonasPanel :resumen="personas" />
            </motion.section>

            <!-- ── Inventario ─────────────────────────────────────────────── -->
            <!--
                El último, y es donde aterriza todo lo anterior: sobre estos
                activos se aplican las medidas que el contexto enmarca y la
                plantilla sostiene. Los repartos —por tipo, por ciclo de vida,
                cobertura de cifrado y copia— viven aquí y no en `/activos`, que
                es donde se viene a trabajar y no a mirar cómo va la cosa.
            -->
            <motion.section v-if="inventario.vigentes > 0" :variants="variantesEntrada">
                <ResumenInventarioPanelCard :inventario="inventario" />
            </motion.section>

        </motion.div>
    </AppLayout>
</template>
