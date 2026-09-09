<script setup lang="ts">
import { computed } from 'vue';

/**
 * Barras horizontales para comparar el avance de unas pocas categorías: hoy, el
 * grado de implantación por marco normativo.
 *
 * Horizontales y no verticales porque lo que hay que poder leer es la etiqueta
 * —«Esquema Nacional de Seguridad (RD 311/2022)»— y en vertical no cabe sin
 * girarla. Una sola serie, así que no lleva leyenda: cada barra va rotulada.
 *
 * Es SVG-menos: rectángulos y `flex-grow`. Para cuatro barras sin ejes ni
 * escalas de tiempo, una librería de gráficas sería peso muerto en una
 * herramienta que entra en el alcance del propio SGSI.
 */
export interface Barra {
    clave: string;
    etiqueta: string;
    valor: number;
    de: number;
}

const props = defineProps<{ barras: Barra[] }>();

const filas = computed(() =>
    props.barras.map((barra) => ({
        ...barra,
        porcentaje: barra.de === 0 ? 0 : Math.round((barra.valor / barra.de) * 100),
    })),
);
</script>

<template>
    <ul class="space-y-4">
        <li v-for="fila in filas" :key="fila.clave">
            <div class="flex items-baseline justify-between gap-4">
                <p class="min-w-0 truncate text-sm font-medium" :title="fila.etiqueta">{{ fila.etiqueta }}</p>
                <p class="shrink-0 text-sm text-muted-foreground">
                    <span class="cifra font-medium text-foreground">{{ fila.porcentaje }}%</span>
                    <!-- La fracción real siempre debajo de la cifra: un
                         porcentaje sin denominador no es un dato que un auditor
                         pueda contrastar. -->
                    <span class="cifra ml-2 text-xs">{{ fila.valor }}/{{ fila.de }}</span>
                </p>
            </div>

            <div
                class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-muted"
                role="img"
                :aria-label="`${fila.etiqueta}: ${fila.valor} de ${fila.de} implantados, ${fila.porcentaje} por ciento`"
            >
                <div
                    class="h-full rounded-full bg-primary transition-[width] duration-500 ease-marca"
                    :style="{ width: `${Math.min(Math.max(fila.porcentaje, 0), 100)}%` }"
                />
            </div>
        </li>
    </ul>
</template>
