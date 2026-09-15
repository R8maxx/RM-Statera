<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { motion } from 'motion-v';
import { cn } from '@/lib/utils';
import type { Editor } from '@tiptap/vue-3';
import { computed } from 'vue';

/**
 * El índice del documento.
 *
 * Una SoA son ocho secciones y una tabla de noventa y tres filas: llegar al
 * final a rueda de ratón es de lo que más molesta de redactar aquí. El índice
 * sale del propio documento —los hijos de primer nivel y el texto de su primer
 * encabezado—, así que una sección que alguien acabe de insertar aparece sola,
 * sin ninguna lista que mantener al lado.
 *
 * `version` entra como prop y no se lee del editor porque `editor.state` no es
 * reactivo: quien lleva la cuenta es el `onTransaction` de `EditorCuerpo`, que
 * ya la llevaba para la barra.
 *
 * **Se oculta por debajo de `lg`**, como la balanza del acceso: una hoja A4
 * apaisada ya no cabe en una pantalla estrecha, y quitarle además cien píxeles
 * de ancho no ayuda a nadie.
 */
const props = defineProps<{ editor: Editor | undefined; version: number }>();

type Entrada = { pos: number; titulo: string; esPortada: boolean };

const entradas = computed<Entrada[]>(() => {
    /* Se lee `version` para que Vue recalcule en cada transacción del editor. */
    void props.version;

    const editor = props.editor;

    if (editor === undefined) {
        return [];
    }

    const lista: Entrada[] = [];
    let pos = 0;

    editor.state.doc.forEach((nodo) => {
        const inicio = pos;
        pos += nodo.nodeSize;

        let titulo = '';

        nodo.descendants((hijo) => {
            if (titulo !== '' || hijo.type.name !== 'heading') {
                return titulo === '';
            }

            titulo = hijo.textContent.trim();

            return false;
        });

        lista.push({
            pos: inicio,
            esPortada: nodo.type.name === 'portada',
            titulo: titulo !== '' ? titulo : nodo.type.name === 'portada' ? 'Portada' : 'Sección sin título',
        });
    });

    return lista;
});

/** La sección donde está el cursor, para saber dónde se está. */
const activa = computed<number>(() => {
    void props.version;

    const editor = props.editor;

    if (editor === undefined) {
        return -1;
    }

    return editor.state.selection.$from.depth === 0 ? -1 : editor.state.selection.$from.index(0);
});

function ir(entrada: Entrada, indice: number): void {
    const editor = props.editor;

    if (editor === undefined) {
        return;
    }

    /*
     * Se coloca el cursor DENTRO de la sección y se deja que ProseMirror
     * desplace: `scrollIntoView()` conoce el desbordamiento del lienzo y el
     * zoom, y `scrollTo` a mano se equivoca en los dos.
     */
    editor.chain().focus().setTextSelection(entrada.pos + 1).scrollIntoView().run();

    void indice;
}
const { reducido } = useMovimientoReducido();
</script>

<template>
    <nav aria-label="Secciones del documento" class="hidden w-56 shrink-0 lg:block">
        <div class="sticky top-20">
            <p class="mb-2 px-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                Secciones
            </p>
            <ol class="space-y-0.5">
                <li v-for="(entrada, indice) in entradas" :key="entrada.pos" class="relative">
                    <!--
                        La marca de sección activa se desliza, y aquí SÍ se puede:
                        el índice es un componente persistente dentro de la misma
                        pantalla. En el sidebar no —`AppLayout` se monta dentro de
                        cada página, así que entre dos secciones no hay ningún
                        elemento compartido que mover— y por eso allí sigue
                        apareciendo y desapareciendo.

                        Se mueve sola con el scroll, sin que nadie pulse nada, y
                        ver de dónde a dónde va es lo que dice por qué se ha
                        movido.
                    -->
                    <motion.span
                        v-if="indice === activa"
                        :layout-id="reducido ? undefined : 'indice-documento-activa'"
                        :transition="{ duration: reducido ? 0 : 0.28, ease: [0.77, 0, 0.175, 1] }"
                        class="absolute inset-y-1 left-0 w-0.5 rounded-full bg-primary"
                        aria-hidden="true"
                    />

                    <button
                        type="button"
                        :aria-current="indice === activa ? 'true' : undefined"
                        :class="
                            cn(
                                'w-full rounded-md px-2 py-1.5 text-left text-sm',
                                'transition-colors duration-[var(--duracion-rapida)] ease-marca',
                                'outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50',
                                indice === activa
                                    ? 'bg-accent font-medium text-accent-foreground'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            )
                        "
                        @click="ir(entrada, indice)"
                    >
                        {{ entrada.titulo }}
                    </button>
                </li>
            </ol>
        </div>
    </nav>
</template>
