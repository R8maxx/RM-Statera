<script setup lang="ts">
import BarraFiltros from '@/components/tabla/BarraFiltros.vue';
import ConfirmacionAccion from '@/components/tabla/ConfirmacionAccion.vue';
import IconoAccion from '@/components/tabla/IconoAccion.vue';
import PaginacionTabla from '@/components/tabla/PaginacionTabla.vue';
import SelectorColumnas from '@/components/tabla/SelectorColumnas.vue';
import CeldaValor from '@/components/tabla/celdas/CeldaValor.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { useTablaServidor, type ValorFiltro } from '@/composables/useTablaServidor';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/vue3';
import { ArrowDownIcon, ArrowUpDownIcon, ArrowUpIcon, MoreHorizontalIcon } from '@lucide/vue';
import {
    columnOrderingFeature,
    columnPinningFeature,
    columnVisibilityFeature,
    rowSelectionFeature,
    tableFeatures,
    useTable,
} from '@tanstack/vue-table';
import { computed, ref, toRef, watch } from 'vue';

type Accion = App.Http.Resources.Definicion.Accion;
type Columna = App.Http.Resources.Definicion.Columna;
type DefinicionRecurso = App.Http.Resources.Definicion.DefinicionRecurso;
type MetaTabla = App.Http.Resources.Definicion.MetaTabla;

/**
 * Una fila tal y como la serializa `ConsultaRecurso`: el identificador, los
 * extras del recurso y un valor por columna. Los valores son heterogéneos por
 * naturaleza — texto, número, fecha ISO, `ValorEtiquetado` — y `CeldaValor` los
 * estrecha según el tipo de la columna.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type Fila = { id: number | string } & Record<string, any>;

const props = defineProps<{
    recurso: DefinicionRecurso;
    filas: Fila[];
    meta: MetaTabla;
}>();

const emit = defineEmits<{
    /**
     * Una acción masiva no puede resolverse aquí: cada recurso pide sus propios
     * datos (a qué estado, con qué nota). La tabla entrega la acción y la
     * selección, y la página decide qué hacer con ellas.
     */
    masiva: [accion: Accion, ids: (number | string)[]];
}>();

/*
 * Paginación, orden y filtros son de servidor. TanStack se queda con lo que sí
 * es de cliente: el modelo de columnas, su visibilidad, orden, anclado y ancho,
 * y la selección de filas.
 */
const {
    cargando,
    filtros,
    orden,
    hayFiltrosActivos,
    ordenarPor,
    irAPagina,
    cambiarTamano,
    aplicarFiltro,
    limpiarFiltros,
} = useTablaServidor({
    meta: toRef(props, 'meta'),
    ordenPorDefecto: props.recurso.ordenPorDefecto,
});

const caracteristicas = tableFeatures({
    columnVisibilityFeature,
    columnOrderingFeature,
    columnPinningFeature,
    rowSelectionFeature,
});

const columnas = props.recurso.columnas.map((columna) => ({
    id: columna.clave,
    accessorKey: columna.clave,
    header: columna.etiqueta,
    enableHiding: !columna.anclada,
    size: columna.ancho ? Number.parseFloat(columna.ancho) * 16 : undefined,
}));

const visibilidadInicial = Object.fromEntries(
    props.recurso.columnas
        .filter((columna) => columna.ocultaPorDefecto)
        .map((columna) => [columna.clave, false]),
);

const tabla = useTable({
    features: caracteristicas,
    columns: columnas,
    data: toRef(props, 'filas'),
    getRowId: (fila: Fila) => String(fila.id),
    enableRowSelection: props.recurso.seleccionable,
    initialState: {
        columnVisibility: visibilidadInicial,
        columnPinning: {
            start: props.recurso.columnas.filter((columna) => columna.anclada).map((columna) => columna.clave),
            end: [],
        },
    },
});

/*
 * Filtrar o paginar cambia el conjunto de filas, y una selección hecha sobre el
 * anterior deja de significar nada: se limpia. Mantenerla llevaría a aplicar una
 * acción masiva sobre filas que ya no están a la vista.
 */
watch(
    () => props.meta,
    () => tabla.resetRowSelection(),
);

const porClave = computed<Record<string, Columna>>(() =>
    Object.fromEntries(props.recurso.columnas.map((columna) => [columna.clave, columna])),
);

const visibles = computed(() =>
    tabla
        .getVisibleLeafColumns()
        .map((columna) => porClave.value[columna.id])
        .filter((columna): columna is Columna => columna !== undefined),
);

const seleccionadas = computed(() => Object.keys(tabla.atoms.rowSelection?.get() ?? {}));
const todasSeleccionadas = computed(
    () => props.filas.length > 0 && seleccionadas.value.length === props.filas.length,
);

const alineaciones: Record<string, string> = {
    izquierda: 'text-left',
    centro: 'text-center',
    derecha: 'text-right',
};

const confirmando = ref<{ accion: Accion; fila: Fila } | null>(null);

function url(accion: Accion, fila: Fila): string {
    return accion.url.replace('{id}', String(fila.id));
}

function ejecutar(accion: Accion, fila: Fila): void {
    if (accion.confirmacion !== null && confirmando.value === null) {
        confirmando.value = { accion, fila };

        return;
    }

    confirmando.value = null;
    router.visit(url(accion, fila), { method: accion.metodo, preserveScroll: true });
}

function iconoOrden(clave: string) {
    if (orden.value.clave !== clave) {
        return ArrowUpDownIcon;
    }

    return orden.value.descendente ? ArrowDownIcon : ArrowUpIcon;
}
</script>

