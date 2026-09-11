import {
    computed,
    inject,
    onUnmounted,
    provide,
    ref,
    useId,
    watch,
    type ComputedRef,
    type InjectionKey,
    type Ref,
} from 'vue';

/**
 * Qué campos obligatorios quedan por rellenar, en vivo.
 *
 * En un formulario de ocho secciones y veintiocho campos, la única respuesta a
 * «¿me dejo algo?» era pulsar Guardar y leer el resumen de errores. Esto la
 * adelanta sin duplicar ni una regla de validación: el `FormRequest` sigue
 * siendo la única fuente de verdad y esto es sólo una cuenta de lo que está
 * vacío. Por eso tampoco deshabilita el botón de envío.
 *
 * Dos decisiones que importan:
 *
 * 1. **El valor se lee del `FormData` del propio `<form>`, no de un `v-model`.**
 *    Los campos de este producto van sobre el `<Form>` de Inertia v3 y la mitad
 *    no tiene estado en Vue. Un único listener en el formulario los cubre todos,
 *    incluidos los que viajan en un `<input type="hidden">` porque Reka pinta un
 *    botón: select, casillas y opciones.
 * 2. **El registro va por ciclo de vida del componente.** Un campo que sólo
 *    aparece con cierto estado —la fecha de baja de un activo retirado— se da de
 *    alta al montarse y de baja al desmontarse, así que la condición no se
 *    escribe dos veces.
 */

interface ContextoObligatorios {
    /** Nombre del campo obligatorio => id de la sección que lo contiene. */
    registrados: Ref<Map<string, string | null>>;
    /** Nombres de los campos obligatorios que ahora mismo están vacíos. */
    faltantes: Ref<Set<string>>;
    registrar: (nombre: string, seccion: string | null) => void;
    darDeBaja: (nombre: string) => void;
}

const CLAVE_OBLIGATORIOS = Symbol('statera.obligatorios') as InjectionKey<ContextoObligatorios>;
const CLAVE_SECCION = Symbol('statera.seccion') as InjectionKey<string>;

/** Un valor del `FormData` cuenta como relleno si tiene texto o es un fichero. */
function tieneValor(valor: FormDataEntryValue): boolean {
    return valor instanceof File ? valor.size > 0 : valor.trim() !== '';
}

/**
 * Lo llama `FormularioRecurso` con la referencia a su `<form>`.
 *
 * Devuelve el total pendiente y si hay cambios sin guardar; el reparto por
 * sección lo resuelve cada `SeccionFormulario` con `usarSeccionObligatorios()`.
 */
export function proveerObligatorios(formulario: Ref<HTMLFormElement | null>): {
    pendientes: ComputedRef<number>;
    sucio: Ref<boolean>;
} {
    const registrados = ref(new Map<string, string | null>());
    const faltantes = ref(new Set<string>());
    const sucio = ref(false);

    function recalcular(): void {
        const form = formulario.value;

        if (form === null) {
            return;
        }

        const datos = new FormData(form);

        faltantes.value = new Set(
            [...registrados.value.keys()].filter((nombre) => {
                /*
                 * Las casillas viajan como `nombre[]`, y con la lista vacía
                 * mandan una entrada en blanco a propósito —para que el
                 * servidor distinga «ninguno» de «no venía el campo»—, así que
                 * ahí `getAll` devuelve [''] y no [].
                 */
                const valores = datos.has(nombre) ? datos.getAll(nombre) : datos.getAll(`${nombre}[]`);

                return !valores.some(tieneValor);
            }),
        );
    }

    /*
     * En microtarea: los campos condicionales se montan como reacción al mismo
     * `change` que dispara esto, y contarlos antes de que Vue repinte los leería
     * del DOM anterior.
     */
    function recalcularTrasPintar(): void {
        queueMicrotask(recalcular);
    }

    function alCambiar(): void {
        sucio.value = true;
        recalcularTrasPintar();
    }

    provide(CLAVE_OBLIGATORIOS, {
        registrados,
        faltantes,
        registrar(nombre, seccion) {
            registrados.value.set(nombre, seccion);
            recalcularTrasPintar();
        },
        darDeBaja(nombre) {
            registrados.value.delete(nombre);
            recalcularTrasPintar();
        },
    });

    /*
     * El listener va en el formulario y no en cada campo: `input` y `change`
     * burbujean, así que uno solo ve los veintiocho.
     */
    const parar = watch(
        formulario,
        (form, anterior) => {
            anterior?.removeEventListener('input', alCambiar);
            anterior?.removeEventListener('change', alCambiar);

            if (form) {
                form.addEventListener('input', alCambiar);
                form.addEventListener('change', alCambiar);
                recalcular();
            }
        },
        { immediate: true },
    );

    onUnmounted(() => {
        parar();
        formulario.value?.removeEventListener('input', alCambiar);
        formulario.value?.removeEventListener('change', alCambiar);
    });

    return { pendientes: computed(() => faltantes.value.size), sucio };
}

/**
 * Lo llama cada campo obligatorio con su nombre.
 *
 * Fuera de un `FormularioRecurso` no hace nada, que es justo lo que tiene que
 * pasar en el perfil o en la valoración de un sistema: ni error ni contador.
 */
export function registrarCampoObligatorio(nombre: string, requerido: () => boolean): void {
    const contexto = inject(CLAVE_OBLIGATORIOS, null);

    if (contexto === null) {
        return;
    }

    const seccion = inject(CLAVE_SECCION, null);

    watch(
        requerido,
        (esObligatorio) => {
            if (esObligatorio) {
                contexto.registrar(nombre, seccion);
            } else {
                contexto.darDeBaja(nombre);
            }
        },
        { immediate: true },
    );

    onUnmounted(() => contexto.darDeBaja(nombre));
}

/**
 * Lo llama `SeccionFormulario`: crea su identidad, la provee a sus campos y
 * cuenta los suyos.
 */
export function usarSeccionObligatorios(): ComputedRef<number> {
    const id = useId();
    const contexto = inject(CLAVE_OBLIGATORIOS, null);

    provide(CLAVE_SECCION, id);

    if (contexto === null) {
        return computed(() => 0);
    }

    return computed(
        () => [...contexto.faltantes.value].filter((nombre) => contexto.registrados.value.get(nombre) === id).length,
    );
}
