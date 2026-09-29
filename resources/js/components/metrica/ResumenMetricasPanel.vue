<script setup lang="ts">
import BarraSegmentada, { type Segmento } from '@/components/BarraSegmentada.vue';
import TarjetaRegistro, { type FilaRegistro } from '@/components/panel/TarjetaRegistro.vue';
import { computed } from 'vue';

/**
 * El desempeño, de un vistazo (§ 4.14, cláusula 9.1).
 *
 * **Sin anillo de progreso y sin porcentaje de «indicadores en objetivo»**, por
 * el mismo motivo por el que el plan de acción no lleva porcentaje de tareas
 * hechas: esa cifra sube al ponerse objetivos flojos y baja al ponerse
 * ambiciosos, así que mide el listón y no el desempeño. Un indicador que castiga
 * por apuntar lo que falta enseña a no apuntarlo.
 *
 * **Una sola cifra en rojo, y no es «fuera de objetivo»**: es el periodo que
 * cerró sin medición. Quedarse por debajo de una cifra que la propia
 * organización se puso es la distancia que queda; haberse comprometido a medir
 * cada trimestre y no haber medido es la cláusula 9.1 sin hacer, y es lo primero
 * que un auditor comprueba.
 */
const props = defineProps<{ resumen: App.Http.Resources.Panel.ResumenMetricasPanel }>();

const segmentos = computed<Segmento[]>(() =>
    props.resumen.porCumplimiento.map((tramo) => ({ clave: tramo.clave, etiqueta: tramo.etiqueta, valor: tramo.valor, tono: tramo.tono })),
);

const filas = computed<FilaRegistro[]>(() => [
    { clave: 'periodo_sin_medir', etiqueta: 'Con el periodo cerrado sin medir', valor: props.resumen.periodoSinMedir, href: '/indicadores?filter[periodo_sin_medir]=1', alerta: true },
    { clave: 'fuera_de_objetivo', etiqueta: 'Fuera de objetivo', valor: props.resumen.fueraDeObjetivo, href: '/indicadores?filter[fuera_de_objetivo]=1' },
    { clave: 'sin_medir', etiqueta: 'Nunca medidos', valor: props.resumen.nuncaMedidos, href: '/indicadores?filter[sin_medir]=1' },
]);
</script>

<template>
    <TarjetaRegistro
        titulo="Indicadores"
        href="/indicadores"
        :cifra="resumen.activos"
        unidad="en seguimiento"
        :de="resumen.total"
        :filas="filas"
        vacio="Todos medidos en su periodo."
    >
        <BarraSegmentada v-if="resumen.activos > 0" :segmentos="segmentos" leyenda />
    </TarjetaRegistro>
</template>
