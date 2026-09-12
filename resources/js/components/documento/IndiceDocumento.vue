<script setup lang="ts">
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
</script>

<template>
    <nav aria-label="Secciones del documento" class="hidden w-56 shrink-0 lg:block">
        <div class="sticky top-20">
            <p class="mb-2 px-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                Secciones
            </p>
            <ol class="space-y-0.5">
                <li v-for="(entrada, indice) in entradas" :key="entrada.pos">
                    <button
                        type="button"
                        :aria-current="indice === activa ? 'true' : undefined"
                        :class="
                            cn(
                                'w-full rounded-md px-2 py-1.5 text-left text-sm transition-colors',
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
