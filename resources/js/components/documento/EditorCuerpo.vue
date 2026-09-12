<script setup lang="ts">
import IndiceDocumento from '@/components/documento/IndiceDocumento.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { extensionesCuerpo } from '@/lib/cuerpoDocumento';
import { RAIZ, montarHojaDocumento } from '@/lib/hojaDocumento';
import { Editor, EditorContent } from '@tiptap/vue-3';
import { useResizeObserver } from '@vueuse/core';
import {
    BoldIcon,
    Heading2Icon,
    Heading3Icon,
    ItalicIcon,
    LinkIcon,
    ListIcon,
    ListOrderedIcon,
    PlusIcon,
    Rows3Icon,
    SearchIcon,
    Trash2Icon,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, useTemplateRef } from 'vue';

/**
 * El documento entero, editable, sobre una hoja.
 *
 * **El esquema es la lista blanca** (`lib/cuerpoDocumento.ts`): lo que no está
 * declarado no se puede crear, ni escribiendo, ni pegando, ni arrastrando. El
 * servidor vuelve a comprobarlo con `SanearCuerpo`, porque la ruta se puede
 * llamar sin pasar por aquí.
 *
 * ## Lo que se ve es lo que se imprime, y literalmente
 *
 * Las etiquetas y las clases son las mismas que emite `RenderizadorCuerpo`, y la
 * hoja de estilos **es `resources/documentos/documento.css`**, acotada al editor
 * por `lib/hojaDocumento.ts`. Antes había aquí una segunda hoja escrita a mano
 * que la imitaba con tokens de la interfaz: dos hojas que describen el mismo
 * documento divergen, y lo que quedaba abajo sólo se parecía al PDF de lejos.
 *
 * Lo que queda en este fichero es lo que **no** pertenece al documento impreso:
 * el papel, el distintivo de procedencia y el cursor. Nada de tipografía, nada
 * de color, nada de espaciado del documento.
 *
 * Va en su propio chunk con `defineAsyncComponent`: quien nunca edita un
 * documento no paga ProseMirror ni las tablas.
 */
const props = defineProps<{
    cuerpo: Record<string, unknown>;
    /** Las medidas con las que Gotenberg imprime, desde `GeometriaPagina`. */
    geometria: { ancho: number; alto: number; margenSuperior: number; margenInferior: number; margenLateral: number };
    margenes: {
        organizacion: string;
        codigo: string;
        titulo: string;
        clasificacion: string;
        version: string;
        fecha: string;
    };
}>();

/**
 * `normalizado` y `cambio` son dos cosas distintas a propósito.
 *
 * Tiptap normaliza el árbol al cargarlo —rellena atributos por defecto, ordena
 * las marcas— y el padre necesita ese JSON, porque es el que se enviará al
 * guardar. Pero eso NO es una edición: emitirlo como `cambio` marcaba el
 * documento sucio nada más abrirlo, y el guardado que eso provocaba sellaba
 * `editado_en`. Un documento acababa declarando en portada que se había editado
 * a mano por el hecho de abrirlo, que es justo lo que la procedencia existe para
 * impedir.
 */
const emit = defineEmits<{
    normalizado: [Record<string, unknown>];
    cambio: [Record<string, unknown>];
}>();

const editor = shallowRef<Editor>();
const version = shallowRef(0);

editor.value = new Editor({
    content: props.cuerpo,
    extensions: extensionesCuerpo(),
    editorProps: {
        attributes: {
            role: 'textbox',
            'aria-multiline': 'true',
            'aria-label': 'Cuerpo del documento',
            class: `${RAIZ} focus:outline-none`,
        },
    },
    onUpdate({ editor: instancia }) {
        emit('cambio', instancia.getJSON() as Record<string, unknown>);
    },
    /* Redibuja la barra y el índice: `isActive` no es reactivo por sí solo. */
    onSelectionUpdate() {
        version.value += 1;
    },
    onTransaction() {
        version.value += 1;
    },
});

emit('normalizado', editor.value.getJSON() as Record<string, unknown>);

let desmontarHoja: (() => void) | null = null;

onMounted(() => {
    desmontarHoja = montarHojaDocumento();
});

onBeforeUnmount(() => {
    editor.value?.destroy();
    desmontarHoja?.();
});

/* ------------------------------------------------------------------- La hoja */

