import { aQueryString, estaActivo, normalizar, type ValorFiltro } from '@/lib/filtros';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch, type Ref } from 'vue';

interface Opciones {
    /** Lo que el servidor aplicó de verdad, no lo que se pidió. */
    aplicados: Ref<Record<string, string | string[]>>;
    /** Qué props recargar al cambiar un filtro. */
    only: string[];
    /** Lo que la pantalla necesita mantener al recargar: el mes, por ejemplo. */
    extras?: () => Record<string, unknown>;
}

/**
 * Los filtros contra el servidor, sin saber nada de paginación.
 *
 * Lo usan las tres pantallas del plan de acción: la tabla —a través de
 * `useTablaServidor`, que le añade orden y paginación—, el tablero y el
 * calendario. Los tres filtran con la misma declaración y los mismos scopes, que
 * es lo único que garantiza que enseñen lo mismo: con la condición escrita en
 * tres sitios, la tabla dice doce y el tablero nueve.
 *
 * Lo que cambia entre pantallas es qué recargar (`only`) y qué hay que mantener
 * en la URL al hacerlo (`extras`).
 */
export function useFiltrosServidor({ aplicados, only, extras }: Opciones) {
    const cargando = ref(false);
    const filtros = ref<Record<string, ValorFiltro>>(normalizar(aplicados.value));

    // El servidor manda: si una recarga devuelve otros filtros —porque el
    // navegador volvió atrás, o porque uno no se pudo aplicar— el estado local
    // se rinde en vez de discutir.
    watch(
        () => aplicados.value,
        (valores) => {
            filtros.value = normalizar(valores);
        },
    );

    const hayFiltrosActivos = computed(() => Object.values(filtros.value).some(estaActivo));

    function consultar(cambios: Record<string, unknown> = {}): void {
        router.reload({
            only,
            data: {
                ...(extras?.() ?? {}),
                filter: aQueryString(filtros.value),
                ...cambios,
            },
            replace: true,
            onStart: () => (cargando.value = true),
            onFinish: () => (cargando.value = false),
        });
    }

    function aplicarFiltro(clave: string, valor: ValorFiltro): void {
        filtros.value = { ...filtros.value, [clave]: valor };
        consultar();
    }

    function limpiarFiltros(): void {
        filtros.value = {};
        consultar({ filter: {} });
    }

    return { cargando, filtros, hayFiltrosActivos, aplicarFiltro, limpiarFiltros };
}
