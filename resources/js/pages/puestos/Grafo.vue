<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import ConmutadorOrganigrama from '@/components/puesto/ConmutadorOrganigrama.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import NodoPuesto from '@/components/puesto/NodoPuesto.vue';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { disponer, type NodoOrganigrama } from '@/lib/organigrama';
import { Controls } from '@vue-flow/controls';
import { useVueFlow, VueFlow, type NodeTypesObject } from '@vue-flow/core';
import { MiniMap } from '@vue-flow/minimap';
import { computed, markRaw } from 'vue';

import '@vue-flow/core/dist/style.css';
import '@vue-flow/controls/dist/style.css';
import '@vue-flow/minimap/dist/style.css';

/**
 * El organigrama como diagrama de cajas.
 *
 * Dos vistas con una sola pantalla —`conPersonas` decide— porque la única
 * diferencia es qué lleva la caja dentro: la jerarquía, la disposición y el
 * lienzo son los mismos, y duplicar el componente sería duplicar el sitio donde
 * arreglar lo que se rompa.
 *
 * **El reparto con la librería.** `d3-hierarchy` calcula dónde va cada caja,
 * Vue Flow pone el lienzo con su pan y su zoom, y **el aspecto es nuestro**: los
 * nodos son componentes con los tokens de `app.css`. Por eso las variables de
 * Vue Flow se reescriben abajo contra los tokens en vez de aceptar su tema, que
 * es claro y no sabe nada de nuestro modo oscuro.
 *
 * **El scroll vive en la caja y no en la página**, que es lo que permite tener
 * un diagrama sin romper la regla de DESIGN.md de no desplazar la página en
 * horizontal: en móvil se navega arrastrando dentro del lienzo.
 *
 * **Nada se arrastra ni se conecta.** La jerarquía se cambia en la ficha del
 * puesto, que es donde `AsignarSuperior` comprueba los ciclos; dejar mover
 * nodos aquí prometería que el organigrama se edita arrastrando, y no es así.
 */
const props = defineProps<{
    nodos: NodoOrganigrama[];
    sueltos: number;
    total: number;
    conPersonas: boolean;
    puedeGestionar: boolean;
}>();

/*
 * `markRaw`: es un componente, no un dato, y Vue no tiene que hacerlo reactivo.
 *
 * El `as unknown as` es el peaje de Vue Flow y no una dejadez: su
 * `NodeTypesObject` exige que el componente declare las diez props de `NodeProps`
 * —`id`, `selected`, `connectable`…—, y `NodoPuesto` sólo usa `data`. Declararlas
 * todas para ignorar nueve sería más ruido que este comentario.
 */
const tiposDeNodo = { puesto: markRaw(NodoPuesto) } as unknown as NodeTypesObject;

const grafo = computed(() => disponer(props.nodos, props.conPersonas));

/*
 * El encuadre se pide cuando los nodos EXISTEN, no al montar.
 *
 * `fit-view-on-init` corre antes de que el lienzo tenga su tamaño definitivo, y
 * en una ventana estrecha —donde la caja es alta y angosta— eso deja el árbol
 * medio fuera y un montón de vacío. `onNodesInitialized` cae después de que Vue
 * Flow haya medido, que es cuando el encuadre significa algo. Se vio a 500 px.
 */
const { fitView, onNodesInitialized } = useVueFlow();

onNodesInitialized(() => {
    void fitView({ padding: 0.2 });
});
</script>