/**
 * Las medidas viajan desde PHP y se pintan como variables, nunca escritas aquí.
 *
 * `GeometriaPagina` es la misma fuente que consume `GotenbergHttp`: si mañana un
 * tipo de documento pasa a vertical, esta hoja cambia sola.
 *
 * La cabecera y el pie son **una representación**. Chromium los renderiza de
 * verdad en un contexto aparte, con su propio CSS y sin heredar fuentes, así que
 * aquí sólo se reserva el hueco y se dice qué va a ocuparlo. El número de página
 * no aparece: en el editor no se sabe, y una cifra inventada dentro de un
 * documento es peor que un hueco.
 */
const hoja = computed(() => ({
    '--hoja-ancho': `${props.geometria.ancho}in`,
    '--hoja-alto': `${props.geometria.alto}in`,
    '--hoja-margen-sup': `${props.geometria.margenSuperior}in`,
    '--hoja-margen-inf': `${props.geometria.margenInferior}in`,
    '--hoja-margen-lat': `${props.geometria.margenLateral}in`,
    '--hoja-cabecera': JSON.stringify(
        `${props.margenes.organizacion} · ${props.margenes.codigo} — ${props.margenes.titulo}`,
    ),
    '--hoja-pie': JSON.stringify(
        `${props.margenes.clasificacion} · ${props.margenes.version} · ${props.margenes.fecha} · Generado por Statera`,
    ),
}));

/* --------------------------------------------------------------------- Zoom */

/**
 * El documento se compone a 8,5 pt, que en pantalla son once píxeles.
 *
 * Se escala con la propiedad `zoom` y no con `transform: scale`: la segunda deja
 * las coordenadas del ratón sin escalar y el cursor de ProseMirror empieza a
 * caer una línea más arriba de donde se pulsa.
 *
 * La preferencia se guarda en el navegador, como la vista de las tablas: es de
 * un puesto de trabajo, no un dato de la organización, así que no viaja al
 * servidor ni cruza la frontera del tenant.
 */
const CLAVE = 'statera.documento.zoom';
const NIVELES = [1, 1.25, 1.5] as const;

const preferencia = ref<'ajustar' | number>(leerPreferencia());
const anchoLienzo = ref(0);
const lienzo = useTemplateRef<HTMLElement>('lienzo');

useResizeObserver(lienzo, ([entrada]) => {
    anchoLienzo.value = entrada.contentRect.width;
});

const zoom = computed<number>(() => {
    if (preferencia.value !== 'ajustar') {
        return preferencia.value;
    }

    if (anchoLienzo.value === 0) {
        return 1;
    }

    /* 96 px por pulgada es la definición de `in` en CSS, no una estimación. */
    return Math.min(1.5, Math.max(0.4, anchoLienzo.value / (props.geometria.ancho * 96)));
});

const etiquetaZoom = computed(() =>
    preferencia.value === 'ajustar' ? 'Ajustar' : `${Math.round(preferencia.value * 100)} %`,
);

function leerPreferencia(): 'ajustar' | number {
    try {
        const guardado = window.localStorage.getItem(CLAVE);

        if (guardado === 'ajustar') {
            return 'ajustar';
        }

        const numero = Number(guardado);

        return NIVELES.includes(numero as (typeof NIVELES)[number]) ? numero : 'ajustar';
    } catch {
        /* Ventana privada o almacenamiento bloqueado: se sigue sin recordar. */
        return 'ajustar';
    }
}

function fijarZoom(valor: 'ajustar' | number): void {
    preferencia.value = valor;

    try {
        window.localStorage.setItem(CLAVE, String(valor));
    } catch {
        /* Sin sitio donde recordarlo, pero la sesión funciona igual. */
    }
}

/* ------------------------------------------------------------ La barra */

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

/**
 * La barra.
 *
 * Corta a propósito: no hay tamaños de letra, ni colores, ni alineación. El
 * diseño del documento lo decide `documento.css`, no quien escribe, y una barra
 * con veinte botones es cómo un entregable acaba con cuatro tipografías.
 */
const herramientas = [
    { clave: 'bold', etiqueta: 'Negrita', icono: BoldIcon, accion: () => editor.value?.chain().focus().toggleBold().run(), activo: () => editor.value?.isActive('bold') },
    { clave: 'italic', etiqueta: 'Cursiva', icono: ItalicIcon, accion: () => editor.value?.chain().focus().toggleItalic().run(), activo: () => editor.value?.isActive('italic') },
    { clave: 'h2', etiqueta: 'Título de sección', icono: Heading2Icon, accion: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(), activo: () => editor.value?.isActive('heading', { level: 2 }) },
    { clave: 'h3', etiqueta: 'Subtítulo', icono: Heading3Icon, accion: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run(), activo: () => editor.value?.isActive('heading', { level: 3 }) },
    { clave: 'ul', etiqueta: 'Lista', icono: ListIcon, accion: () => editor.value?.chain().focus().toggleBulletList().run(), activo: () => editor.value?.isActive('bulletList') },
    { clave: 'ol', etiqueta: 'Lista numerada', icono: ListOrderedIcon, accion: () => editor.value?.chain().focus().toggleOrderedList().run(), activo: () => editor.value?.isActive('orderedList') },
    { clave: 'link', etiqueta: 'Enlace', icono: LinkIcon, accion: enlazar, activo: () => editor.value?.isActive('link') },
];

