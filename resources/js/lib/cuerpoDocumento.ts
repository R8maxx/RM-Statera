/**
 * El esquema del cuerpo de un documento, en el editor.
 *
 * Es `app/Domain/Documento/Cuerpo/EsquemaCuerpo.php` escrito otra vez en el
 * camino de escritura, y eso es deliberado: **en ProseMirror el esquema ES la
 * lista blanca**. Lo que no está declarado aquí no se puede crear, ni
 * escribiendo, ni pegando, ni arrastrando. El servidor vuelve a validar contra
 * el suyo —nunca se confía en el cliente—, pero tenerlo también aquí es lo que
 * hace que pegar media página de un navegador no meta un `<script>` en el
 * documento ni siquiera durante un instante.
 *
 * Las etiquetas y las clases son las mismas que emite `RenderizadorCuerpo`, así
 * que **lo que se ve al editar es lo que va a salir impreso**, con la misma hoja
 * de estilos por debajo. Sin esa correspondencia, «editar el documento» sería
 * editar otra cosa y comprobar el resultado en un PDF.
 *
 * Si alguien añade un nodo al esquema de PHP y no lo añade aquí, el editor lo
 * borra al cargar. Hay un test que compara las dos listas.
 */
import Link from '@tiptap/extension-link';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import { Mark, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

/**
 * Los tres atributos que dicen de dónde salió un bloque.
 *
 * Viajan en todos los bloques y **no se tocan al editar**: el editor los lee
 * para pintar el distintivo y los devuelve tal cual. Quien decide que algo pasa
 * a estar «editado» es el servidor, comparando con su línea base, no el
 * navegador diciéndolo de sí mismo.
 */
const procedencia = {
    fuente: { default: null, parseHTML: (e: HTMLElement) => e.getAttribute('data-fuente'), renderHTML: (a: Record<string, unknown>) => (a.fuente ? { 'data-fuente': a.fuente } : {}) },
    huella: { default: null, parseHTML: (e: HTMLElement) => e.getAttribute('data-huella'), renderHTML: (a: Record<string, unknown>) => (a.huella ? { 'data-huella': a.huella } : {}) },
    editado: { default: false, parseHTML: (e: HTMLElement) => e.hasAttribute('data-editado'), renderHTML: (a: Record<string, unknown>) => (a.editado ? { 'data-editado': '' } : {}) },
};

/**
 * Un atributo enumerado que se pinta con las clases del documento.
 *
 * El nodo guarda **la clave** —`pequeno_suave`, `fija`— y aquí se traduce a las
 * clases que espera `documento.css`, exactamente el mismo mapa que usa
 * `RenderizadorCuerpo` en el servidor. Sin esta traducción el editor guardaba la
 * clave y no pintaba nada: con la hoja del documento puesta, una tabla sin su
 * `tabla--fija` pierde el `table-layout: fixed` y reparte las diez columnas a
 * ojo, que es justo lo que el PDF no hace.
 *
 * `data-<nombre>` viaja además de la clase para poder leer la clave de vuelta:
 * de la clase no se deduce, porque varias claves comparten clases.
 *
 * Las clases van **escritas enteras en el mapa y nunca compuestas en
 * ejecución**, igual que en `CeldaBadge` y en `EsquemaCuerpo`.
 */
function claseEnumerada(nombre: string, mapa: Record<string, string>) {
    return atributoEnumerado(nombre, mapa, 'class');
}

/** Lo mismo, pero lo que se emite es el `style` en línea de `EsquemaCuerpo::ESTILOS`. */
function estiloEnumerado(nombre: string, mapa: Record<string, string>) {
    return atributoEnumerado(nombre, mapa, 'style');
}

function atributoEnumerado(nombre: string, mapa: Record<string, string>, destino: 'class' | 'style') {
    const dato = `data-${nombre}`;

    return {
        default: null,
        parseHTML: (e: HTMLElement) => {
            const valor = e.getAttribute(dato);

            return valor !== null && valor in mapa ? valor : null;
        },
        renderHTML: (a: Record<string, unknown>) => {
            const valor = a[nombre];

            if (typeof valor !== 'string' || !(valor in mapa)) {
                return {};
            }

            return { [dato]: valor, [destino]: mapa[valor] };
        },
    };
}

/**
 * Los tipos de nodo del esquema, escritos una vez para poder compararlos.
 *
 * `tests/Unit/Documento/EsquemaEnDosIdiomasTest.php` comprueba que esta lista es
 * exactamente la de `EsquemaCuerpo::NODOS`. Un nodo que exista en PHP y no aquí
 * lo borraría el editor al cargar el documento —en silencio, que es lo peor—; y
 * uno que exista aquí y no en PHP lo borraría el servidor al guardar.
 */
export const NODOS = [
    'doc', 'portada', 'seccion', 'caja', 'ficha', 'cifras', 'grafica', 'leyenda',
    'limitaciones', 'nota', 'grupo', 'fileteMarca', 'marcaPortada', 'pieDePortada',
    'paragraph', 'heading', 'bulletList', 'orderedList', 'listItem', 'text', 'hardBreak',
    'fichaFila', 'cifraDato', 'badge',
    'table', 'tableRow', 'tableHeader', 'tableCell',
] as const;

export const MARCAS = ['bold', 'italic', 'link', 'cifra', 'suave'] as const;

/**
 * Las enumeraciones, **clave → lo que se pinta**.
 *
 * Son las mismas de `EsquemaCuerpo`, con los mismos valores, y
 * `EsquemaEnDosIdiomasTest` compara claves **y** valores. Antes aquí sólo había
 * la lista de claves y las clases se escribían en una hoja de estilos propia del
 * editor; ahora el editor usa `documento.css` tal cual, así que la clase tiene
 * que ser la del documento o no se pinta nada.
 */
export const CLASES_PARRAFO: Record<string, string> = {
    suave: 'suave',
    pequeno: 'pequeno',
    pequeno_suave: 'pequeno suave',
    vacio: 'vacio',
    subtitulo_portada: 'portada__subtitulo',
};

export const CLASES_ENCABEZADO: Record<string, string> = {
    titulo_portada: 'portada__titulo',
};

export const ESTILOS: Record<string, string> = {
    aviso_borrador: 'border-left: 3px solid var(--estado-en-progreso); background: var(--estado-en-progreso-suave)',
    formula: 'font-size: 10pt; margin-bottom: 0',
    separado: 'margin-top: 0.2in',
    nota_al_pie: 'margin-top: 0.12in',
};

export const TONOS_BADGE: Record<string, string> = {
    implantado: 'badge badge--implantado',
    planificado: 'badge badge--planificado',
    en_progreso: 'badge badge--en_progreso',
    no_iniciado: 'badge badge--no_iniciado',
    no_aplica: 'badge badge--no_aplica',
    neutro: 'badge badge--neutro',
};

export const VARIANTES_CAJA: Record<string, string> = {
    simple: 'caja',
    marca: 'caja caja--marca',
};

/** Los niveles de encabezado que el editor ofrece, como `EsquemaCuerpo::NIVELES`. */
const NIVELES: number[] = [1, 2, 3, 4];

export const CLASES_TABLA: Record<string, string> = {
    fija: 'tabla--fija',
};

export const CLASES_FILA: Record<string, string> = {
    grupo: 'grupo',
};

export const CLASES_CELDA: Record<string, string> = {
    codigo: 'codigo',
    huella: 'huella',
};

/**
 * Un bloque contenedor: una etiqueta, unas clases fijas y sus hijos.
 *
 * Las clases van escritas enteras y nunca compuestas en ejecución, igual que en
 * `CeldaBadge` y en `EsquemaCuerpo`.
 */
function contenedor(nombre: string, etiqueta: string, clases: string | null, contenido: string) {
    return Node.create({
        name: nombre,
        group: 'block',
        content: contenido,
        defining: true,
        addAttributes: () => ({ ...procedencia }),
        parseHTML: () => [{ tag: clases === null ? etiqueta : `${etiqueta}[class="${clases}"]` }],
        renderHTML: ({ HTMLAttributes }) => [
            etiqueta,
            mergeAttributes(HTMLAttributes, clases === null ? {} : { class: clases }),
            0,
        ],
    });
}

/** Un bloque con texto dentro y nada más. */
function bloqueDeTexto(nombre: string, etiqueta: string, clases: string) {
    return Node.create({
        name: nombre,
        group: 'block',
        content: 'inline*',
        addAttributes: () => ({ ...procedencia }),
        parseHTML: () => [{ tag: `${etiqueta}[class="${clases}"]` }],
        renderHTML: ({ HTMLAttributes }) => [etiqueta, mergeAttributes(HTMLAttributes, { class: clases }), 0],
    });
}

export const Portada = contenedor('portada', 'section', 'portada', 'block+');
export const Seccion = contenedor('seccion', 'section', 'seccion', 'block+');

/**
 * Un contenedor que no pinta nada de sí mismo.
 *
 * En el documento impreso desaparece; aquí necesita un elemento donde colgar el
 * distintivo de procedencia, así que se pinta como un `<div>` que el CSS del
 * editor marca y la hoja del documento ignora.
 */
export const Grupo = contenedor('grupo', 'div', 'grupo-calculado', 'block+');

export const Ficha = contenedor('ficha', 'div', 'ficha', 'fichaFila+');
export const Cifras = contenedor('cifras', 'div', 'cifras', 'cifraDato+');
export const Leyenda = contenedor('leyenda', 'div', 'leyenda', 'badge*');
export const Limitaciones = contenedor('limitaciones', 'div', 'limitaciones', 'block+');
/**
 * La nota al pie de una celda: la dimensión que modula una medida del Anexo II.
 *
 * Es un nodo **en línea** aunque se pinte como `<div>`, y eso no es un descuido:
 * las celdas de este documento contienen texto, no párrafos —`documento.css` da
 * `margin: 0 0 0.6em` a todo `<p>`, y meter uno en cada una de las novecientas
 * treinta celdas de la tabla larga la estiraría media página—. El `<div>` es lo
 * que hace que la nota caiga a su propia línea sin necesitar una regla nueva.
 */
export const Nota = Node.create({
    name: 'nota',
    inline: true,
    group: 'inline',
    content: 'text*',
    parseHTML: () => [{ tag: 'div.pequeno.suave' }],
    renderHTML: () => ['div', { class: 'pequeno suave' }, 0],
});
export const MarcaPortada = bloqueDeTexto('marcaPortada', 'div', 'portada__marca');
export const PieDePortada = bloqueDeTexto('pieDePortada', 'div', 'portada__pie pequeno suave');

export const Caja = Node.create({
    name: 'caja',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes: () => ({
        ...procedencia,
        variante: { default: 'simple' },
        estilo: estiloEnumerado('estilo', ESTILOS),
    }),
    parseHTML: () => [{ tag: 'div.caja' }],
    renderHTML: ({ HTMLAttributes, node }) => [
        'div',
        mergeAttributes(HTMLAttributes, {
            class: VARIANTES_CAJA[node.attrs.variante] ?? VARIANTES_CAJA.simple,
        }),
        0,
    ],
});

/** El filete violeta de la portada. La única aparición del violeta (DESIGN.md §3). */
export const FileteMarca = Node.create({
    name: 'fileteMarca',
    group: 'block',
    atom: true,
    selectable: false,
    parseHTML: () => [{ tag: 'div.portada__filete' }],
    renderHTML: () => ['div', { class: 'portada__filete' }],
});

/**
 * Una fila de la ficha: clave fija y valor editable.
 *
 * En el documento **no hay fila**: `.ficha` es una rejilla de dos columnas
 * (`grid-template-columns: 1.5in 1fr`) y sus hijos son `.ficha__clave` y
 * `.ficha__valor` sueltos, alternándose. Aquí hace falta un elemento donde
 * colgar el nodo de ProseMirror —un nodo, un elemento—, así que se envuelve en
 * un `div.ficha-fila` al que la capa del editor le da `display: contents`: la
 * rejilla ve los nietos y la ficha sale en dos columnas, como impresa.
 *
 * Sin ese `display: contents`, la rejilla ve un solo hijo por fila y la portada
 * entera colapsa a una columna. Es el desajuste que hace de prueba de que la
 * hoja del documento está puesta de verdad.
 */
export const FichaFila = Node.create({
    name: 'fichaFila',
    content: 'inline*',
    addAttributes: () => ({ ...procedencia, clave: { default: '' } }),
    parseHTML: () => [{ tag: 'div.ficha__valor' }],
    renderHTML: ({ HTMLAttributes, node }) => [
        'div',
        { class: 'ficha-fila' },
        ['div', { class: 'ficha__clave' }, node.attrs.clave],
        ['div', mergeAttributes(HTMLAttributes, { class: 'ficha__valor' }), 0],
    ],
});

/**
 * Una cifra del resumen. Es un átomo: el valor sale del registro y editarlo a
 * mano sería escribir un número que no cuadra con su propia tabla.
 */
export const CifraDato = Node.create({
    name: 'cifraDato',
    atom: true,
    addAttributes: () => ({ ...procedencia, valor: { default: '—' }, de: { default: null }, etiqueta: { default: '' } }),
    parseHTML: () => [{ tag: 'div.cifras__dato' }],
    renderHTML: ({ node }) => [
        'div',
        { class: 'cifras__dato' },
        [
            'div',
            { class: 'cifras__valor' },
            String(node.attrs.valor),
            ...(node.attrs.de ? [['span', { class: 'cifras__de' }, ` ${node.attrs.de}`] as const] : []),
        ],
        ['div', { class: 'cifras__etiqueta' }, String(node.attrs.etiqueta)],
    ],
});

/**
 * La gráfica guarda **el reparto**, no el dibujo.
 *
 * El SVG lo pinta `GraficaSvg` en el servidor al generar, así que aquí no hay
 * marcado vectorial que editar ni que confiar: el editor enseña la leyenda, que
 * es lo que dice lo mismo en texto.
 */
export const Grafica = Node.create({
    name: 'grafica',
    group: 'block',
    content: 'leyenda?',
    addAttributes: () => ({ ...procedencia, segmentos: { default: [] } }),
    parseHTML: () => [{ tag: 'div.grafica' }],
    renderHTML: ({ HTMLAttributes }) => ['div', mergeAttributes(HTMLAttributes, { class: 'grafica' }), 0],
});

export const Badge = Node.create({
    name: 'badge',
    inline: true,
    group: 'inline',
    content: 'text*',
    addAttributes: () => ({ tono: { default: 'neutro' } }),
    parseHTML: () => [{ tag: 'span.badge' }],
    renderHTML: ({ node }) => ['span', { class: TONOS_BADGE[node.attrs.tono] ?? TONOS_BADGE.neutro }, 0],
});

/** La monoespaciada tabular del documento: códigos, huellas y cifras sueltas. */
export const Cifra = Mark.create({
    name: 'cifra',
    parseHTML: () => [{ tag: 'span.cifra' }],
    renderHTML: () => ['span', { class: 'cifra' }, 0],
});

export const Suave = Mark.create({
    name: 'suave',
    parseHTML: () => [{ tag: 'span.suave' }],
    renderHTML: () => ['span', { class: 'suave' }, 0],
});

/**
 * El juego completo.
 *
 * `resizable: false` en las tablas a propósito: diez columnas con tiradores de
 * redimensionado en noventa y tres filas no ayudan a nadie, y las anchuras están
 * medidas para que quepan en un A4 apaisado.
 */
export function extensionesCuerpo() {
    return [
        StarterKit.configure({
            /*
             * `doc`, `paragraph` y `heading` se redeclaran más abajo con sus
             * atributos —la clase enumerada, el estilo y la procedencia—, así
             * que los de serie se apagan. Dejarlos encendidos no da un error:
             * TipTap avisa de «Duplicate extension names» por consola y se queda
             * con uno de los dos, que es como se pierde un atributo sin que se
             * note hasta que alguien abre el PDF.
             */
            document: false,
            paragraph: false,
            heading: false,
            codeBlock: false,
            blockquote: false,
            horizontalRule: false,
            code: false,
            strike: false,
            link: false,

            /*
             * `underline` no está en `EsquemaCuerpo::MARCAS`, así que el
             * servidor lo poda al guardar. Dejarlo encendido sólo sirve para que
             * Cmd+U subraye en pantalla algo que no va a salir impreso.
             */
            underline: false,

            /*
             * **`trailingNode` hay que apagarlo, y no es una preferencia.**
             *
             * Añade un nodo vacío al final del documento para que siempre se
             * pueda escribir debajo del último bloque. Con un `doc` corriente eso
             * es un párrafo y no molesta a nadie; aquí el `doc` es
             * `portada seccion+` y lo único que cabe al final es una SECCIÓN.
             *
             * Resultado: **cada vez que se abría el editor, el documento ganaba
             * una sección vacía**, y al guardar se quedaba. Cuatro visitas,
             * cuatro páginas en blanco en el PDF del auditor sin que nadie
             * hubiera escrito nada, y sin nada en la interfaz que lo explicara.
             *
             * Para añadir una sección está el menú «Insertar», que pone la suya
             * con su título.
             */
            trailingNode: false,
        }),
        Documento,
        Parrafo,
        Encabezado,
        Link.configure({
            openOnClick: false,
            autolink: false,
            protocols: ['http', 'https', 'mailto'],
            HTMLAttributes: { rel: 'noopener noreferrer' },
        }),
        /*
         * `clase` va declarada aquí y no sólo en PHP: sin ella, el editor se
         * comía el `tabla--fija` de la tabla larga al cargarla, y con él se iba
         * el `table-layout: fixed` que hace que las anchuras de columna se
         * respeten. La tabla seguía saliendo —por eso no rompía nada— pero con
         * las diez columnas repartidas a ojo por Chromium.
         */
        Table.configure({ resizable: false, allowTableNodeSelection: true }).extend({
            addAttributes() {
                return { ...this.parent?.(), clase: claseEnumerada('clase', CLASES_TABLA) };
            },
        }),
        TableRow.extend({ addAttributes: () => ({ clase: claseEnumerada('clase', CLASES_FILA) }) }),
        /*
         * Celdas de contenido EN LÍNEA, no de bloque.
         *
         * Es la diferencia con las tablas de serie de TipTap, y viene del
         * documento: un `<td>` de esta SoA lleva texto suelto, nunca un `<p>`.
         * Con el contenido de bloque por defecto, el cuerpo que produce el
         * servidor no valida contra este esquema y **el editor se come la tabla
         * entera al abrirla**.
         */
        TableHeader.extend({
            content: 'inline*',
            addAttributes() {
                return { ...this.parent?.(), ancho: anchoDeColumna(), scope: { default: null } };
            },
        }),
        TableCell.extend({
            content: 'inline*',
            addAttributes() {
                return { ...this.parent?.(), clase: claseEnumerada('clase', CLASES_CELDA) };
            },
        }),
        Portada,
        Seccion,
        Grupo,
        Caja,
        Ficha,
        FichaFila,
        Cifras,
        CifraDato,
        Grafica,
        Leyenda,
        Limitaciones,
        Nota,
        FileteMarca,
        MarcaPortada,
        PieDePortada,
        Badge,
        Cifra,
        Suave,
    ];
}

/**
 * La raíz.
 *
 * Se redeclara porque el documento no es «párrafos sueltos»: es una portada y
 * luego secciones, y con el `doc` de serie se podría escribir texto fuera de
 * toda sección, que en el PDF saldría entre dos páginas sin pertenecer a ninguna.
 */
const Documento = Node.create({
    name: 'doc',
    topNode: true,
    content: 'portada seccion+',
});

const Parrafo = Node.create({
    name: 'paragraph',
    priority: 1000,
    group: 'block',
    content: 'inline*',
    addAttributes: () => ({
        ...procedencia,
        clase: claseEnumerada('clase', CLASES_PARRAFO),
        estilo: estiloEnumerado('estilo', ESTILOS),
    }),
    parseHTML: () => [{ tag: 'p' }],
    renderHTML: ({ HTMLAttributes }) => ['p', HTMLAttributes, 0],
});

const Encabezado = Node.create({
    name: 'heading',
    priority: 1000,
    group: 'block',
    content: 'inline*',
    defining: true,
    addAttributes: () => ({
        ...procedencia,
        level: { default: 2 },
        clase: claseEnumerada('clase', CLASES_ENCABEZADO),
        estilo: estiloEnumerado('estilo', ESTILOS),
    }),
    parseHTML: () => [1, 2, 3, 4].map((level) => ({ tag: `h${level}`, attrs: { level } })),
    renderHTML: ({ HTMLAttributes, node }) => [
        `h${NIVELES.includes(node.attrs.level) ? node.attrs.level : 2}`,
        HTMLAttributes,
        0,
    ],
});

/**
 * El ancho de una columna de la tabla larga.
 *
 * `RenderizadorCuerpo` lo emite como `style="width: 1.1in"`, y el editor tiene
 * que hacer lo mismo: las diez columnas de la SoA están medidas en pulgadas para
 * que quepan en un A4 apaisado, y sin ellas Chromium las reparte a su gusto — en
 * el PDF no, pero en el editor sí, que es donde se decide si caben.
 *
 * La expresión es la misma de `EsquemaCuerpo::ANCHO`: un `width` libre es un
 * atributo de estilo por la puerta de atrás.
 */
const ANCHO = /^\d{1,3}(\.\d{1,3})?(in|pt|mm|em|%)$/;

function anchoDeColumna() {
    return {
        default: null,
        parseHTML: (e: HTMLElement) => {
            const valor = e.getAttribute('data-ancho');

            return valor !== null && ANCHO.test(valor) ? valor : null;
        },
        renderHTML: (a: Record<string, unknown>) => {
            const valor = a.ancho;

            if (typeof valor !== 'string' || !ANCHO.test(valor)) {
                return {};
            }

            return { 'data-ancho': valor, style: `width: ${valor}` };
        },
    };
}
