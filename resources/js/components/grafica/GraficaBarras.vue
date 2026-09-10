<script setup lang="ts">
import Cifra from '@/components/Cifra.vue';
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
    /**
     * El color de la barra, cuando no es el de marca.
     *
     * Lo usa el reparto por tipo de activo, donde cada barra es una categoría
     * distinta y pintarlas todas del mismo teal borraría justo lo que la
     * gráfica viene a enseñar.
     */
    tono?: string;
}

const props = defineProps<{ barras: Barra[] }>();

/*
 * Escritos enteros y no compuestos, como en `BarraSegmentada`: Tailwind lee el
 * fichero como texto y una clase construida en ejecución no se genera.
 */
const fondos: Record<string, string> = {
    'tipo:servicios': 'bg-tipo-servicios',
    'tipo:datos': 'bg-tipo-datos',
    'tipo:software': 'bg-tipo-software',
    'tipo:hardware': 'bg-tipo-hardware',
    'tipo:comunicaciones': 'bg-tipo-comunicaciones',
    'tipo:soportes': 'bg-tipo-soportes',
    'tipo:equipamiento_auxiliar': 'bg-tipo-equipamiento-auxiliar',
    'tipo:instalaciones': 'bg-tipo-instalaciones',
    'tipo:personal': 'bg-tipo-personal',
    implantado: 'bg-estado-implantado',
    en_progreso: 'bg-estado-en-progreso',
    planificado: 'bg-estado-planificado',
    no_iniciado: 'bg-estado-no-iniciado/45',
    no_aplica: 'bg-estado-no-aplica/45',
};

const filas = computed(() =>
    props.barras.map((barra) => ({
        ...barra,
        porcentaje: barra.de === 0 ? 0 : Math.round((barra.valor / barra.de) * 100),
        // Sin tono declarado se comporta como siempre: teal de marca.
        fondo: barra.tono === undefined ? 'bg-primary' : (fondos[barra.tono] ?? 'bg-primary'),
    })),
);
</script>

<template>
    <ul class="space-y-4">
        <li v-for="fila in filas" :key="fila.clave">
            <div class="flex items-baseline justify-between gap-4">
                <p class="min-w-0 truncate text-sm font-medium" :title="fila.etiqueta">{{ fila.etiqueta }}</p>
                <p class="shrink-0 text-sm text-muted-foreground">
                    <Cifra class="font-medium text-foreground" :valor="fila.porcentaje" sufijo="%" />
                    <!-- La fracción real siempre debajo de la cifra: un
                         porcentaje sin denominador no es un dato que un auditor
                         pueda contrastar. -->
                    <span class="cifra ml-2 text-xs"><Cifra :valor="fila.valor" />/{{ fila.de }}</span>
                </p>
            </div>

            <div
                class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-muted"
                role="img"
                :aria-label="`${fila.etiqueta}: ${fila.valor} de ${fila.de}, ${fila.porcentaje} por ciento`"
            >
                <div
                    class="h-full rounded-full transition-[width] duration-500 ease-marca"
                    :class="fila.fondo"
                    :style="{ width: `${Math.min(Math.max(fila.porcentaje, 0), 100)}%` }"
                />
            </div>
        </li>
    </ul>
</template>
