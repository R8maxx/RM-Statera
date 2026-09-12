import { useFiltrosServidor } from '@/composables/useFiltrosServidor';
import { aQueryString, type ValorFiltro } from '@/lib/filtros';
import { router } from '@inertiajs/vue3';
import { computed, ref, type Ref } from 'vue';

type MetaTabla = App.Http.Resources.Definicion.MetaTabla;

export type { ValorFiltro };

interface Opciones {
    meta: Ref<MetaTabla>;
    ordenPorDefecto: string;
}

/**
 * Mantiene el estado de la tabla en la URL y lo resuelve en el servidor.
 *
 * Paginación, orden y filtros son consultas de servidor, así que aquí no se
 * guarda una copia del estado: se traduce a query string y se recarga sólo
 * `filas` y `meta`. La definición del recurso viaja como prop `once` y no se
 * vuelve a pedir.
 *
 * **Los filtros no viven aquí**, viven en `useFiltrosServidor`: los usan también
 * el tablero y el calendario, que no paginan ni ordenan. Esto es lo que la tabla
 * añade encima.
 */
export function useTablaServidor({ meta, ordenPorDefecto }: Opciones) {
    const paginando = ref(false);

    const orden = computed(() => {
        const valor = meta.value.orden || ordenPorDefecto;

        return {
            clave: valor.replace(/^-/, ''),
            descendente: valor.startsWith('-'),
        };
    });

    /*
     * Lo que la tabla arrastra en cada recarga y las otras dos pantallas no: sin
     * esto, filtrar devolvería a la página 1 con el orden por defecto.
     */
    const extras = (): Record<string, unknown> => ({
        sort: meta.value.orden || ordenPorDefecto,
        page: meta.value.pagina,
        por_pagina: meta.value.porPagina,
    });

    const filtrado = useFiltrosServidor({
        aplicados: computed(() => meta.value.filtros),
        only: ['filas', 'meta'],
        extras,
    });

    // Cambiar un filtro devuelve a la primera página: la séptima de un resultado
    // que ahora tiene dos sale vacía y parece que el filtro no encontró nada.
    function aplicarFiltro(clave: string, valor: ValorFiltro): void {
        filtrado.filtros.value = { ...filtrado.filtros.value, [clave]: valor };
        consultar({ filter: aQueryString(filtrado.filtros.value), page: 1 });
    }

    function limpiarFiltros(): void {
        filtrado.filtros.value = {};
        consultar({ filter: {}, page: 1 });
    }

    function consultar(cambios: Record<string, unknown>, reemplazarHistorial = true): void {
        router.reload({
            only: ['filas', 'meta'],
            data: {
                ...extras(),
                filter: aQueryString(filtrado.filtros.value),
                ...cambios,
            },
            replace: reemplazarHistorial,
            onStart: () => (paginando.value = true),
            onFinish: () => (paginando.value = false),
        });
    }

    function ordenarPor(clave: string, descendente?: boolean): void {
        const sentido = descendente ?? (orden.value.clave === clave && !orden.value.descendente);

        consultar({ sort: `${sentido ? '-' : ''}${clave}`, page: 1 });
    }

    function irAPagina(pagina: number): void {
        consultar({ page: pagina }, false);
    }

    function cambiarTamano(porPagina: number): void {
        consultar({ por_pagina: porPagina, page: 1 });
    }

    const cargando = computed(() => paginando.value || filtrado.cargando.value);

    return {
        cargando,
        filtros: filtrado.filtros,
        orden,
        hayFiltrosActivos: filtrado.hayFiltrosActivos,
        ordenarPor,
        irAPagina,
        cambiarTamano,
        aplicarFiltro,
        limpiarFiltros,
    };
}