/** Las de tabla sólo tienen sentido dentro de una, y ahí se enseñan. */
const deTabla = [
    { clave: 'fila', etiqueta: 'Añadir fila debajo', icono: Rows3Icon, accion: () => editor.value?.chain().focus().addRowAfter().run() },
    { clave: 'borrarFila', etiqueta: 'Eliminar fila', icono: Trash2Icon, accion: () => editor.value?.chain().focus().deleteRow().run() },
];

/* ---------------------------------------------------------------- Insertar */

/**
 * La sección de primer nivel donde está el cursor.
 *
 * El documento es `portada seccion+`, así que el nivel 1 siempre existe salvo
 * con la selección en la raíz. Se devuelven las dos posiciones porque insertar
 * quiere el final y eliminar quiere el tramo entero.
 */
function seccionActual(): { indice: number; desde: number; hasta: number } | null {
    const estado = editor.value?.state;

    if (estado === undefined || estado.selection.$from.depth === 0) {
        return null;
    }

    const indice = estado.selection.$from.index(0);
    const desde = estado.selection.$from.before(1);

    return { indice, desde, hasta: desde + estado.doc.child(indice).nodeSize };
}

const enSeccion = computed(() => {
    void version.value;

    return seccionActual();
});

/**
 * Dos restricciones que la interfaz dice en vez de dejar que fallen solas.
 *
 * La primera es del esquema: `doc` es `portada seccion+`, así que no se inserta
 * antes de la portada ni se borra la última sección — ProseMirror rechazaría la
 * transacción y el botón parecería roto.
 */
const puedeInsertarSeccion = computed(() => enSeccion.value !== null);

const puedeBorrarSeccion = computed(() => {
    void version.value;

    const actual = enSeccion.value;
    const total = editor.value?.state.doc.childCount ?? 0;

    /* La portada no es una sección, y por eso el mínimo es «portada + una». */
    return actual !== null && actual.indice > 0 && total > 2;
});

function insertarSeccion(): void {
    const actual = seccionActual();

    if (actual === null) {
        return;
    }

    editor.value
        ?.chain()
        .focus()
        .insertContentAt(actual.hasta, {
            type: 'seccion',
            content: [
                { type: 'heading', attrs: { level: 2 }, content: [{ type: 'text', text: 'Sección nueva' }] },
                { type: 'paragraph' },
            ],
        })
        .run();
}

function insertarCaja(variante: 'simple' | 'marca'): void {
    editor.value
        ?.chain()
        .focus()
        .insertContent({ type: 'caja', attrs: { variante }, content: [{ type: 'paragraph' }] })
        .run();
}

function insertarTabla(): void {
    editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
}

function borrarSeccion(): void {
    const actual = seccionActual();

    if (actual === null || !puedeBorrarSeccion.value) {
        return;
    }

    editor.value?.chain().focus().deleteRange({ from: actual.desde, to: actual.hasta }).run();
}
</script>

