import {
    CLAVE_RECORRIDO_PANEL_ANTIGUA,
    CLAVE_RECORRIDOS_VISTOS,
    recorridos,
    type ClaveRecorrido,
    type PasoRecorrido,
} from '@/lib/recorridos';
import { router, usePage } from '@inertiajs/vue3';
import { useStorage } from '@vueuse/core';
import { computed, ref, type ComputedRef, type Ref } from 'vue';

/**
 * El estado de los recorridos guiados.
 *
 * Vive fuera de los componentes por lo mismo que `usePaletaComandos`: tiene
 * varios puntos de entrada que no se conocen entre sí —el arranque automático
 * la primera vez que alguien abre una pantalla, el «?» de su cabecera y el
 * «Recorrido guiado» del menú de usuario— y el overlay que lo pinta cuelga del
 * layout, que no es hijo de ninguno.
 *
 * **Hay uno abierto como mucho.** El general del panel y los de cada pantalla
 * comparten overlay, índice y estado; lo que cambia es `activo`.
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
const activo = ref<ClaveRecorrido>('panel');

/*
 * Antes sólo había el del panel y se guardaba como un booleano. Quien ya lo
 * vio no tiene que volver a encontrárselo por haber cambiado la forma de
 * guardarlo.
 */
const sembrarVistos = (): ClaveRecorrido[] => {
    try {
        return localStorage.getItem(CLAVE_RECORRIDO_PANEL_ANTIGUA) === 'true' ? ['panel'] : [];
    } catch {
        return [];
    }
};

const vistos = useStorage<ClaveRecorrido[]>(CLAVE_RECORRIDOS_VISTOS, sembrarVistos());

/*
 * Un paso que señala una pantalla de otra cosa se queda apuntando al vacío en
 * cuanto se navega. Se cierra sin marcar nada: no se terminó de ver, se salió.
 */
let escuchandoNavegacion = false;

export function useRecorrido(): {
    abierto: Ref<boolean>;
    indice: Ref<number>;
    activo: Ref<ClaveRecorrido>;
    paso: ComputedRef<PasoRecorrido | undefined>;
    pasos: ComputedRef<PasoRecorrido[]>;
    total: ComputedRef<number>;
    esUltimo: ComputedRef<boolean>;
    abrir: (clave?: ClaveRecorrido) => void;
    cerrar: () => void;
    avanzar: () => void;
    retroceder: () => void;
    irA: (destino: number) => void;
    /** Arranca sólo si nadie lo ha visto todavía en este navegador. */
    arrancarSiEsLaPrimeraVez: (clave: ClaveRecorrido) => void;
} {
    const pagina = usePage();

    /*
     * Un paso con `permiso` se le ahorra a quien no lo tiene: explicarle a un
     * técnico cómo se invita a alguien es enseñarle una pantalla que no ve.
     */
    const pasos = computed<PasoRecorrido[]>(() => {
        const permisos: string[] = pagina.props.auth?.permisos ?? [];

        return recorridos[activo.value].filter((p) => !p.permiso || permisos.includes(p.permiso));
    });
    const total = computed(() => pasos.value.length);
    const paso = computed(() => pasos.value[indice.value]);
    const esUltimo = computed(() => indice.value === total.value - 1);

    if (!escuchandoNavegacion && typeof window !== 'undefined') {
        escuchandoNavegacion = true;
        /*
         * `before` y no `navigate`: éste salta también en las recargas
         * parciales de una tabla —filtrar en mitad del recorrido lo cerraría— y
         * llega con la página nueva ya montada, justo después de que su
         * cabecera haya arrancado el suyo.
         */
        router.on('before', (evento) => {
            if (evento.detail.visit.url.pathname !== window.location.pathname) {
                abierto.value = false;
            }
        });
    }

    const abrir = (clave: ClaveRecorrido = 'panel'): void => {
        activo.value = clave;
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

        if (!vistos.value.includes(activo.value)) {
            vistos.value = [...vistos.value, activo.value];
        }
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
        indice.value = Math.min(Math.max(destino, 0), total.value - 1);
    };

    return {
        abierto,
        indice,
        activo,
        paso,
        pasos,
        total,
        esUltimo,
        abrir,
        cerrar,
        avanzar,
        retroceder,
        irA,
        arrancarSiEsLaPrimeraVez: (clave: ClaveRecorrido) => {
            // Uno abierto no se pisa: el que llega ya tendrá su ocasión.
            if (abierto.value || vistos.value.includes(clave)) {
                return;
            }

            abrir(clave);
        },
    };
}
