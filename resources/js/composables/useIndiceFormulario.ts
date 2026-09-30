import { computed, type ComputedRef, inject, type InjectionKey, onUnmounted, provide, type Ref, shallowRef } from 'vue';

/**
 * Las secciones de un formulario, para el índice del carril.
 *
 * En un formulario de cuatro secciones o más, la que debe dos campos puede estar
 * tres pantallas más abajo y lo único que lo decía era la nota del pie. El
 * índice las enseña todas a la vez, con lo que le falta a cada una, y lleva a
 * la que se pulsa.
 *
 * **Cada `SeccionFormulario` se da de alta sola**, igual que los campos
 * obligatorios en `useCamposObligatorios`: el formulario no declara su índice
 * aparte, así que no hay una segunda lista que se desincronice de la primera.
 * El orden no es el de alta sino **el del documento**: una sección que aparece
 * con cierto estado se monta la última, pero se pinta donde está.
 */
export interface EntradaIndice {
    id: string;
    titulo: string;
    pendientes: ComputedRef<number>;
    elemento: Ref<HTMLElement | null>;
    /** Abre la sección si está plegada. Sin pliegue, no hace nada. */
    abrir: () => void;
}

interface ContextoIndice {
    registrar: (entrada: EntradaIndice) => void;
    darDeBaja: (id: string) => void;
}

const CLAVE_INDICE = Symbol('statera.indice') as InjectionKey<ContextoIndice>;

/** Lo llama `FormularioRecurso`. */
export function proveerIndice(): { secciones: ComputedRef<EntradaIndice[]> } {
    const entradas = shallowRef<EntradaIndice[]>([]);

    provide(CLAVE_INDICE, {
        registrar(entrada) {
            entradas.value = [...entradas.value.filter((otra) => otra.id !== entrada.id), entrada];
        },
        darDeBaja(id) {
            entradas.value = entradas.value.filter((entrada) => entrada.id !== id);
        },
    });

    const secciones = computed(() =>
        [...entradas.value].sort((a, b) => {
            if (a.elemento.value === null || b.elemento.value === null) {
                return 0;
            }

            return a.elemento.value.compareDocumentPosition(b.elemento.value) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
        }),
    );

    return { secciones };
}

/**
 * Lo llama `SeccionFormulario` tras montarse, cuando ya tiene su elemento.
 *
 * Fuera de un `FormularioRecurso` —la valoración de un sistema— no hace nada.
 */
export function registrarEnIndice(): (entrada: EntradaIndice) => void {
    const contexto = inject(CLAVE_INDICE, null);
    let id: string | null = null;

    onUnmounted(() => {
        if (contexto !== null && id !== null) {
            contexto.darDeBaja(id);
        }
    });

    return (entrada) => {
        id = entrada.id;
        contexto?.registrar(entrada);
    };
}
