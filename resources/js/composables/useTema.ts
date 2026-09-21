import { computed, onMounted, onUnmounted, ref, type ComputedRef, type Ref } from 'vue';

export type PreferenciaTema = 'claro' | 'oscuro' | 'sistema';

const CLAVE = 'statera.tema';

/**
 * El tema, con sus tres estados reales.
 *
 * El interruptor binario anterior tenía un fallo: en cuanto alguien lo tocaba
 * una vez, la preferencia del sistema quedaba descartada para siempre y la
 * aplicación dejaba de seguir el cambio automático de la tarde. `sistema` es un
 * estado de primera clase, y es el de partida.
 *
 * El valor lo escribe primero el script en línea de `app.blade.php`, antes del
 * primer pintado; esto sólo lo recoge y lo mantiene.
 */
export function useTema(): {
    preferencia: Ref<PreferenciaTema>;
    esOscuro: ComputedRef<boolean>;
    fijar: (valor: PreferenciaTema) => void;
    siguiente: () => void;
} {
    const preferencia = ref<PreferenciaTema>('sistema');
    const sistemaOscuro = ref(false);

    let consulta: MediaQueryList | undefined;
    const alCambiarSistema = (evento: MediaQueryListEvent): void => {
        sistemaOscuro.value = evento.matches;
        aplicar();
    };

    const esOscuro = computed(
        () => preferencia.value === 'oscuro' || (preferencia.value === 'sistema' && sistemaOscuro.value),
    );

    function aplicar(): void {
        const raiz = document.documentElement;

        const escribir = (): void => {
            raiz.classList.toggle('dark', esOscuro.value);
            raiz.dataset.tema = preferencia.value;
        };

        /*
         * El fundido sólo cuando la pantalla cambia DE VERDAD de luminancia.
         *
         * Las tres preferencias son tres, pero los temas son dos: elegir «el
         * del sistema» teniendo el sistema en claro y estando ya en claro no
         * cambia un píxel, y disolver la pantalla entera durante 380 ms para
         * dejarla igual es una animación que miente sobre lo que ha pasado.
         */
        const cambiaLaLuz = raiz.classList.contains('dark') !== esOscuro.value;

        /*
         * `typeof` y no `if (!document.startViewTransition)`: el método está
         * tipado como obligatorio en lib.dom, así que la comprobación de
         * verdad la rechaza `vue-tsc` con un TS2774 —«esta condición siempre
         * es falsa, ¿querías llamarla?»—. Donde no exista, el tema cambia de
         * golpe, que es exactamente lo que se hacía hasta ahora.
         */
        if (!cambiaLaLuz || typeof document.startViewTransition !== 'function') {
            escribir();

            return;
        }

        /*
         * `ready` se rechaza cuando el navegador se salta la transición, y el
         * caso que la salta de verdad es el más frecuente de los tres: la
         * pestaña en segundo plano cuando el sistema cambia de tema al
         * atardecer. Sin el `catch`, eso es un «Uncaught (in promise)
         * InvalidStateError» en la consola de cualquiera que deje Statera
         * abierta en una pestaña que no mira.
         *
         * Saltársela no impide nada: el navegador ejecuta igual la función de
         * actualización —comprobado—, así que el tema se aplica y lo único que
         * se pierde es el fundido, que en una pestaña oculta no ve nadie.
         */
        document.startViewTransition(escribir).ready.catch(() => {});
    }

    function fijar(valor: PreferenciaTema): void {
        preferencia.value = valor;
        aplicar();

        try {
            if (valor === 'sistema') {
                localStorage.removeItem(CLAVE);
            } else {
                localStorage.setItem(CLAVE, valor);
            }
        } catch {
            /* almacenamiento no disponible: el tema dura lo que la pestaña */
        }
    }

    /** Claro → oscuro → sistema. El ciclo no esconde ninguno de los tres. */
    function siguiente(): void {
        fijar(preferencia.value === 'claro' ? 'oscuro' : preferencia.value === 'oscuro' ? 'sistema' : 'claro');
    }

    onMounted(() => {
        const guardado = document.documentElement.dataset.tema;
        preferencia.value = guardado === 'claro' || guardado === 'oscuro' ? guardado : 'sistema';

        consulta = window.matchMedia('(prefers-color-scheme: dark)');
        sistemaOscuro.value = consulta.matches;
        consulta.addEventListener('change', alCambiarSistema);
    });

    onUnmounted(() => consulta?.removeEventListener('change', alCambiarSistema));

    return { preferencia, esOscuro, fijar, siguiente };
}
