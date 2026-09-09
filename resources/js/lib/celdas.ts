type Columna = App.Http.Resources.Definicion.Columna;
type ValorEnlace = App.Http.Resources.Definicion.ValorEnlace;
type ValorEscala = App.Http.Resources.Definicion.ValorEscala;
type ValorEtiquetado = App.Http.Resources.Definicion.ValorEtiquetado;
type ValorProgreso = App.Http.Resources.Definicion.ValorProgreso;

/**
 * El formato de una celda, en un solo sitio.
 *
 * `CeldaValor` pinta y esto escribe: el título de un texto recortado, la
 * exportación a CSV y cualquier lectura en texto plano salen de aquí. Tenerlo
 * dos veces terminaba con una fecha en la tabla y otra distinta en el fichero
 * exportado.
 */
export const formatoFecha = new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium' });
export const formatoFechaHora = new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' });
export const formatoNumero = new Intl.NumberFormat('es-ES');

export function fechaDe(valor: unknown): Date | null {
    if (typeof valor !== 'string') {
        return null;
    }

    const instante = new Date(valor);

    return Number.isNaN(instante.getTime()) ? null : instante;
}

export function esVacio(valor: unknown): boolean {
    return valor === null || valor === undefined || valor === '';
}

export function enlaceDe(valor: unknown): ValorEnlace | null {
    return typeof valor === 'object' && valor !== null && 'url' in valor ? (valor as ValorEnlace) : null;
}

export function progresoDe(valor: unknown): ValorProgreso | null {
    if (typeof valor === 'object' && valor !== null && 'porcentaje' in valor) {
        return valor as ValorProgreso;
    }

    return typeof valor === 'number' ? { porcentaje: valor, hechas: null, de: null } : null;
}

/** El valor de una celda en texto plano, con el mismo formato que se ve. */
export function textoDeCelda(columna: Columna, valor: unknown): string {
    if (columna.tipo === 'booleano') {
        return valor ? 'Sí' : 'No';
    }

    if (esVacio(valor)) {
        return '';
    }

    switch (columna.tipo) {
        case 'badge':
            return (valor as ValorEtiquetado).etiqueta ?? '';
        case 'escala':
            return (valor as ValorEscala).etiqueta ?? '';
        case 'enlace':
            return enlaceDe(valor)?.etiqueta ?? String(valor);
        case 'progreso': {
            const progreso = progresoDe(valor);

            return progreso === null ? String(valor) : `${progreso.porcentaje}%`;
        }
        case 'numero':
            return formatoNumero.format(Number(valor));
        case 'fecha': {
            const fecha = fechaDe(valor);

            return fecha ? formatoFecha.format(fecha) : String(valor);
        }
        case 'fecha_hora': {
            const fecha = fechaDe(valor);

            return fecha ? formatoFechaHora.format(fecha) : String(valor);
        }
        default:
            return String(valor);
    }
}
