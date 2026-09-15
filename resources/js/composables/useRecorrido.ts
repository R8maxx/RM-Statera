import { CLAVE_RECORRIDO_VISTO, recorridoPanel, type PasoRecorrido } from '@/lib/recorridos';
import { useStorage } from '@vueuse/core';
import { computed, ref, type ComputedRef, type Ref } from 'vue';

/**
 * El estado del recorrido guiado.
 *
 * Vive fuera de los componentes por lo mismo que `usePaletaComandos`: tiene dos
 * puntos de entrada que no se conocen entre sí —el arranque automático la
 * primera vez que alguien abre el panel y el «Recorrido guiado» del menú de
 * usuario— y el overlay que lo pinta cuelga del layout, que no es hijo de
 * ninguno de los dos.
 *
 * **Que ya se vio se recuerda en el navegador**, no en la base de datos. Es el
 * mismo criterio que la vista de cada tabla y que el sidebar plegado: una
 * preferencia de un puesto, no un dato de la organización, así que no viaja al
 * servidor ni cruza la frontera del tenant. El precio está medido y es
 * aceptable: quien entre desde otro ordenador lo verá otra vez, y el recorrido
 * se puede cerrar en un gesto.
 */
const abierto = ref(false);
const indice = ref(0);
const visto = useStorage(CLAVE_RECORRIDO_VISTO, false);

export function useRecorrido(): {
    abierto: Ref<boolean>;
    indice: Ref<number>;
    visto: Ref<boolean>;
    paso: ComputedRef<PasoRecorrido | undefined>;
    pasos: PasoRecorrido[];
    total: number;
    esUltimo: ComputedRef<boolean>;
    abrir: () => void;
    cerrar: () => void;
    avanzar: () => void;
    retroceder: () => void;
    irA: (destino: number) => void;
    /** Arranca sólo si nadie lo ha visto todavía en este navegador. */
    arrancarSiEsLaPrimeraVez: () => void;
} {
    const paso = computed(() => recorridoPanel[indice.value]);
    const esUltimo = computed(() => indice.value === recorridoPanel.length - 1);

    const abrir = (): void => {
        indice.value = 0;
        abierto.value = true;
    };

    /*
     * Cerrar marca el recorrido como visto, se haya terminado o no. Volver a
     * lanzarlo a quien lo descartó en el segundo paso es exactamente el patrón
     * que hace que la gente aprenda a cerrar cosas sin leerlas.
     */
    const cerrar = (): void => {
        abierto.value = false;
        visto.value = true;
    };

    const avanzar = (): void => {
        if (esUltimo.value) {
            cerrar();

            return;
        }

        indice.value += 1;
    };

    const retroceder = (): void => {
        indice.value = Math.max(indice.value - 1, 0);
    };

    const irA = (destino: number): void => {
        indice.value = Math.min(Math.max(destino, 0), recorridoPanel.length - 1);
    };

    return {
        abierto,
        indice,
        visto,
        paso,
        pasos: recorridoPanel,
        total: recorridoPanel.length,
        esUltimo,
        abrir,
        cerrar,
        avanzar,
        retroceder,
        irA,
        arrancarSiEsLaPrimeraVez: () => {
            if (!visto.value) {
                abrir();
            }
        },
    };
}
