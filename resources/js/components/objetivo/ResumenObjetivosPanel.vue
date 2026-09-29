<script setup lang="ts">
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import TarjetaRegistro, { type FilaRegistro } from '@/components/panel/TarjetaRegistro.vue';
import { computed } from 'vue';

/**
 * Los objetivos de seguridad, de un vistazo (cláusula 6.2).
 *
 * Va pegado al desempeño porque son las dos mitades de la misma pregunta: los
 * indicadores dicen cómo va y los objetivos dicen contra qué.
 *
 * **Sin anillo y sin porcentaje de objetivos alcanzados**, por el mismo motivo
 * por el que no lo llevan el plan de acción ni el cuadro de indicadores: esa
 * cifra sube al cerrar y baja al comprometerse con uno nuevo, así que castiga
 * por ponerse objetivos ambiciosos.
 *
 * **Una sola cifra en rojo, y no es «no alcanzado»**: es el objetivo aprobado
 * cuyo plazo pasó y que nadie ha cerrado. Quedarse corto es la distancia que
 * queda; haberse comprometido por escrito a una fecha que ya pasó y no decir si
 * se consiguió es la 6.2 sin terminar.
 *
 * El reparto por estado **incluye los cerrados**, a diferencia del plan de
 * acción: la pregunta aquí es «de los que nos pusimos, cuántos alcanzamos», que
 * es literalmente una de las siete entradas de la revisión por la dirección.
 */
const props = defineProps<{ resumen: App.Http.Resources.Panel.ResumenObjetivosPanel }>();

const segmentos = computed<Segmento[]>(() =>
    props.resumen.porEstado.map((tramo) => ({ clave: tramo.clave, etiqueta: tramo.etiqueta, valor: tramo.valor, tono: tramo.tono })),
);

const filas = computed<FilaRegistro[]>(() => [
    { clave: 'vencidos', etiqueta: 'Fuera de plazo', valor: props.resumen.vencidos, href: '/objetivos?filter[vencidos]=1', alerta: true },
    { clave: 'sin_indicador', etiqueta: 'Sin indicador que lo mida', valor: props.resumen.sinIndicador, href: '/objetivos?filter[sin_indicador]=1' },
    { clave: 'sin_actuacion', etiqueta: 'Sin actuación planificada', valor: props.resumen.sinActuacion, href: '/objetivos?filter[sin_actuacion]=1' },
]);
</script>

<template>
    <TarjetaRegistro
        titulo="Objetivos de seguridad"
        href="/objetivos"
        :cifra="resumen.vivos"
        unidad="en curso"
        :de="resumen.total"
        :filas="filas"
        vacio="Todos con su indicador y su actuación."
    >
        <BarraSegmentada v-if="resumen.total > 0" :segmentos="segmentos" leyenda />
    </TarjetaRegistro>
</template>