<template>
    <AppLayout ancho="completo" titulo="Organigrama">
        <CabeceraPagina
            titulo="Organigrama"
            :descripcion="
                conPersonas
                    ? 'La jerarquía de puestos con quién ocupa cada uno. Arrastra para moverte y usa los controles para acercar.'
                    : 'La jerarquía de puestos. Vive en el puesto, así que no se mueve porque alguien entre o se vaya.'
            "
        >
            <template #acciones>
                <ConmutadorOrganigrama />
            </template>
        </CabeceraPagina>

        <Card>
            <CardContent>
                <EstadoVacio
                    v-if="nodos.length === 0"
                    titulo="Todavía no hay puestos"
                    descripcion="El organigrama sale del catálogo de puestos: en cuanto haya uno, aparece aquí."
                />

                <!--
                    Alto fijo en `dvh` y no automático: un lienzo que crece con su
                    contenido deja de ser un lienzo —la página entera se estira y
                    el pan no sirve de nada—. Los 22rem que se restan son la
                    cabecera de la aplicación, el título y el respiro de abajo.
                -->
                <div
                    v-else
                    class="grafo h-[calc(100dvh-22rem)] min-h-[24rem] w-full overflow-hidden rounded-xl border bg-superficie"
                >
                    <VueFlow
                        :nodes="grafo.nodes"
                        :edges="grafo.edges"
                        :node-types="tiposDeNodo"
                        :nodes-draggable="false"
                        :nodes-connectable="false"
                        :elements-selectable="false"
                        :edges-updatable="false"
                        :min-zoom="0.2"
                        :max-zoom="1.6"
                        fit-view-on-init
                        :fit-view-options="{ padding: 0.2 }"
                    >
                        <Controls :show-interactive="false" position="bottom-left" />
                        <MiniMap pannable zoomable />
                    </VueFlow>
                </div>
            </CardContent>
        </Card>

        <p class="text-xs text-muted-foreground">
            <template v-if="sueltos > 1">
                Hay <span class="cifra">{{ sueltos }}</span> puestos que no dependen de ninguno, así
                que el diagrama tiene esas ramas sueltas.
            </template>
            Esta vista no se recorre con el teclado —es un lienzo— y a esta anchura hay que
            arrastrar: la
            <a href="/puestos/organigrama" class="underline underline-offset-4">lista</a>
            dice lo mismo y sí hace las dos cosas. Statera no comprueba que el organigrama esté
            completo ni que quien ocupa un puesto reúna la competencia que ese puesto pide.
        </p>
    </AppLayout>
</template>

<style scoped>
/*
 * Vue Flow trae su tema en variables propias y es claro. Se reescriben contra
 * los tokens de `app.css` para que el diagrama siga al modo oscuro y a la marca
 * sin repetir un solo hex — que es la regla que hace legible toda la paleta.
 */
.grafo {
    --vf-node-bg: transparent;
    --vf-node-color: var(--foreground);
    --vf-node-text: var(--foreground);
    --vf-handle: var(--border);
    --vf-connection-path: var(--border);
    --vf-background-pattern-color: var(--border);
}

.grafo :deep(.vue-flow__node) {
    /* La caja la pinta `NodoPuesto`; el nodo de la librería sólo la posiciona. */
    border: none;
    padding: 0;
    background: transparent;
    box-shadow: none;
}

.grafo :deep(.vue-flow__edge-path) {
    stroke: var(--border);
    stroke-width: 1.5;
}

.grafo :deep(.vue-flow__handle) {
    /* Los conectores no se usan —no se arrastra para unir— así que no se ven. */
    opacity: 0;
    pointer-events: none;
}

.grafo :deep(.vue-flow__controls-button) {
    background: var(--card);
    border-color: var(--border);
    fill: var(--foreground);
}

.grafo :deep(.vue-flow__controls-button:hover) {
    background: var(--accent);
}

.grafo :deep(.vue-flow__minimap) {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
}

/*
 * El minimapa pinta sus rectángulos con su propio gris y su máscara con negro al
 * 60 %: sobre el tema oscuro queda un recuadro claro flotando. Se lleva a los
 * tokens como todo lo demás.
 */
.grafo :deep(.vue-flow__minimap-node) {
    fill: var(--border);
    stroke: none;
}

.grafo :deep(.vue-flow__minimap-mask) {
    fill: var(--card);
    fill-opacity: 0.6;
    stroke: none;
}

/* El minimapa es decoración de orientación: a esta anchura estorba más que ayuda. */
@media (max-width: 640px) {
    .grafo :deep(.vue-flow__minimap) {
        display: none;
    }
}
</style>
