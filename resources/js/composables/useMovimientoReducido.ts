import { entrada, escalonado, estatico, transicion, type Variantes } from '@/lib/motion';
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
    variantesEscalonado: (retraso?: number) => ComputedRef<Variantes>;
    transicionSegura: ComputedRef<{ duration: number; ease?: readonly number[] }>;
} {
    const preferencia = usePreferredReducedMotion();
    const reducido = computed(() => preferencia.value === 'reduce');

    return {
        reducido,
        variantesEntrada: computed(() => (reducido.value ? estatico : entrada)),
        variantesEscalonado: (retraso = 0.04) =>
            computed(() => (reducido.value ? { oculto: {}, visible: {} } : escalonado(retraso))),
        transicionSegura: computed(() => (reducido.value ? { duration: 0 } : transicion)),
    };
}
