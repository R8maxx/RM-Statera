<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { aMarkdown } from '@/lib/markdownEditor';
import Link from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import { Editor, EditorContent } from '@tiptap/vue-3';
import {
    BoldIcon,
    Heading2Icon,
    Heading3Icon,
    ItalicIcon,
    LinkIcon,
    ListIcon,
    ListOrderedIcon,
} from '@lucide/vue';
import { onBeforeUnmount, ref, shallowRef, watch } from 'vue';

/**
 * El editor de los textos de un documento.
 *
 * **En ProseMirror el esquema ES la lista blanca**: lo que no está declarado no
 * se puede crear, ni escribiendo, ni pegando, ni arrastrando. No hay filtrado a
 * posteriori del HTML pegado, que es justo la clase de código que no se quiere
 * en una herramienta que entra en el alcance de su propio SGSI.
 *
 * Se carga con `defineAsyncComponent` desde las dos pantallas que lo usan, así
 * que ProseMirror va en su propio chunk y no lo paga quien nunca edita textos.
 * Mismo criterio que `@number-flow/vue`.
 *
 * **Guarda Markdown, no HTML**, en un `<input type="hidden">` — el mismo
 * mecanismo que `CampoSelect` y `CampoCasillas`, para que el `<Form>` de Inertia
 * lo recoja del `FormData` sin `v-model`.
 */
const props = defineProps<{
    nombre: string;
    /** HTML de partida, ya saneado en el servidor y sin bajar los encabezados. */
    html: string;
    atributos: Record<string, unknown>;
}>();

const emit = defineEmits<{ cambio: [string] }>();

const markdown = ref('');
const editor = shallowRef<Editor>();

editor.value = new Editor({
    content: props.html,
    extensions: [
        StarterKit.configure({
            /*
             * Configurado a la contra: se apaga todo lo que el documento no
             * admite. Un bloque de código o una cita en una Declaración de
             * Aplicabilidad no significan nada, y las imágenes tumbarían la
             * generación del PDF.
             */
            heading: { levels: [2, 3] },
            codeBlock: false,
            blockquote: false,
            horizontalRule: false,
            code: false,
            strike: false,
            link: false,
        }),
        Link.configure({
            openOnClick: false,
            autolink: false,
            protocols: ['http', 'https', 'mailto'],
            HTMLAttributes: { rel: 'noopener noreferrer' },
        }),
    ],
    editorProps: {
        attributes: {
            // `data-campo` va AQUÍ y no en el hidden: es el contrato del resumen
            // de errores de `FormularioRecurso`, que busca el elemento enfocable.
            ...(props.atributos as Record<string, string>),
            role: 'textbox',
            'aria-multiline': 'true',
            class: 'prosa-editor min-h-32 px-3 py-2 focus:outline-none',
        },
    },
    onUpdate({ editor: instancia }) {
        markdown.value = aMarkdown(instancia.state.doc);
        emit('cambio', markdown.value);
    },
});

markdown.value = aMarkdown(editor.value.state.doc);

/* Si el servidor manda otro contenido —restablecer una sección—, se recarga. */
watch(
    () => props.html,
    (nuevo) => {
        if (editor.value && nuevo !== editor.value.getHTML()) {
            editor.value.commands.setContent(nuevo, { emitUpdate: false });
            markdown.value = aMarkdown(editor.value.state.doc);
        }
    },
);

onBeforeUnmount(() => editor.value?.destroy());

function enlazar(): void {
    const actual = String(editor.value?.getAttributes('link').href ?? '');
    const url = window.prompt('Dirección del enlace', actual || 'https://');

    if (url === null) {
        return;
    }

    if (url === '') {
        editor.value?.chain().focus().unsetLink().run();

        return;
    }

    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

/** Los siete botones, y sólo siete. */
const herramientas = [
    { clave: 'bold', etiqueta: 'Negrita', icono: BoldIcon, accion: () => editor.value?.chain().focus().toggleBold().run(), activo: () => editor.value?.isActive('bold') },
    { clave: 'italic', etiqueta: 'Cursiva', icono: ItalicIcon, accion: () => editor.value?.chain().focus().toggleItalic().run(), activo: () => editor.value?.isActive('italic') },
    { clave: 'h2', etiqueta: 'Título', icono: Heading2Icon, accion: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(), activo: () => editor.value?.isActive('heading', { level: 2 }) },
    { clave: 'h3', etiqueta: 'Subtítulo', icono: Heading3Icon, accion: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run(), activo: () => editor.value?.isActive('heading', { level: 3 }) },
    { clave: 'ul', etiqueta: 'Lista', icono: ListIcon, accion: () => editor.value?.chain().focus().toggleBulletList().run(), activo: () => editor.value?.isActive('bulletList') },
    { clave: 'ol', etiqueta: 'Lista numerada', icono: ListOrderedIcon, accion: () => editor.value?.chain().focus().toggleOrderedList().run(), activo: () => editor.value?.isActive('orderedList') },
    { clave: 'link', etiqueta: 'Enlace', icono: LinkIcon, accion: enlazar, activo: () => editor.value?.isActive('link') },
];
</script>

<template>
    <div class="overflow-hidden rounded-md border border-input bg-transparent focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2">
        <div
            role="toolbar"
            aria-label="Formato del texto"
            class="flex flex-wrap items-center gap-0.5 border-b border-border bg-muted/40 px-1.5 py-1"
        >
            <Button
                v-for="herramienta in herramientas"
                :key="herramienta.clave"
                type="button"
                variant="ghost"
                size="icon-sm"
                :aria-label="herramienta.etiqueta"
                :aria-pressed="herramienta.activo() ? 'true' : 'false'"
                :class="herramienta.activo() ? 'bg-accent text-accent-foreground' : undefined"
                @click="herramienta.accion()"
            >
                <component :is="herramienta.icono" class="size-4" />
            </Button>
        </div>

        <EditorContent :editor="editor" />

        <input type="hidden" :name="nombre" :value="markdown" />
    </div>
</template>

<style scoped>
/*
 * Tokens de ROL, nunca de escala: así el modo oscuro no obliga a tocar nada.
 * Y sin `@tailwindcss/typography`, que trae su propia paleta y su propia escala
 * tipográfica y habría que desactivar casi enteras.
 *
 * Los tamaños replican los del documento para que lo que se ve al escribir se
 * parezca a lo que va a salir impreso.
 */
.prosa-editor :deep(p) {
    margin: 0 0 0.7em;
}

.prosa-editor :deep(p:last-child),
.prosa-editor :deep(ul:last-child),
.prosa-editor :deep(ol:last-child) {
    margin-bottom: 0;
}

.prosa-editor :deep(h2) {
    font-size: 1rem;
    font-weight: 600;
    margin: 1em 0 0.4em;
}

.prosa-editor :deep(h3) {
    font-size: 0.9375rem;
    font-weight: 600;
    margin: 0.9em 0 0.35em;
}

.prosa-editor :deep(ul),
.prosa-editor :deep(ol) {
    margin: 0 0 0.7em;
    padding-left: 1.3em;
}

.prosa-editor :deep(ul) {
    list-style: disc;
}

.prosa-editor :deep(ol) {
    list-style: decimal;
}

.prosa-editor :deep(li) {
    margin-bottom: 0.2em;
}

.prosa-editor :deep(a) {
    color: var(--primary);
    text-decoration: underline;
    text-underline-offset: 2px;
}

/* El aviso de campo vacío, para que no parezca que el editor no ha cargado. */
.prosa-editor :deep(p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    color: var(--muted-foreground);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
