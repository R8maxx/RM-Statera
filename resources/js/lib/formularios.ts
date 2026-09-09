/**
 * Reka —como Radix— prohíbe el valor vacío en un `SelectItem`, así que un
 * desplegable opcional necesita una opción con valor propio para poder decir
 * «sin responsable» o «sin evaluar».
 *
 * Ese centinela es cosa de la interfaz y no llega a la validación: el trait
 * `NormalizaSeleccionVacia` lo traduce a nulo en el `FormRequest`. Las dos
 * constantes tienen que decir lo mismo, y sólo se escriben aquí y allí.
 */
export const SIN_VALOR = '__ninguno__';

export interface Opcion {
    valor: string;
    etiqueta: string;
}

/** Antepone la opción de «ninguno» a una lista de opciones. */
export function conOpcionVacia(opciones: Opcion[], etiqueta: string): Opcion[] {
    return [{ valor: SIN_VALOR, etiqueta }, ...opciones];
}
