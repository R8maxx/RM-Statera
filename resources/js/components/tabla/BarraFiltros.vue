<script setup lang="ts">
import FiltroColumna from '@/components/tabla/FiltroColumna.vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { ValorFiltro } from '@/composables/useTablaServidor';
import { chipsDe, estaActivo } from '@/lib/filtros';
import { ListFilterIcon, XIcon } from '@lucide/vue';
import { computed } from 'vue';

type Filtro = App.Http.Resources.Definicion.Filtro;

/**
 * La barra de filtros: lo que no cabe en la cabecera de la tabla.
 *
 * Desde que cada filtro cuelga de su columna, aquí quedan tres cosas: la
 * búsqueda —que cruza varios campos y no pertenece a ninguna columna—, los
 * filtros cuya columna está oculta o no existe, y los chips de lo aplicado.
 *
 * Los chips salen de `props.valores`, que viene de `MetaTabla`: es lo que el
 * servidor aplicó de verdad, no lo que se pidió. Con la fila de filtros
 * plegada siguen siendo la única forma de ver qué está estrechando la tabla.
 */
const props = defineProps<{
    /** La búsqueda general, si el recurso declara una. */
    busqueda: Filtro | null;
    /** Los que no tienen columna visible bajo la que pintarse. */
    sueltos: Filtro[];
    /** Todos los del recurso, para los chips. */
    todos: Filtro[];
    valores: Record<string, ValorFiltro>;
    hayFiltrosActivos: boolean;
}>();

const emit = defineEmits<{
    aplicar: [clave: string, valor: ValorFiltro];
    limpiar: [];
}>();

const chips = computed(() => chipsDe(props.todos, props.valores));

const sueltosActivos = computed(
    () => props.sueltos.filter((filtro) => estaActivo(props.valores[filtro.clave] ?? null)).length,
);
</script>

<template>
    <div class="flex w-full min-w-0 flex-col gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <div v-if="busqueda" class="w-full sm:w-72">
                <FiltroColumna
                    :filtro="busqueda"
                    :valor="valores[busqueda.clave] ?? null"
                    variante="panel"
                    @aplicar="(clave: string, valor: ValorFiltro) => emit('aplicar', clave, valor)"
                />
            </div>

            <Popover v-if="sueltos.length > 0">
                <PopoverTrigger as-child>
                    <Button variant="outline" size="sm" class="h-9 gap-1.5">
                        <ListFilterIcon class="size-3.5" />
                        Filtros
                        <span
                            v-if="sueltosActivos > 0"
                            class="cifra rounded-full bg-primary px-1.5 text-[10px] text-primary-foreground"
                        >
                            {{ sueltosActivos }}
                        </span>
                    </Button>
                </PopoverTrigger>

                <PopoverContent align="start" class="w-72 gap-3">
                    <p class="text-xs font-medium text-muted-foreground">
                        Sin columna a la vista. Muestra la columna y el filtro sube a la cabecera.
                    </p>

                    <div v-for="filtro in sueltos" :key="filtro.clave" class="grid gap-1.5">
                        <span class="text-xs font-medium">{{ filtro.etiqueta }}</span>
                        <FiltroColumna
                            :filtro="filtro"
                            :valor="valores[filtro.clave] ?? null"
                            variante="panel"
                            @aplicar="(clave: string, valor: ValorFiltro) => emit('aplicar', clave, valor)"
                        />
                    </div>
                </PopoverContent>
            </Popover>
        </div>

        <!-- Lo aplicado de verdad, y cómo quitarlo pieza a pieza. -->
        <div v-if="chips.length > 0" class="flex flex-wrap items-center gap-1.5">
            <button
                v-for="(chip, indice) in chips"
                :key="`${chip.clave}-${indice}`"
                type="button"
                class="group inline-flex max-w-xs items-center gap-1 rounded-full border bg-muted/60 py-0.5 pr-1 pl-2.5 text-xs font-medium transition-colors hover:border-destructive/40 hover:bg-destructive/10"
                @click="emit('aplicar', chip.clave, chip.resto)"
            >
                <span class="truncate">{{ chip.etiqueta }}</span>
                <span class="sr-only">, quitar este filtro</span>
                <XIcon class="size-3 shrink-0 text-muted-foreground transition-colors group-hover:text-destructive" />
            </button>

            <Button
                v-if="hayFiltrosActivos && chips.length > 1"
                variant="ghost"
                size="xs"
                class="text-muted-foreground"
                @click="emit('limpiar')"
            >
                Limpiar todo
            </Button>
        </div>
    </div>
</template>
