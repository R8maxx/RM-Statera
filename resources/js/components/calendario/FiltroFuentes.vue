<script setup lang="ts">
import IconoTipo from '@/components/IconoTipo.vue';
import { computed } from 'vue';

type Filtro = App.Http.Resources.Definicion.Filtro;

/**
 * Los chips de fuente del calendario: **filtro y leyenda a la vez**.
 *
 * Como filtro, porque con tantas fuentes el control más importante de la pantalla
 * no puede vivir a dos clics detrás del embudo de `BarraFiltros`: ahí cae por no
 * tener columna bajo la que ponerse, y con tres era tolerable.
 *
 * Como leyenda, porque en la rejilla **el icono es el único canal que identifica
 * la fuente** —el color dice cómo va, no qué es— y DESIGN.md § 3 avisa de que dos
 * iconos parecidos a 14 px se confunden. Esta fila es la clave de esos iconos, y
 * no ocupa sitio extra porque ya tenía que estar por ser el filtro.
 *
 * **No hay familia de color por fuente y no la va a haber.** La rueda de hue está
 * agotada —nueve tipos de activo, cuatro del DAFO, seis de estado— y la peor
 * pareja ya está por debajo del suelo de contraste. Un tono por fuente la rompe.
 */
const props = defineProps<{
    filtro: Filtro;
    /** Lo que el servidor aplicó de verdad, no lo que el cliente cree. */
    seleccionadas: string[];
}>();

const emit = defineEmits<{ aplicar: [clave: string, valor: string[]] }>();

const opciones = computed(() => props.filtro.opciones ?? []);

const activa = (valor: string): boolean => props.seleccionadas.includes(valor);

/**
 * Sin ninguna marcada se ven todas, que es lo que hace el servidor con una lista
 * vacía. Por eso desmarcar la última no deja el calendario en blanco: lo
 * devuelve a «todas», que es lo que alguien espera al quitar el último filtro.
 */
function alternar(valor: string): void {
    const siguiente = activa(valor)
        ? props.seleccionadas.filter((actual) => actual !== valor)
        : [...props.seleccionadas, valor];

    emit('aplicar', props.filtro.clave, siguiente);
}
</script>

<template>
    <div v-if="opciones.length > 1" role="group" :aria-label="filtro.etiqueta" class="flex flex-wrap gap-1.5">
        <button
            v-for="opcion in opciones"
            :key="opcion.valor"
            type="button"
            :aria-pressed="activa(opcion.valor)"
            class="inline-flex min-h-11 items-center gap-1.5 rounded-full border px-3 text-xs transition-colors sm:min-h-8"
            :class="
                activa(opcion.valor)
                    ? 'border-transparent bg-accent text-accent-foreground'
                    : 'border-border text-muted-foreground hover:text-foreground'
            "
            @click="alternar(opcion.valor)"
        >
            <IconoTipo :nombre="opcion.icono ?? ''" />
            {{ opcion.etiqueta }}
        </button>
    </div>
</template>
