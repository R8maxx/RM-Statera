<script setup lang="ts">
import { useMovimientoReducido } from '@/composables/useMovimientoReducido';
import { curvaEnPantalla, duracion } from '@/lib/motion';
import { computed, onMounted } from 'vue';

/**
 * La tendencia de un indicador en una fila: sus últimos periodos como barras.
 *
 * Va **a escala de su propio máximo** y no de cero a cien: lo que cuenta es
 * hacia dónde va, y la cifra ya está en la columna de al lado.
 *
 * **Se levanta al abrir la ficha, y es parte del quinto momento de DESIGN.md
 * §10, no un sexto**: es la misma serie que la gráfica de arriba, contada en el
 * mismo tiempo (`duracion.trazo`) y con la misma curva, así que las dos se leen
 * como un solo gesto de izquierda a derecha. Una vez por visita: la ficha no se
 * desmonta en una recarga parcial, así que vincular o desvincular no la repite;
 * sólo la fila que entra nueva se levanta, que es justo el acuse de que entró.
 *
 * Con la API de animaciones web, como `GraficaSerie`: el `@media` global de
 * `app.css` no alcanza a `element.animate()`, y con movimiento reducido las
 * barras están quietas desde el primer fotograma.
 */
const props = defineProps<{
    serie: App.Http.Resources.Metrica.PuntoSerie[];
}>();

const { reducido } = useMovimientoReducido();

const barras = computed(() => {
    const techo = Math.max(...props.serie.map((punto) => punto.valor), 1);

    return props.serie.map((punto, indice) => ({
        clave: punto.periodo,
        alto: Math.max(8, Math.round((punto.valor / techo) * 100)),
        ultima: indice === props.serie.length - 1,
    }));
});

const descripcion = computed(
    () => `Últimos periodos: ${props.serie.map((punto) => `${punto.etiqueta}, ${punto.valorEscrito}`).join('; ')}`,
);

/*
 * Mismo criterio que la ref en función de `GraficaSerie`: un array normal, no
 * reactivo, porque escribir en algo reactivo desde la ref pide otro render.
 */
const nodos: (Element | null)[] = [];

onMounted(() => {
    if (reducido.value || document.visibilityState !== 'visible') {
        return;
    }

    const total = duracion.trazo * 1000;
    const cada = total / Math.max(nodos.length, 1);

    nodos.forEach((nodo, indice) => {
        nodo?.animate([{ transform: 'scaleY(0)' }, { transform: 'scaleY(1)' }], {
            duration: cada * 1.6,
            delay: indice * cada * 0.6,
            easing: `cubic-bezier(${curvaEnPantalla.join(',')})`,
            fill: 'backwards',
        });
    });
});
</script>

<template>
    <div class="flex h-7 items-end gap-1" role="img" :aria-label="descripcion">
        <span
            v-for="(barra, indice) in barras"
            :key="barra.clave"
            :ref="(elemento) => (nodos[indice] = elemento as Element | null)"
            class="w-3.5 origin-bottom rounded-sm"
            :class="barra.ultima ? 'bg-primary' : 'bg-primary/30'"
            :style="{ height: `${barra.alto}%` }"
        />
    </div>
</template>
