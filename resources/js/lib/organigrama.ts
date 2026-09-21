import { hierarchy, tree } from 'd3-hierarchy';
import type { Edge, Node } from '@vue-flow/core';

/**
 * Coloca el árbol de puestos, y nada más.
 *
 * **Esto es todo lo que se le compra a d3**: `d3-hierarchy` es una función pura
 * sin DOM que recibe el árbol y devuelve una coordenada por nodo. El algoritmo
 * es el tidy-tree de Reingold–Tilford, que es exactamente lo que CLAUDE.md
 * llama «lo único que no compensa escribir a mano»: hacerlo uno mismo son unas
 * ciento cincuenta líneas y ramas que se solapan en cuanto el árbol se ensancha.
 *
 * Dibujar es de Vue Flow y **pintar es nuestro**: los nodos son componentes con
 * los tokens de `app.css`, así que el color y la forma siguen saliendo de un
 * solo sitio. Es el mismo reparto que ya se decidió al descartar Chart.js —«una
 * librería obliga a escribir los colores en JavaScript en vez de leerlos de los
 * tokens»—, sólo que aquí la parte que se delega es la geometría y no el estilo.
 */
export interface NodoOrganigrama {
    id: number;
    codigo: string;
    titulo: string;
    profundidad: number;
    reportaA: number | null;
    caracterizado: boolean;
    ocupantes: { id: number; nombre: string }[];
}

/**
 * El tamaño de la caja, que decide el de la retícula entera.
 *
 * Va aquí y no en el componente porque **la disposición depende de él**: d3
 * separa los hermanos por el ancho que se le diga, y si el componente pintara
 * cajas más anchas que las declaradas, se solaparían.
 */
export const CAJA = {
    ancho: 232,
    alto: 76,
    /** Con los ocupantes dentro la caja crece, y la retícula con ella. */
    altoConPersonas: 132,
} as const;

/** Cuántos ocupantes caben antes del «y N más». */
export const OCUPANTES_A_LA_VISTA = 3;

/**
 * Traduce el árbol del servidor a lo que Vue Flow espera.
 *
 * **La raíz sintética es el truco que hace falta aquí**: una organización puede
 * tener varios puestos sin superior —y los tiene, mientras el organigrama se
 * está montando—, pero `d3-hierarchy` sólo sabe colocar UN árbol. Se cuelgan
 * todas las raíces reales de una falsa, se coloca el conjunto y **la falsa se
 * descarta**, con lo que los bosques quedan repartidos sin solaparse y sin
 * inventar un nodo que nadie ha creado.
 */
export function disponer(
    nodos: NodoOrganigrama[],
    conPersonas: boolean,
): { nodes: Node[]; edges: Edge[] } {
    if (nodos.length === 0) {
        return { nodes: [], edges: [] };
    }

    const alto = conPersonas ? CAJA.altoConPersonas : CAJA.alto;
    const porId = new Map(nodos.map((nodo) => [nodo.id, nodo]));

    const raiz = hierarchy<NodoOrganigrama | null>(null, (padre) => {
        if (padre === null) {
            // Los hijos de la raíz falsa son los puestos que no reportan a nadie
            // —y también los que apuntan a un puesto que ya no existe, que es lo
            // que deja un borrado: verlos arriba es lo que permite arreglarlos.
            return nodos.filter(
                (nodo) => nodo.reportaA === null || !porId.has(nodo.reportaA),
            );
        }

        return nodos.filter((nodo) => nodo.reportaA === padre.id);
    });

    // Separación: uno de ancho entre hermanos, y uno y cuarto entre primos, que
    // es lo que deja ver dónde acaba una rama y empieza otra.
    const disposicion = tree<NodoOrganigrama | null>()
        .nodeSize([CAJA.ancho, alto + 56])
        .separation((a, b) => (a.parent === b.parent ? 1.12 : 1.4));

    const colocado = disposicion(raiz);
    const puntos = colocado.descendants().filter((punto) => punto.data !== null);

    // El origen se lleva al cero para que el lienzo no arranque con la mitad del
    // árbol en coordenadas negativas: `fitView` lo encuadra igual, pero el
    // minimapa y el zoom manual se leen mucho peor.
    const minX = Math.min(...puntos.map((punto) => punto.x));
    const minY = Math.min(...puntos.map((punto) => punto.y));

    const nodes: Node[] = puntos.map((punto) => {
        const dato = punto.data as NodoOrganigrama;

        return {
            id: String(dato.id),
            type: 'puesto',
            position: { x: punto.x - minX, y: punto.y - minY },
            // El tamaño se declara para que el minimapa y el `fitView` no tengan
            // que medir el DOM: sin esto, el primer encuadre sale con las cajas
            // a cero y hay que volver a encuadrar tras pintar.
            width: CAJA.ancho,
            height: alto,
            data: { puesto: dato, conPersonas },
            draggable: false,
            connectable: false,
            selectable: false,
        };
    });

    const edges: Edge[] = puntos
        // Los hijos de la raíz falsa no tienen arista: su padre no existe.
        .filter((punto) => punto.parent !== null && punto.parent.data !== null)
        .map((punto) => {
            const hijo = punto.data as NodoOrganigrama;
            const padre = punto.parent?.data as NodoOrganigrama;

            return {
                id: `${padre.id}-${hijo.id}`,
                source: String(padre.id),
                target: String(hijo.id),
                type: 'smoothstep',
                // Sin flecha: la jerarquía ya la dice la posición, y veinte
                // puntas de flecha en un organigrama son ruido.
                animated: false,
            };
        });

    return { nodes, edges };
}