<template>
    <div class="flex gap-6">
        <IndiceDocumento :editor="editor" :version="version" />

        <div class="min-w-0 flex-1">
            <div
                role="toolbar"
                aria-label="Formato del documento"
                class="sticky top-0 z-10 flex flex-wrap items-center gap-0.5 rounded-t-xl border border-border bg-card px-2 py-1.5"
            >
                <Button
                    v-for="herramienta in herramientas"
                    :key="herramienta.clave + version"
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="herramienta.etiqueta"
                    :title="herramienta.etiqueta"
                    :aria-pressed="herramienta.activo() ? 'true' : 'false'"
                    :class="herramienta.activo() ? 'bg-accent text-accent-foreground' : undefined"
                    @click="herramienta.accion()"
                >
                    <component :is="herramienta.icono" class="size-4" />
                </Button>

                <template v-if="editor?.isActive('table')">
                    <span aria-hidden="true" class="mx-1 h-5 w-px bg-border" />
                    <Button
                        v-for="herramienta in deTabla"
                        :key="herramienta.clave"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="herramienta.etiqueta"
                        :title="herramienta.etiqueta"
                        @click="herramienta.accion()"
                    >
                        <component :is="herramienta.icono" class="size-4" />
                    </Button>
                </template>

                <span aria-hidden="true" class="mx-1 h-5 w-px bg-border" />

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button type="button" variant="ghost" size="sm">
                            <PlusIcon class="size-4" />
                            Insertar
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-64">
                        <DropdownMenuItem :disabled="!puedeInsertarSeccion" @select="insertarSeccion">
                            Sección nueva, detrás de ésta
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="insertarCaja('simple')">Caja</DropdownMenuItem>
                        <DropdownMenuItem @select="insertarCaja('marca')">Caja destacada</DropdownMenuItem>
                        <DropdownMenuItem @select="insertarTabla">Tabla de 3 × 3</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            :disabled="!puedeBorrarSeccion"
                            variant="destructive"
                            @select="borrarSeccion"
                        >
                            Eliminar esta sección
                        </DropdownMenuItem>
                        <!--
                            No es una etiqueta de grupo, es una advertencia: va
                            como párrafo y no como `DropdownMenuLabel`, que
                            anuncia a un lector de pantalla un grupo de opciones
                            que aquí no existe.
                        -->
                        <p class="px-2 py-1.5 text-xs text-muted-foreground">
                            Las limitaciones y el control de versiones se vuelven a poner al generar: son
                            lo que declara que el documento se ha editado a mano.
                        </p>
                    </DropdownMenuContent>
                </DropdownMenu>

                <span class="ml-auto" />

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button type="button" variant="ghost" size="sm" aria-label="Tamaño de la hoja">
                            <SearchIcon class="size-4" />
                            {{ etiquetaZoom }}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-44">
                        <DropdownMenuCheckboxItem
                            :model-value="preferencia === 'ajustar'"
                            @select="fijarZoom('ajustar')"
                        >
                            Ajustar al ancho
                        </DropdownMenuCheckboxItem>
                        <DropdownMenuCheckboxItem
                            v-for="nivel in NIVELES"
                            :key="nivel"
                            :model-value="preferencia === nivel"
                            @select="fijarZoom(nivel)"
                        >
                            {{ Math.round(nivel * 100) }} %
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <div ref="lienzo" class="escritorio rounded-b-xl border border-t-0 border-border">
                <div :style="{ ...hoja, zoom }">
                    <EditorContent :editor="editor" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/*
 * Aquí sólo vive lo que NO pertenece al documento impreso.
 *
 * La tipografía, el color y el espaciado los pone `documento.css` tal cual, sin
 * copiarla, desde `lib/hojaDocumento.ts`. Si algo de lo de abajo empieza a
 * parecerse a una regla del documento, está en el fichero equivocado.
 *
 * **Los colores van en hex y no en tokens, y es por dónde está cada cosa.** El
 * escritorio y el cromo de la hoja son el elemento que CONTIENE al `@scope`, así
 * que las variables de `documento.css` no llegan hasta aquí; y los tokens de
 * `app.css` tampoco valen, porque este trozo es papel y se queda claro en los dos
 * temas. Son los neutros sRGB de DESIGN.md §3 —`border`, `muted-foreground`,
 * `foreground`— y el `marca-600` y el `estado-en-progreso` de la misma tabla: no
 * hay ni un valor inventado.
 */

/* ------------------------------------------------------------- El escritorio */

/*
 * El papel es blanco en los dos temas, como lo sería una fotografía. Es una
 * superficie de documento, no una sección de la aplicación que se haya quedado
 * sin invertir — el mismo criterio que el panel de marca del acceso.
 *
 * `color-scheme: light` además de la paleta: sin él, las barras de
 * desplazamiento del lienzo salen oscuras sobre el papel.
 */
.escritorio {
    overflow: auto;
    /* `border` de DESIGN.md §3: el neutro más oscuro que sigue siendo fondo, que
       es lo que separa el papel del escritorio sin competir con él. */
    background: #dae1e3;
    color-scheme: light;
    /* El hueco alrededor es lo que hace que la hoja se lea como una hoja: sin
       él, el papel llega a los bordes y vuelve a parecer un formulario. */
    padding: 2rem;
}

/* ------------------------------------------------------------------ La hoja */

/*
 * Cada sección de primer nivel es una hoja, y no es una licencia: `documento.css`
 * declara `.seccion { break-before: page }`, así que toda sección empieza página
 * de verdad. Una sección larga ocupará varias páginas en el PDF y aquí sale como
 * una hoja alta: es la única aproximación, y para la paginación exacta está
 * «Ver el PDF».
 */
