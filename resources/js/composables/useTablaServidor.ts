import { router } from '@inertiajs/vue3';
import { computed, ref, watch, type Ref } from 'vue';

type MetaTabla = App.Http.Resources.Definicion.MetaTabla;

/** El valor de un filtro tal y como lo maneja la interfaz. */
export type ValorFiltro = string | string[] | null;

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
 */
export function useTablaServidor({ meta, ordenPorDefecto }: Opciones) {
    const cargando = ref(false);

    /** Los filtros que el usuario está editando, antes de aplicarse. */
    const filtros = ref<Record<string, ValorFiltro>>(normalizar(meta.value.filtros));

    // La meta que vuelve del servidor manda: si una recarga descarta un filtro
    // no declarado, la barra tiene que reflejarlo y no mentir.
    watch(
        () => meta.value.filtros,
        (valores) => {
            filtros.value = normalizar(valores);
        },
    );

    const orden = computed(() => {
        const valor = meta.value.orden || ordenPorDefecto;

        return {
            clave: valor.replace(/^-/, ''),
            descendente: valor.startsWith('-'),
        };
    });

    const hayFiltrosActivos = computed(() =>
        Object.values(filtros.value).some((valor) => valor !== null && valor !== '' && valor.length > 0),
    );

    function consultar(cambios: Record<string, unknown>, reemplazarHistorial = true): void {
        router.reload({
            only: ['filas', 'meta'],
            data: {
                sort: meta.value.orden || ordenPorDefecto,
                page: meta.value.pagina,
                por_pagina: meta.value.porPagina,
                filter: aQueryString(filtros.value),
                ...cambios,
            },
            replace: reemplazarHistorial,
            onStart: () => (cargando.value = true),
            onFinish: () => (cargando.value = false),
        });
    }

    function ordenarPor(clave: string): void {
        const descendente = orden.value.clave === clave && !orden.value.descendente;

        // Cambiar el orden con la vista en la página siete no tiene sentido:
        // el conjunto entero se reordena.
        consultar({ sort: `${descendente ? '-' : ''}${clave}`, page: 1 });
    }

    function irAPagina(pagina: number): void {
        consultar({ page: pagina }, false);
    }

    function cambiarTamano(porPagina: number): void {
        consultar({ por_pagina: porPagina, page: 1 });
    }

    function aplicarFiltro(clave: string, valor: ValorFiltro): void {
        filtros.value = { ...filtros.value, [clave]: valor };
        consultar({ filter: aQueryString(filtros.value), page: 1 });
    }

    function limpiarFiltros(): void {
        filtros.value = {};
        consultar({ filter: {}, page: 1 });
    }

    return {
        cargando,
        filtros,
        orden,
        hayFiltrosActivos,
        ordenarPor,
        irAPagina,
        cambiarTamano,
        aplicarFiltro,
        limpiarFiltros,
    };
}

function normalizar(valores: Record<string, string | string[]>): Record<string, ValorFiltro> {
    return { ...valores };
}

/**
 * spatie/laravel-query-builder espera los valores múltiples separados por comas,
 * no como `filter[estado][]`.
 */
function aQueryString(valores: Record<string, ValorFiltro>): Record<string, string> {
    const salida: Record<string, string> = {};

    for (const [clave, valor] of Object.entries(valores)) {
        if (valor === null || valor === '' || (Array.isArray(valor) && valor.length === 0)) {
            continue;
        }

        salida[clave] = Array.isArray(valor) ? valor.join(',') : valor;
    }

    return salida;
}