<template>
    <section class="space-y-3">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <BarraFiltros
                v-if="recurso.filtros.length > 0"
                :filtros="recurso.filtros"
                :valores="filtros"
                :hay-filtros-activos="hayFiltrosActivos"
                @aplicar="(clave: string, valor: ValorFiltro) => aplicarFiltro(clave, valor)"
                @limpiar="limpiarFiltros"
            />
            <div v-else />

            <div class="flex items-center gap-2">
                <SelectorColumnas :columnas="tabla.getAllLeafColumns()" />

                <Link v-for="accion in recurso.accionesGenerales" :key="accion.clave" :href="accion.url">
                    <Button size="sm" class="gap-1.5">
                        <IconoAccion :nombre="accion.icono" />
                        {{ accion.etiqueta }}
                    </Button>
                </Link>
            </div>
        </header>

        <div
            v-if="recurso.seleccionable && seleccionadas.length > 0"
            class="flex flex-wrap items-center gap-3 rounded-md border border-primary/30 bg-accent px-3 py-2 text-sm"
        >
            <span class="font-medium">{{ seleccionadas.length }} seleccionadas</span>

            <Button
                v-for="accion in recurso.accionesMasivas"
                :key="accion.clave"
                size="sm"
                variant="outline"
                class="gap-1.5"
                @click="emit('masiva', accion, seleccionadas)"
            >
                <IconoAccion :nombre="accion.icono" />
                {{ accion.etiqueta }}
            </Button>

            <Button size="sm" variant="ghost" @click="tabla.resetRowSelection()">Deseleccionar</Button>
        </div>

        <div class="rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full caption-bottom text-sm">
                    <thead class="border-b">
                        <tr>
                            <th v-if="recurso.seleccionable" class="w-10 px-3 py-2">
                                <Checkbox
                                    :model-value="todasSeleccionadas"
                                    aria-label="Seleccionar todas las filas de la página"
                                    @update:model-value="tabla.toggleAllRowsSelected(!todasSeleccionadas)"
                                />
                            </th>

                            <th
                                v-for="columna in visibles"
                                :key="columna.clave"
                                :style="columna.ancho ? { width: columna.ancho } : undefined"
                                :class="
                                    cn(
                                        'px-3 py-2 font-medium text-muted-foreground whitespace-nowrap',
                                        alineaciones[columna.alineacion],
                                    )
                                "
                            >
                                <button
                                    v-if="columna.ordenable"
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-foreground"
                                    :title="columna.ayuda ?? undefined"
                                    @click="ordenarPor(columna.clave)"
                                >
                                    {{ columna.etiqueta }}
                                    <component :is="iconoOrden(columna.clave)" class="size-3.5 opacity-60" />
                                </button>
                                <span v-else :title="columna.ayuda ?? undefined">{{ columna.etiqueta }}</span>
                            </th>

                            <th v-if="recurso.accionesFila.length > 0" class="w-12 px-3 py-2">
                                <span class="sr-only">Acciones</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-if="cargando && filas.length === 0">
                            <td :colspan="visibles.length + 2" class="p-3">
                                <Skeleton v-for="n in 5" :key="n" class="mb-2 h-8 w-full" />
                            </td>
                        </tr>

                        <tr v-else-if="filas.length === 0">
                            <td
                                :colspan="visibles.length + 2"
                                class="px-3 py-12 text-center text-muted-foreground"
                            >
                                {{
                                    hayFiltrosActivos
                                        ? 'Ningún resultado con estos filtros.'
                                        : (recurso.etiquetas.vacio ?? 'Todavía no hay nada aquí.')
                                }}
                            </td>
                        </tr>

                        <tr
                            v-for="fila in filas"
                            v-else
                            :key="fila.id"
                            class="border-b last:border-0 hover:bg-muted/50"
                            :class="{ 'bg-accent/40': tabla.getRow(String(fila.id))?.getIsSelected() }"
                        >
                            <td v-if="recurso.seleccionable" class="px-3 py-2">
                                <Checkbox
                                    :model-value="tabla.getRow(String(fila.id))?.getIsSelected() ?? false"
                                    :aria-label="`Seleccionar la fila ${fila.id}`"
                                    @update:model-value="tabla.getRow(String(fila.id))?.toggleSelected()"
                                />
                            </td>

                            <td
                                v-for="columna in visibles"
                                :key="columna.clave"
                                :class="cn('px-3 py-2', alineaciones[columna.alineacion])"
                            >
                                <CeldaValor :columna="columna" :valor="fila[columna.clave]" />
                            </td>

                            <td v-if="recurso.accionesFila.length > 0" class="px-3 py-2 text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon-sm" aria-label="Acciones de la fila">
                                            <MoreHorizontalIcon />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            v-for="accion in recurso.accionesFila"
                                            :key="accion.clave"
                                            :class="accion.destructiva ? 'text-destructive' : undefined"
                                            @select="ejecutar(accion, fila)"
                                        >
                                            <IconoAccion :nombre="accion.icono" />
                                            {{ accion.etiqueta }}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginacionTabla
                :meta="meta"
                :tamanos="recurso.tamanosPagina"
                @pagina="irAPagina"
                @tamano="cambiarTamano"
            />
        </div>

        <ConfirmacionAccion
            v-if="confirmando"
            :accion="confirmando.accion"
            @cancelar="confirmando = null"
            @confirmar="ejecutar(confirmando.accion, confirmando.fila)"
        />
    </section>
</template>
