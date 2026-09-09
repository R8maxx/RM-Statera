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
        document.documentElement.classList.toggle('dark', esOscuro.value);
        document.documentElement.dataset.tema = preferencia.value;
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
