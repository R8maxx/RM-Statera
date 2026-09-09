import { ref, type Ref } from 'vue';

/**
 * El estado de apertura de la paleta de comandos.
 *
 * Vive fuera de los componentes porque tiene dos puntos de entrada que no se
 * conocen entre sí: el atajo de teclado, que escucha la propia paleta, y el
 * botón «Buscar» de la cabecera. Compartir un `ref` de módulo es lo que evita
 * que uno de los dos tenga que simular el otro.
 */
const abierta = ref(false);

export function usePaletaComandos(): { abierta: Ref<boolean>; abrir: () => void } {
    return {
        abierta,
        abrir: () => (abierta.value = true),
    };
}
