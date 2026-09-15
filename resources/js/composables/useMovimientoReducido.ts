import {
    entrada,
    escalonado,
    estatico,
    salida,
    transicion,
    transicionEnPantalla,
    type Variantes,
} from '@/lib/motion';
import { usePreferredReducedMotion } from '@vueuse/core';
import { computed, type ComputedRef } from 'vue';

/**
 * La preferencia de movimiento reducido, resuelta en un único sitio.
 *
 * `app.css` ya lleva un `@media (prefers-reduced-motion: reduce)` global que
 * anula transiciones y animaciones CSS, y `<MotionConfig reduced-motion="user">`
 * cubre lo que anima motion-v. Esto es la tercera pieza: las decisiones que
 * dependen de la preferencia y que no son ni CSS ni una variante —no contar un
 * porcentaje de 0 a 62, no escalonar una lista— y las variantes ya degradadas,
 * para que ningún componente vuelva a comprobarlo a mano.
 */
export function useMovimientoReducido(): {
    reducido: ComputedRef<boolean>;
    variantesEntrada: ComputedRef<Variantes>;
    variantesSalida: ComputedRef<Variantes>;
    variantesEscalonado: (retraso?: number) => ComputedRef<Variantes>;
    transicionSegura: ComputedRef<{ duration: number; ease?: readonly number[] }>;
    transicionMovimiento: ComputedRef<{ duration: number; ease?: readonly number[] }>;
} {
    const preferencia = usePreferredReducedMotion();
    const reducido = computed(() => preferencia.value === 'reduce');

    return {
        reducido,
        variantesEntrada: computed(() => (reducido.value ? estatico : entrada)),
        /*
         * Con movimiento reducido la salida sigue existiendo, pero sólo se
         * desvanece. Quitarla del todo devolvería el corte seco, y desaparecer
         * de un fotograma al siguiente es justo lo que confunde a quien pidió
         * menos movimiento: menos y más suave, no cero.
         */
        variantesSalida: computed(() =>
            reducido.value ? { oculto: { opacity: 0, transition: { duration: 0.1 } } } : salida,
        ),
        variantesEscalonado: (retraso = 0.04) =>
            computed(() => (reducido.value ? { oculto: {}, visible: {} } : escalonado(retraso))),
        transicionSegura: computed(() => (reducido.value ? { duration: 0 } : transicion)),
        /* Para lo que se desplaza de un sitio a otro, no para lo que aparece. */
        transicionMovimiento: computed(() => (reducido.value ? { duration: 0 } : transicionEnPantalla)),
    };
}
