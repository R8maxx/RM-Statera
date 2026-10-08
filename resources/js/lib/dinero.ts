/**
 * Importes, que llegan del servidor en céntimos enteros (punto 51).
 *
 * Un solo formateador para todo el producto: el precio de una tarjeta, el
 * prorrateo del resumen y el importe del histórico tienen que escribirse igual.
 * Sin decimales cuando el importe es redondo —«49 €», no «49,00 €»—, que es
 * como se lee un precio; con dos cuando no lo es.
 */
const conDecimales = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2 });
const sinDecimales = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });

export function euros(centimos: number): string {
    return (centimos % 100 === 0 ? sinDecimales : conDecimales).format(centimos / 100);
}

/** El importe en euros como número, para `Cifra`, que cuenta hasta él. */
export function aEuros(centimos: number): number {
    return centimos / 100;
}
