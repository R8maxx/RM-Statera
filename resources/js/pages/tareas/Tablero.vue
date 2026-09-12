<script setup lang="ts">
import CabeceraPagina from '@/components/CabeceraPagina.vue';
import ColumnaTablero, { type Columna } from '@/components/tarea/ColumnaTablero.vue';
import ConmutadorVista from '@/components/tarea/ConmutadorVista.vue';
import CampoTextarea from '@/components/formulario/CampoTextarea.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    columnas: Columna[];
    /** Cuántos días sigue viéndose en el tablero algo ya cerrado. */
    recientes: number;
}>();

const etiquetas = computed(() =>
    Object.fromEntries(props.columnas.map((columna) => [columna.estado, columna.etiqueta])),
);

/**
 * Lo que acaba de pasar, para quien no lo ve.
 *
 * Un cambio que sólo se comunica moviendo una tarjeta de sitio no existe para
 * un lector de pantalla. DESIGN.md § 11: los estados también en texto.
 */
const anuncio = ref('');

function mover(id: number, estado: string): void {
    const titulo = props.columnas
        .flatMap((columna) => columna.tarjetas)
        .find((tarjeta) => tarjeta.id === id)?.titulo;

    router.post(
        `/tareas/${id}/estado`,
        { estado },
        {
            preserveScroll: true,
            /*
             * La tarjeta se mueve al soltarla y vuelve sola si el servidor dice
             * que no. Inertia 3 lo trae de serie, con reversión automática: sin
             * esto, entre el soltado y la respuesta la tarjeta se queda donde
             * estaba y parece que el gesto no ha funcionado.
             */
            optimistic: (actuales) => ({
                columnas: (actuales.columnas as Columna[]).map((columna) => {
                    if (columna.estado === estado) {
                        const movida = (actuales.columnas as Columna[])
                            .flatMap((otra) => otra.tarjetas)
                            .find((tarjeta) => tarjeta.id === id);

                        return movida === undefined
                            ? columna
                            : {
                                  ...columna,
                                  total: columna.total + 1,
                                  tarjetas: [{ ...movida, estado }, ...columna.tarjetas],
                              };
                    }

                    if (!columna.tarjetas.some((tarjeta) => tarjeta.id === id)) {
                        return columna;
                    }

                    return {
                        ...columna,
                        total: Math.max(columna.total - 1, 0),
                        tarjetas: columna.tarjetas.filter((tarjeta) => tarjeta.id !== id),
                    };
                }),
            }),
            onSuccess: () => {
                anuncio.value = `«${titulo}» movida a ${etiquetas.value[estado] ?? estado}.`;
            },
        },
    );
}

/* ------------------------------------------------------------- Descartar */

/*
 * Descartar no es una columna ni un gesto: es una decisión que un auditor puede
 * cuestionar, y sin motivo el requisito se queda sin rastro de qué se hizo con
 * él. Por eso vive en un diálogo y no en el tablero.
 */
const descartando = ref<{ id: number; titulo: string } | null>(null);
const motivo = ref('');
const enviando = ref(false);

function descartar(id: number, titulo: string): void {
    descartando.value = { id, titulo };
    motivo.value = '';
}

function confirmarDescarte(): void {
    if (descartando.value === null || motivo.value.trim() === '') {
        return;
    }

    router.post(
        `/tareas/${descartando.value.id}/estado`,
        { estado: 'descartada', nota: motivo.value },
        {
            preserveScroll: true,
            onStart: () => (enviando.value = true),
            onFinish: () => {
                enviando.value = false;
                descartando.value = null;
            },
        },
    );
}
</script>

<template>
    <AppLayout titulo="Tablero">
        <CabeceraPagina
            titulo="Plan de acción"
            descripcion="En qué punto está cada cosa. Arrastra una tarjeta a otra columna, o muévela desde su menú."
        >
            <template #acciones>
                <ConmutadorVista />
            </template>
        </CabeceraPagina>

        <p aria-live="polite" class="sr-only">{{ anuncio }}</p>

        <!--
            El tablero acota su propia altura: `AppLayout` no lo hace, y sin esto
            la página crece hacia abajo y las cuatro columnas dejan de verse a la
            vez, que es lo único que un tablero aporta sobre una tabla.

            Por debajo de `lg`, una columna por fila: cuatro columnas a 400 px no
            son un tablero, son cuatro listas estrechas.
        -->
        <div
            class="grid gap-3 lg:h-[calc(100vh-15rem)] lg:grid-cols-4"
            role="group"
            aria-label="Columnas del tablero"
        >
            <ColumnaTablero
                v-for="columna in columnas"
                :key="columna.estado"
                :columna="columna"
                :etiquetas="etiquetas"
                @mover="mover"
                @descartar="descartar"
            />
        </div>

        <p class="mt-3 text-xs text-muted-foreground">
            En «Hecha» sólo se ven las cerradas en los últimos {{ recientes }} días. El resto está en la
            tabla, junto con las descartadas.
        </p>

        <Dialog :open="descartando !== null" @update:open="(abierto) => !abierto && (descartando = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Descartar la tarea</DialogTitle>
                    <DialogDescription>
                        «{{ descartando?.titulo }}» quedará cerrada sin hacerse. Es una decisión que un auditor
                        puede cuestionar: sin motivo, el requisito se queda sin rastro de qué se hizo con él.
                    </DialogDescription>
                </DialogHeader>

                <CampoTextarea
                    v-model="motivo"
                    nombre="nota"
                    etiqueta="Por qué se descarta"
                    :filas="3"
                    requerido
                />

                <DialogFooter>
                    <Button variant="outline" @click="descartando = null">Cancelar</Button>
                    <Button :disabled="enviando || motivo.trim() === ''" @click="confirmarDescarte">
                        Descartar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
