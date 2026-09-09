import type { ValorFiltro } from '@/composables/useTablaServidor';

type Filtro = App.Http.Resources.Definicion.Filtro;

/**
 * La lectura de un valor de filtro, en un solo sitio.
 *
 * Un mismo filtro se pinta hasta en tres sitios —la fila de la cabecera, el
 * panel «Filtros» y el chip de lo aplicado— y los tres tienen que interpretar
 * igual lo que viene del servidor. Cuando cada uno lo hacía por su cuenta, el
 * chip decía una cosa y el control mostraba otra.
 */

/** Los valores marcados de un filtro de opciones. */
export function seleccionados(valor: ValorFiltro): string[] {
    if (Array.isArray(valor)) {
        return valor;
    }

    return typeof valor === 'string' && valor !== '' ? valor.split(',') : [];
}

/** Los dos extremos de un rango de fechas; cualquiera puede venir vacío. */
export function rango(valor: ValorFiltro): [string, string] {
    const partes = String(valor ?? '').split(',');

    return [partes[0] ?? '', partes[1] ?? ''];
}

export function estaActivo(valor: ValorFiltro): boolean {
    if (valor === null || valor === undefined || valor === '') {
        return false;
    }

    return Array.isArray(valor) ? valor.length > 0 : true;
}

const formatoFechaCorta = new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'short' });

function fechaCorta(valor: string): string {
    const instante = new Date(valor);

    return Number.isNaN(instante.getTime()) ? valor : formatoFechaCorta.format(instante);
}

/** Lo que enseña el control cuando está cerrado. */
export function resumenFiltro(filtro: Filtro, valor: ValorFiltro): string | null {
    if (filtro.tipo === 'booleano') {
        if (!estaActivo(valor)) {
            return null;
        }

        return valor === '0' ? 'No' : 'Sí';
    }

    if (filtro.tipo === 'rango_fechas') {
        const [desde, hasta] = rango(valor);

        if (desde === '' && hasta === '') {
            return null;
        }

        return `${desde === '' ? '…' : fechaCorta(desde)} – ${hasta === '' ? '…' : fechaCorta(hasta)}`;
    }

    const marcados = seleccionados(valor);

    if (marcados.length === 0) {
        return null;
    }

    if (marcados.length === 1) {
        return filtro.opciones.find((opcion) => opcion.valor === marcados[0])?.etiqueta ?? marcados[0];
    }

    return `${marcados.length} seleccionados`;
}

/** Lo aplicado de verdad, pieza a pieza, y qué queda al quitar cada pieza. */
export interface Chip {
    clave: string;
    etiqueta: string;
    /** Qué queda al quitar este chip: `null` borra el filtro entero. */
    resto: ValorFiltro;
}

export function chipsDe(filtros: Filtro[], valores: Record<string, ValorFiltro>): Chip[] {
    return filtros.flatMap((filtro): Chip[] => {
        const valor = valores[filtro.clave] ?? null;

        if (!estaActivo(valor)) {
            return [];
        }

        if (filtro.tipo === 'busqueda' || filtro.tipo === 'texto') {
            return [{ clave: filtro.clave, etiqueta: `${filtro.etiqueta}: ${String(valor)}`, resto: null }];
        }

        if (filtro.tipo === 'booleano' || filtro.tipo === 'rango_fechas') {
            const resumen = resumenFiltro(filtro, valor);

            return resumen === null ? [] : [{ clave: filtro.clave, etiqueta: `${filtro.etiqueta}: ${resumen}`, resto: null }];
        }

        const marcados = seleccionados(valor);

        return marcados.map((opcion) => {
            const resto = marcados.filter((otro) => otro !== opcion);

            return {
                clave: filtro.clave,
                etiqueta: `${filtro.etiqueta}: ${filtro.opciones.find((o) => o.valor === opcion)?.etiqueta ?? opcion}`,
                resto: resto.length === 0 ? null : resto,
            };
        });
    });
}

/**
 * ── Resaltado de coincidencias ──────────────────────────────────────────────
 *
 * Qué términos se resaltan en cada columna. Salen de dos sitios: el filtro de
 * texto de la propia columna, y la búsqueda general, que declara en
 * `resaltaEn` a qué columnas corresponden los campos que cruza —eso el cliente
 * no puede deducirlo, porque `requisitos.titulo` es nombre de servidor—.
 */
export function terminosPorColumna(
    filtros: Filtro[],
    valores: Record<string, ValorFiltro>,
): Record<string, string[]> {
    const salida: Record<string, string[]> = {};

    const anotar = (columna: string, termino: string): void => {
        salida[columna] = [...(salida[columna] ?? []), termino];
    };

    for (const filtro of filtros) {
        const valor = valores[filtro.clave];

        // Con una sola letra coincide media tabla y el resaltado deja de informar.
        if (typeof valor !== 'string' || valor.trim().length < 2) {
            continue;
        }

        if (filtro.tipo === 'busqueda') {
            filtro.resaltaEn.forEach((columna) => anotar(columna, valor));

            continue;
        }

        if (filtro.tipo === 'texto' && filtro.columna !== null) {
            anotar(filtro.columna, valor);
        }
    }

    return salida;
}

export interface Fragmento {
    texto: string;
    coincide: boolean;
}

/** Lo que en una expresión regular significaría otra cosa. */
function escapar(termino: string): string {
    return termino.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Trocea un texto en fragmentos que coinciden y fragmentos que no.
 *
 * Devuelve trozos, no marcado: el texto es dato de otra organización y no
 * puede volver al DOM como HTML. Quien lo pinta usa `v-for`, nunca `v-html`.
 */
export function partirPorTerminos(texto: string, terminos: string[]): Fragmento[] {
    const utiles = [...new Set(terminos.map((termino) => termino.trim()).filter((termino) => termino.length >= 2))];

    if (utiles.length === 0 || texto === '') {
        return [{ texto, coincide: false }];
    }

    const patron = new RegExp(utiles.map(escapar).join('|'), 'gi');
    const fragmentos: Fragmento[] = [];
    let ultimo = 0;

    for (const coincidencia of texto.matchAll(patron)) {
        const indice = coincidencia.index ?? 0;

        if (indice > ultimo) {
            fragmentos.push({ texto: texto.slice(ultimo, indice), coincide: false });
        }

        fragmentos.push({ texto: coincidencia[0], coincide: true });
        ultimo = indice + coincidencia[0].length;
    }

    if (ultimo < texto.length) {
        fragmentos.push({ texto: texto.slice(ultimo), coincide: false });
    }

    return fragmentos;
}
