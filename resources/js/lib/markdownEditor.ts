import { MarkdownSerializer, type MarkdownSerializerState } from 'prosemirror-markdown';
import type { Node as NodoProseMirror, Mark } from '@tiptap/pm/model';

/**
 * El serializador a Markdown de lo que se escribe en el editor.
 *
 * **Se declara a mano, y ése es el argumento.** Un paquete que «detecta» el
 * Markdown automáticamente hace lo contrario de lo que aquí interesa: este mapa
 * es la lista blanca escrita otra vez, ahora en el camino de escritura. Lo que
 * no está aquí no se puede serializar, y por tanto no se puede guardar.
 *
 * El de `prosemirror-markdown` no vale tal cual porque está escrito para los
 * nombres de nodo de `prosemirror-schema-basic` (`bullet_list`, `em`, `strong`)
 * y TipTap usa los suyos (`bulletList`, `italic`, `bold`).
 *
 * Fuera, a propósito: tablas —los datos del documento se calculan, no se
 * escriben—, imágenes —una sola tumba la generación del PDF entero—, código,
 * citas y líneas horizontales.
 */
export const serializadorMarkdown = new MarkdownSerializer(
    {
        paragraph(state: MarkdownSerializerState, node: NodoProseMirror) {
            state.renderInline(node);
            state.closeBlock(node);
        },

        heading(state: MarkdownSerializerState, node: NodoProseMirror) {
            state.write(`${'#'.repeat(node.attrs.level as number)} `);
            state.renderInline(node);
            state.closeBlock(node);
        },

        bulletList(state: MarkdownSerializerState, node: NodoProseMirror) {
            state.renderList(node, '  ', () => '- ');
        },

        orderedList(state: MarkdownSerializerState, node: NodoProseMirror) {
            const inicio = (node.attrs.start as number | undefined) ?? 1;
            const anchura = String(inicio + node.childCount - 1).length;
            const relleno = state.repeat(' ', anchura + 2);

            state.renderList(node, relleno, (i: number) => {
                const etiqueta = String(inicio + i);

                return `${state.repeat(' ', anchura - etiqueta.length) + etiqueta}. `;
            });
        },

        listItem(state: MarkdownSerializerState, node: NodoProseMirror) {
            state.renderContent(node);
        },

        hardBreak(state: MarkdownSerializerState, node: NodoProseMirror, padre: NodoProseMirror, indice: number) {
            for (let i = indice + 1; i < padre.childCount; i++) {
                if (padre.child(i).type !== node.type) {
                    state.write('\\\n');

                    return;
                }
            }
        },

        text(state: MarkdownSerializerState, node: NodoProseMirror) {
            state.text(node.text ?? '', false);
        },
    },
    {
        bold: { open: '**', close: '**', mixable: true, expelEnclosingWhitespace: true },
        italic: { open: '*', close: '*', mixable: true, expelEnclosingWhitespace: true },
        link: {
            open: '[',
            close(_state: MarkdownSerializerState, mark: Mark) {
                const href = String(mark.attrs.href ?? '');
                const titulo = mark.attrs.title ? ` "${String(mark.attrs.title).replace(/"/g, '\\"')}"` : '';

                return `](${href}${titulo})`;
            },
        },
    },
);

/** El Markdown de un documento del editor, normalizado como lo guarda el servidor. */
export function aMarkdown(documento: NodoProseMirror): string {
    return serializadorMarkdown.serialize(documento).replace(/\n{3,}/g, '\n\n').trim();
}