.escritorio :deep(.documento-editor > section) {
    width: var(--hoja-ancho);
    min-height: var(--hoja-alto);
    margin: 0 auto 3rem;
    padding: var(--hoja-margen-sup) var(--hoja-margen-lat) var(--hoja-margen-inf);
    background: #ffffff;
    box-shadow: 0 1px 3px rgb(21 30 36 / 0.16), 0 8px 24px rgb(21 30 36 / 0.08);
    position: relative;
}

.escritorio :deep(.documento-editor > section:last-child) {
    margin-bottom: 0;
}

/*
 * Cabecera y pie: el hueco que reservan los márgenes, con lo que va a ocuparlo.
 *
 * Es una representación y no una copia de `documentos/cabecera.blade.php`: el de
 * verdad lo renderiza Chromium en un contexto aparte. Que se repita en cada hoja
 * es, de paso, lo que hace evidente dónde empieza una página.
 */
.escritorio :deep(.documento-editor > section::before),
.escritorio :deep(.documento-editor > section::after) {
    position: absolute;
    left: var(--hoja-margen-lat);
    right: var(--hoja-margen-lat);
    font-size: 7pt;
    color: #5d6c72;
    pointer-events: none;
}

.escritorio :deep(.documento-editor > section::before) {
    content: var(--hoja-cabecera);
    top: calc(var(--hoja-margen-sup) - 0.22in);
    padding-bottom: 2pt;
    border-bottom: 0.5pt solid #dae1e3;
}

.escritorio :deep(.documento-editor > section::after) {
    content: var(--hoja-pie);
    bottom: calc(var(--hoja-margen-inf) - 0.22in);
    padding-top: 2pt;
    border-top: 0.5pt solid #dae1e3;
}

/* --------------------------------------------- Lo que el documento no tiene */

/*
 * La ficha de la portada es una rejilla de dos columnas cuyos hijos son
 * `.ficha__clave` y `.ficha__valor` sueltos. El editor necesita un elemento por
 * nodo, así que envuelve cada pareja; `display: contents` hace que la rejilla vea
 * los nietos. Sin esto la portada colapsa a una columna, y es la comprobación
 * más rápida de que la hoja del documento está puesta de verdad.
 */
.escritorio :deep(.ficha-fila) {
    display: contents;
}

/*
 * El grupo de bloques calculados desaparece al imprimir —`RenderizadorCuerpo`
 * pinta sus hijos y nada más—, y por eso `documento.css` no lo conoce. Aquí se
 * queda como bloque llano, sin `display: contents`, porque **es el elemento que
 * lleva el distintivo de procedencia**: todo lo que sale del registro es un
 * `grupo` con su `data-fuente`, y un elemento sin caja no puede pintar ni un
 * filete ni una etiqueta.
 */

/* La celda seleccionada, para saber dónde actúan los botones de tabla. */
.escritorio :deep(.documento-editor td),
.escritorio :deep(.documento-editor th) {
    position: relative;
}

.escritorio :deep(.selectedCell::after) {
    content: '';
    position: absolute;
    inset: 0;
    background: #007e81;
    opacity: 0.14;
    pointer-events: none;
}

/*
 * El distintivo de bloque calculado.
 *
 * Filete a la izquierda y **una etiqueta con palabras**, nunca sólo color:
 * DESIGN.md deja tres estados por debajo de 4.5:1 y aquí hay que distinguir
 * «esto lo generé yo» de «esto lo has cambiado tú».
 *
 * Va en negativo sobre el margen de la hoja para no empujar el texto: lo que se
 * imprime tiene que seguir empezando donde empieza en el PDF.
 */
.escritorio :deep(.documento-editor :not(section)[data-fuente]) {
    position: relative;
    border-left: 2px solid #dae1e3;
    padding-left: 0.14in;
    margin-left: -0.18in;
}

.escritorio :deep(.documento-editor :not(section)[data-fuente])::before {
    content: 'Generado desde el registro';
    display: block;
    font-size: 6pt;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #5d6c72;
    margin-bottom: 0.04in;
}

.escritorio :deep(.documento-editor :not(section)[data-fuente][data-editado]) {
    border-left-color: #bb7400;
}

.escritorio :deep(.documento-editor :not(section)[data-fuente][data-editado])::before {
    content: 'Editado a mano';
    color: #bb7400;
}

/* El cursor de hueco de ProseMirror, entre dos bloques que no admiten texto. */
.escritorio :deep(.ProseMirror-gapcursor::after) {
    border-top-color: #151e24;
}
</style>
