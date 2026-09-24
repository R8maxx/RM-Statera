<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import EstadoVacio from '@/components/EstadoVacio.vue';
import NodoActivo from '@/components/activo/NodoActivo.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    disponer,
    type AristaActivo,
    type AristaColocada,
    type NodoActivo as Nodo,
    type NodoColocado,
} from '@/lib/grafoActivos';
import { Controls } from '@vue-flow/controls';
import { VueFlow, type NodeTypesObject, type VueFlowStore } from '@vue-flow/core';
import { MiniMap } from '@vue-flow/minimap';
import { Link } from '@inertiajs/vue3';
import { markRaw, ref, watch } from 'vue';

import '@vue-flow/core/dist/style.css';
import '@vue-flow/controls/dist/style.css';
import '@vue-flow/minimap/dist/style.css';

/**
 * El grafo de dependencias de un activo, como diagrama.
 *
 * Lo que esta pantalla añade a los dos bloques de la ficha son **los rombos**:
 * si dos servicios se apoyan en la misma base de datos, cada lista la enseña una
 * vez y aquí se ven los dos vínculos — que es de donde a esa base de datos le
 * sube la valoración efectiva.
 *
 * **La disposición es asíncrona**, que es la diferencia con el organigrama: ELK
 * corre en un web worker, así que hay un instante sin nodos y hace falta un
 * estado de carga de verdad. Un `computed` no sirve para esto.
 *
 * El resto del reparto es el del organigrama: ELK coloca, Vue Flow pone el
 * lienzo, y **el aspecto es nuestro** —los nodos son componentes con los tokens
 * de `app.css`—. El tema de la librería se reescribe abajo contra ellos.
 */
const props = defineProps<{
    activo: { id: number; codigo: string; nombre: string };
    nodos: Nodo[];
    aristas: AristaActivo[];
    puedeGestionar: boolean;
}>();

const tiposDeNodo = { activo: markRaw(NodoActivo) } as unknown as NodeTypesObject;

const nodes = ref<NodoColocado[]>([]);
const edges = ref<AristaColocada[]>([]);
const colocando = ref(true);

/*
 * El encuadre se pide **por referencia al componente** y no con el `fitView` de
 * `useVueFlow()`, y costó verlo: el composable llamado en el `setup` crea su
 * propio store, y este `<VueFlow>` monta más tarde —detrás del `v-if` del estado
 * de carga, porque los nodos llegan de una promesa— así que acaba en otra
 * instancia. El `fitView` se llamaba sobre un store vacío y **no fallaba**: el
 * lienzo se quedaba a zoom 1 sin desplazar, con el grafo medio fuera. Se vio a
 * 485 px, donde no cabe nada.
 *
 * Con la referencia no hay dos instancias posibles. Y el evento del propio
 * componente es lo que dice que los nodos ya están medidos: `fit-view-on-init`
 * corre antes de que el contenedor tenga su tamaño.
 */
const lienzo = ref<VueFlowStore | null>(null);

function encuadrar(): void {
    void lienzo.value?.fitView({ padding: 0.2 });
}

watch(
    () => [props.nodos, props.aristas] as const,
    async () => {
        colocando.value = true;

        const grafo = await disponer(props.nodos, props.aristas);

        nodes.value = grafo.nodes;
        edges.value = grafo.edges;
        colocando.value = false;
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <AppLayout :titulo="`Dependencias de ${activo.codigo}`">
        <CabeceraPagina
            titulo="Grafo de dependencias"
            descripcion="Arriba lo que se cae si este activo cae; abajo lo que necesita para funcionar. El color de cada caja es su valoración efectiva, que es la que sube por estos vínculos."
        >
            <template #acciones>
                <Button as-child variant="outline">
                    <Link :href="`/activos/${activo.id}`">Volver a la ficha</Link>
                </Button>
            </template>
        </CabeceraPagina>

        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="cifra text-muted-foreground">{{ activo.codigo }}</span>
            <span class="font-medium">{{ activo.nombre }}</span>
        </div>

        <Card>
            <CardContent>
                <EstadoVacio
                    v-if="nodos.length <= 1"
                    titulo="Sin dependencias declaradas"
                    descripcion="Este activo no se apoya en nada ni sostiene nada, o no está declarado. Sin el grafo, la valoración no se propaga y el análisis de impacto se queda sin respuesta."
                />

                <div
                    v-else
                    class="grafo h-[calc(100dvh-24rem)] min-h-[24rem] w-full overflow-hidden rounded-xl border bg-superficie"
                >
                    <!--
                        El estado de carga es real y no cosmético: ELK vive en un
                        worker y hay un instante sin nodos. Sin esto, el lienzo
                        aparece vacío y parece que no hay dependencias.
                    -->
                    <div
                        v-if="colocando"
                        class="flex h-full items-center justify-center text-sm text-muted-foreground"
                    >
                        Colocando el grafo…
                    </div>

                    <VueFlow
                        v-else
                        ref="lienzo"
                        :nodes="nodes"
                        :edges="edges"
                        :node-types="tiposDeNodo"
                        :nodes-draggable="false"
                        :nodes-connectable="false"
                        :elements-selectable="false"
                        :edges-updatable="false"
                        :min-zoom="0.2"
                        :max-zoom="1.6"
                        @nodes-initialized="encuadrar"
                    >
                        <Controls :show-interactive="false" position="bottom-left" />
                        <MiniMap pannable zoomable />
                    </VueFlow>
                </div>
            </CardContent>
        </Card>

        <p class="text-xs text-muted-foreground">
            Esta vista no se recorre con el teclado —es un lienzo— y a esta anchura hay que
            arrastrar: los bloques «Depende de» y «Lo sostiene» de la
            <Link :href="`/activos/${activo.id}`" class="underline underline-offset-4">ficha</Link>
            dicen lo mismo en listas y sí hacen las dos cosas. Los vínculos se declaran y se retiran
            desde ahí, no desde aquí: el grafo se mira, no se edita arrastrando.
        </p>
    </AppLayout>
</template>

<style scoped>
/*
 * Igual que en el organigrama: Vue Flow trae su tema en variables propias y es
 * claro. Se reescribe contra los tokens de `app.css` para que el diagrama siga
 * al modo oscuro sin repetir un solo hex.
 */
.grafo {
    --vf-node-bg: transparent;
    --vf-node-color: var(--foreground);
    --vf-node-text: var(--foreground);
    --vf-handle: var(--border);
    --vf-connection-path: var(--border);
}

.grafo :deep(.vue-flow__node) {
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

.grafo :deep(.vue-flow__minimap-node) {
    fill: var(--border);
    stroke: none;
}

.grafo :deep(.vue-flow__minimap-mask) {
    fill: var(--card);
    fill-opacity: 0.6;
    stroke: none;
}

@media (max-width: 640px) {
    .grafo :deep(.vue-flow__minimap) {
        display: none;
    }
}
</style>
