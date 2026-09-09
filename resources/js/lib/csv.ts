/**
 * La exportación de lo que hay en pantalla.
 *
 * Exporta **la página visible con sus columnas visibles**, no el conjunto
 * entero: lo contrario obligaría a otra consulta y a otra ruta, y sobre todo
 * dejaría de coincidir con lo que la persona está mirando. El botón lo dice, y
 * el nombre del fichero lleva la fecha para que dos exportaciones seguidas no
 * se pisen.
 *
 * Nada de esto es un documento del SGSI: la Declaración de Aplicabilidad y
 * cualquier otro entregable archivable se generan con Gotenberg y se almacenan
 * firmados. Esto es una hoja de trabajo.
 */

/** Comilla lo que lo necesita y neutraliza lo que Excel interpretaría como fórmula. */
function celda(valor: string): string {
    const seguro = /^[=+\-@\t\r]/.test(valor) ? `'${valor}` : valor;

    return /[";\n\r]/.test(seguro) ? `"${seguro.replace(/"/g, '""')}"` : seguro;
}

export function componerCsv(cabeceras: string[], filas: string[][]): string {
    return [cabeceras, ...filas].map((fila) => fila.map(celda).join(';')).join('\r\n');
}

export function descargarCsv(nombre: string, cabeceras: string[], filas: string[][]): void {
    // El punto de código inicial es lo único que hace que Excel abra el
    // fichero en UTF-8 en lugar de romper cada tilde.
    const contenido = new Blob([`﻿${componerCsv(cabeceras, filas)}`], {
        type: 'text/csv;charset=utf-8;',
    });

    const url = URL.createObjectURL(contenido);
    const enlace = document.createElement('a');

    enlace.href = url;
    enlace.download = `${nombre}-${new Date().toISOString().slice(0, 10)}.csv`;
    enlace.click();

    URL.revokeObjectURL(url);
}
