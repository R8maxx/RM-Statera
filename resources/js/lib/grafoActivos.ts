import ELK from 'elkjs/lib/elk.bundled.js';

/**
 * Coloca el grafo de dependencias de un activo.
 *
 * **Y no lo hace `d3-hierarchy`, que es lo que coloca el organigrama.** La
 * diferencia no es de gusto: un organigrama es un ÁRBOL —cada puesto reporta a
 * uno— y esto es un **grafo dirigido acíclico**, porque `activo_dependencias` es
 * N:M. Dos servicios pueden apoyarse en la misma base de datos, y ese rombo es
 * justamente lo que hay que ver: es de donde le sube la valoración efectiva.
 * Un tidy-tree no sabe dibujar un rombo; tendría que romper uno de los dos
 * vínculos, que es perder el dato por el que existe la pantalla.
 *
 * ELK coloca por capas y **enruta las aristas para que se crucen lo menos
 * posible**, que es lo que hace legible un grafo con rombos.
 *
 * **Corre en el hilo principal y no en un web worker, y no por comodidad.** El
 * worker es el diseño de la librería y aquí no se puede tener: en desarrollo la
 * aplicación se sirve por el 8000 (nginx) y los assets por el 5173 (Vite), y
 * **un `Worker` de otro origen lo prohíbe el navegador** —«cannot be accessed
 * from origin»—, que es una regla de seguridad y no algo que CORS arregle. Con
 * `elk.bundled.js` no hay worker que construir.
 *
 * Lo que se paga es que la colocación bloquea el hilo, y aquí no se nota: esta
 * pantalla dibuja la VECINDAD de un activo —decenas de nodos como mucho—, no el
 * inventario entero. El día que haya un mapa completo, la opción es volver al
 * worker y servir los assets desde el mismo origen, no cambiar de librería.
 */
export interface NodoActivo {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
    tipoEtiqueta: string;
    tipoIcono: string | null;
    /** `arriba` lo que se cae con él, `abajo` lo que necesita, `centro` él. */
    sentido: 'arriba' | 'centro' | 'abajo';
    nivel: string | null;
    nivelEtiqueta: string | null;
    nivelTono: string;
}

export interface AristaActivo {
    desde: number;
    hacia: number;
    nota: string | null;
}

/**
 * Lo que sale de aquí, **descrito sin los tipos de Vue Flow**.
 *
 * No es purismo: `motion-v` y Vue Flow amplían los dos los `HTMLAttributes` de
 * Vue con un `DragControls` distinto, así que el `Node` que se resuelve en un
 * `.ts` no es idéntico al que se resuelve dentro de un `.vue` y `vue-tsc`
 * rechaza la asignación con un error de doscientas líneas sobre `domAttributes`.
 * Describiendo la forma propia, la compatibilidad es estructural y el fichero no
 * depende de la librería que lo pinta — que además es lo correcto: esto coloca,
 * no dibuja.
 */
export interface NodoColocado {
    id: string;
    type: string;
    position: { x: number; y: number };
    width: number;
    height: number;
    data: { activo: NodoActivo };
    draggable: boolean;
    connectable: boolean;
    selectable: boolean;
}

export interface AristaColocada {
    id: string;
    source: string;
    target: string;
    type: string;
}

/** El tamaño de la caja, del que depende la retícula que calcula ELK. */
export const CAJA = { ancho: 248, alto: 84 } as const;

/* Una instancia para todo el módulo: no hace falta una por navegación. */
const elk = new ELK();

/**
 * @returns Los nodos y aristas ya colocados, listos para Vue Flow.
 *
 * **La nota del vínculo NO se pinta sobre la arista**, y se intentó. Dos aristas
 * que convergen en el mismo nodo —el rombo, que es el caso que esta pantalla
 * existe para enseñar— dejan sus etiquetas a la misma altura y se leen **como
 * una sola frase**: «El servidor aloja la base de datos. La información del
 * gestor vive aquí.» parecía una nota y eran dos. Inventar una frase que nadie
 * escribió es peor que no enseñarla, y la nota ya vive en las listas de la ficha,
 * donde tiene sitio.
 */
export async function disponer(
    nodos: NodoActivo[],
    aristas: AristaActivo[],
): Promise<{ nodes: NodoColocado[]; edges: AristaColocada[] }> {
    if (nodos.length === 0) {
        return { nodes: [], edges: [] };
    }

    const colocado = await elk.layout({
        id: 'raiz',
        layoutOptions: {
            'elk.algorithm': 'layered',
            /*
             * `DOWN` no es arbitrario: las aristas van del activo a lo que
             * necesita, así que hacia abajo queda **lo que sostiene al de
             * arriba**. Es la misma lectura que los dos bloques de la ficha —«Lo
             * sostiene» encima, «Depende de» debajo— y la dirección en la que la
             * valoración efectiva se hereda.
             */
            'elk.direction': 'DOWN',
            'elk.layered.spacing.nodeNodeBetweenLayers': '72',
            'elk.spacing.nodeNode': '40',
            // Lo que se paga ELK: que las aristas de un rombo no se solapen.
            'elk.layered.crossingMinimization.strategy': 'LAYER_SWEEP',
            'elk.edgeRouting': 'ORTHOGONAL',
        },
        children: nodos.map((nodo) => ({
            id: String(nodo.id),
            width: CAJA.ancho,
            height: CAJA.alto,
        })),
        edges: aristas.map((arista, indice) => ({
            id: `a${indice}`,
            sources: [String(arista.desde)],
            targets: [String(arista.hacia)],
        })),
    });

    const posiciones = new Map(
        (colocado.children ?? []).map((hijo) => [hijo.id, { x: hijo.x ?? 0, y: hijo.y ?? 0 }]),
    );

    const nodes: NodoColocado[] = nodos.map((nodo) => ({
        id: String(nodo.id),
        type: 'activo',
        position: posiciones.get(String(nodo.id)) ?? { x: 0, y: 0 },
        width: CAJA.ancho,
        height: CAJA.alto,
        data: { activo: nodo },
        draggable: false,
        connectable: false,
        selectable: false,
    }));

    const edges: AristaColocada[] = aristas.map((arista, indice) => ({
        id: `a${indice}`,
        source: String(arista.desde),
        target: String(arista.hacia),
        type: 'smoothstep',
    }));

    return { nodes, edges };
}
