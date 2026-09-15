<script setup lang="ts">
import FiltroColumna from '@/components/tabla/FiltroColumna.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { ValorFiltro } from '@/composables/useTablaServidor';
import { estaActivo } from '@/lib/filtros';
import { cn } from '@/lib/utils';
import { ChevronDownIcon } from '@lucide/vue';
import { computed, ref } from 'vue';

type Filtro = App.Http.Resources.Definicion.Filtro;

/**
 * Varios filtros que se pintan bajo la MISMA columna.
 *
 * Pasa en cuanto dos `Filtro::porScope` comparten `->enColumna()`: «Plazo» de
 * tareas recibe `vencidas`, `por_vencer` y `sin_plazo`. Antes sólo cabía uno
 * —el mapa de columna a filtro se construía con `Object.fromEntries`, que con
 * claves repetidas se queda con la última— así que dos de los tres filtros
 * declarados por el recurso **no existían en la interfaz**, y cuál sobrevivía
 * lo decidía el orden de declaración. No fallaba nada: sencillamente no
 * estaban.
 *
 * Aquí el disparador dice el nombre de la columna y el contenido apila los
 * controles reales, cada uno con su predicado por rótulo. Se reutiliza
 * `FiltroColumna` en su variante de panel: un segundo dialecto de filtro es
 * exactamente lo que la capa de recursos existe para no tener.
 */
const props = defineProps<{
    filtros: Filtro[];
    titulo: string;
    valores: Record<string, ValorFiltro>;
}>();

const emit = defineEmits<{ aplicar: [clave: string, valor: ValorFiltro] }>();

const abierto = ref(false);

const activos = computed(() => props.filtros.filter((filtro) => estaActivo(props.valores[filtro.clave] ?? null)));

/*
 * Con uno aplicado se dice cuál; con varios, cuántos. Enumerar tres predicados
 * en una celda de columna no cabe, y cortarlos a mitad de palabra es el defecto
 * que este mismo arreglo viene a quitar de la tabla.
 */
const rotulo = computed(() => {
    if (activos.value.length === 0) {
        return props.titulo;
    }

    return activos.value.length === 1 ? activos.value[0].etiqueta : `${activos.value.length} filtros`;
});

const limpiarTodos = (): void => {
    for (const filtro of activos.value) {
        emit('aplicar', filtro.clave, null);
    }
};
</script>

<template>
    <Popover v-model:open="abierto">
        <PopoverTrigger
            :class="
                cn(
                    'flex h-8 w-full min-w-0 items-center gap-1 rounded-md border px-2 text-left text-xs transition-colors outline-none',
                    'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50',
                    activos.length > 0
                        ? 'border-primary/40 bg-accent font-medium text-accent-foreground'
                        : 'border-input bg-background text-muted-foreground hover:bg-muted hover:text-foreground',
                )
            "
            :aria-label="`Filtrar por ${titulo}`"
        >
            <span class="min-w-0 flex-1 truncate">{{ rotulo }}</span>
            <ChevronDownIcon class="size-3 shrink-0 opacity-60" />
        </PopoverTrigger>

        <PopoverContent align="start" class="w-64 gap-3 p-3">
            <div v-for="filtro in filtros" :key="filtro.clave" class="grid gap-1.5">
                <span class="text-xs font-medium">{{ filtro.etiqueta }}</span>
                <FiltroColumna
                    :filtro="filtro"
                    :valor="valores[filtro.clave] ?? null"
                    variante="panel"
                    @aplicar="(clave: string, valor: ValorFiltro) => emit('aplicar', clave, valor)"
                />
            </div>

            <button
                v-if="activos.length > 0"
                type="button"
                class="h-8 rounded-md text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                @click="limpiarTodos"
            >
                Quitar {{ activos.length === 1 ? 'el filtro' : 'los filtros' }}
            </button>
        </PopoverContent>
    </Popover>
</template>
